<?php

namespace GlpiPlugin\Githubdownloader;

use Exception;
use PharData;

class Downloader
{
    public function downloadAndInstall(string $githubLink): string
    {
        // Remove https://github.com/ or http://github.com/
        $repo = preg_replace('#^https?://(www\.)?github\.com/#', '', $githubLink);
        $repo = rtrim($repo, '/');
        
        $parts = explode('/', $repo);
        if (count($parts) < 2) {
            throw new Exception("Link inválido. Por favor, insira um link no formato https://github.com/usuario/repositorio");
        }
        
        $githubUser = preg_replace('/[^a-zA-Z0-9_\-]/', '', $parts[0]);
        $githubRepo = preg_replace('/[^a-zA-Z0-9_\-]/', '', $parts[1]);
        
        if (empty($githubUser) || empty($githubRepo)) {
            throw new Exception("Link inválido. Repositório contendo caracteres não permitidos.");
        }
        
        $repo = $githubUser . '/' . $githubRepo;
        
        // O nome do plugin é padronizado no GLPI como lowercase, sem traços ou caracteres especiais
        $pluginName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $githubRepo)); 
        
        $apiUrl = "https://api.github.com/repos/{$repo}/releases/latest";
        
        // Consultar o GitHub para descobrir a versão mais recente
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'GLPI-GitHub-Downloader-Plugin');
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("O repositório {$repo} não existe, não é público, ou não possui uma Release publicada.");
        }
        
        $data = json_decode($response, true);
        if (empty($data['tag_name'])) {
            throw new Exception("Não foi possível obter a versão mais recente do GitHub.");
        }
        
        $tag = $data['tag_name'];
        $tarUrl = "https://github.com/{$repo}/archive/refs/tags/{$tag}.tar.gz";
        
        $pluginsDir = GLPI_ROOT . '/plugins';
        if (!is_writable($pluginsDir)) {
            throw new Exception("O diretório de plugins (" . $pluginsDir . ") não tem permissão de escrita para o processo do servidor web.");
        }
        
        $tarFile = GLPI_TMP_DIR . "/{$pluginName}-{$tag}.tar.gz";
        
        // Baixando o código-fonte da release
        $fp = fopen($tarFile, 'w');
        if (!$fp) {
            throw new Exception("Não foi possível criar o arquivo temporário em " . GLPI_TMP_DIR);
        }

        $maxRedirects = 3;
        $redirectCount = 0;
        $currentUrl = $tarUrl;
        $downloadSuccess = false;

        while ($redirectCount < $maxRedirects) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $currentUrl);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Manually handle redirects for SSRF mitigation
            curl_setopt($ch, CURLOPT_USERAGENT, 'GLPI-GitHub-Downloader-Plugin');
            curl_setopt($ch, CURLOPT_HEADER, false);
            
            // Limit download size to 50MB
            curl_setopt($ch, CURLOPT_NOPROGRESS, false);
            curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($ch, $download_size, $downloaded, $upload_size, $uploaded) {
                if ($downloaded > 52428800) {
                    return 1; // Abort download
                }
                return 0;
            });
            
            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error && strpos($error, 'aborted') !== false) {
                fclose($fp);
                unlink($tarFile);
                throw new Exception("O arquivo baixado excede o limite de tamanho permitido (50MB).");
            }

            if ($httpCode >= 300 && $httpCode < 400 && !empty($redirectUrl)) {
                $parsedHost = parse_url($redirectUrl, PHP_URL_HOST);
                // Mitigate SSRF: Only follow redirects to github domains
                if (!preg_match('/(github\.com|githubusercontent\.com)$/', $parsedHost)) {
                    fclose($fp);
                    unlink($tarFile);
                    throw new Exception("Redirecionamento bloqueado para domínio não confiável: " . $parsedHost);
                }
                $currentUrl = $redirectUrl;
                $redirectCount++;
                // Rewind file pointer for the new request
                rewind($fp);
                ftruncate($fp, 0);
            } elseif ($httpCode === 200) {
                $downloadSuccess = true;
                break;
            } else {
                break;
            }
        }
        
        fclose($fp);

        if (!$downloadSuccess) {
            if (file_exists($tarFile)) {
                unlink($tarFile);
            }
            throw new Exception("Falha ao baixar o arquivo da versão {$tag} do GitHub. (HTTP $httpCode)");
        }
        
        $extractDir = GLPI_TMP_DIR . "/{$pluginName}-extract-" . time();
        if (is_dir($extractDir)) {
            $this->removeDirectory($extractDir);
        }
        mkdir($extractDir);
        
        // Extraindo a versão baixada
        try {
            $phar = new PharData($tarFile);
            
            // Mitigação contra Zip Slip: inspecionar o arquivo antes de extrair
            $iterator = new \RecursiveIteratorIterator($phar);
            foreach ($iterator as $file) {
                $path = $file->getPathname();
                $internalPath = str_replace('phar://' . $tarFile . '/', '', str_replace('\\', '/', $path));
                
                if (strpos($internalPath, '../') !== false || strpos($internalPath, '..\\') !== false || str_starts_with($internalPath, '/')) {
                    throw new Exception("O arquivo baixado contém caminhos de extração inseguros (Zip Slip). Operação abortada.");
                }
            }

            $phar->extractTo($extractDir, null, true);
        } catch (Exception $e) {
            unlink($tarFile);
            $this->removeDirectory($extractDir);
            throw new Exception("Erro ao extrair o arquivo: " . $e->getMessage());
        }
        
        unlink($tarFile);
        
        // Localizando a pasta que foi extraída dentro de extractDir (GitHub empacota numa subpasta)
        $extractedItems = scandir($extractDir);
        $innerDir = null;
        foreach ($extractedItems as $item) {
            if ($item !== '.' && $item !== '..' && is_dir($extractDir . '/' . $item)) {
                $innerDir = $extractDir . '/' . $item;
                break;
            }
        }
        
        if (!$innerDir) {
            $this->removeDirectory($extractDir);
            throw new Exception("A estrutura do arquivo baixado é inválida (nenhum diretório raiz encontrado).");
        }
        
        $finalPluginDir = $pluginsDir . '/' . $pluginName;
        $backupDir = $pluginsDir . '/' . $pluginName . '-old';
        
        // Fazendo backup da versão atual
        if (is_dir($finalPluginDir)) {
            if (is_dir($backupDir)) {
                $this->removeDirectory($backupDir);
            }
            rename($finalPluginDir, $backupDir);
        }
        
        // Mover para o diretório final
        if (!rename($innerDir, $finalPluginDir)) {
            // Se falhar, tenta restaurar o backup
            if (is_dir($backupDir)) {
                rename($backupDir, $finalPluginDir);
            }
            $this->removeDirectory($extractDir);
            throw new Exception("Erro ao mover a pasta do plugin para o diretório de destino.");
        }
        
        $this->removeDirectory($extractDir);
        
        return "Pronto! O plugin '{$pluginName}' (versão {$tag}) foi baixado e atualizado com sucesso!";
    }
    
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = "$dir/$file";
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
