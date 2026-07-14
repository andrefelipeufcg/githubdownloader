<?php

namespace GlpiPlugin\Githubdownloader\Tests;

use Exception;
use PHPUnit\Framework\TestCase;

/**
 * Teste unitário PURO para validação da lógica de extração de URL do GitHub.
 *
 * Este teste NÃO depende do GLPI, banco de dados ou de conexão com a internet.
 * Ele replica a lógica exata de extração contida no método 
 * Downloader::downloadAndInstall() para garantir que a identificação do
 * repositório e do nome do plugin funcione corretamente com vários formatos de URL.
 *
 * Para rodar:
 *   php phpunit plugins/githubdownloader/tests/DownloaderUrlTest.php --testdox
 */
class DownloaderUrlTest extends TestCase
{
    /**
     * Replica a lógica inicial de extração de URL da classe Downloader.
     * @return array Retorna [repo, pluginName]
     * @throws Exception
     */
    private function parseGithubLink(string $githubLink): array
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
        
        // O nome do plugin é padronizado no GLPI como lowercase sem traços
        $pluginName = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $githubRepo)); 
        
        return [$repo, $pluginName];
    }

    public function testUrlPadraoHttps(): void
    {
        [$repo, $pluginName] = $this->parseGithubLink('https://github.com/ufcg/meuplugin');
        
        $this->assertEquals('ufcg/meuplugin', $repo, 'Deve extrair o repositório corretamente');
        $this->assertEquals('meuplugin', $pluginName, 'O nome do plugin deve ser exato');
    }

    public function testUrlComWww(): void
    {
        [$repo, $pluginName] = $this->parseGithubLink('https://www.github.com/ufcg/PluginTeste');
        
        $this->assertEquals('ufcg/PluginTeste', $repo);
        $this->assertEquals('pluginteste', $pluginName, 'O nome do plugin deve ser forçado para lowercase (padrão GLPI)');
    }

    public function testUrlHttpSemS(): void
    {
        [$repo, $pluginName] = $this->parseGithubLink('http://github.com/andrefelipeufcg/govbrsso');
        
        $this->assertEquals('andrefelipeufcg/govbrsso', $repo);
        $this->assertEquals('govbrsso', $pluginName);
    }

    public function testUrlComBarraNoFinal(): void
    {
        [$repo, $pluginName] = $this->parseGithubLink('https://github.com/glpi-project/glpi/');
        
        $this->assertEquals('glpi-project/glpi', $repo, 'A barra no final deve ser ignorada');
        $this->assertEquals('glpi', $pluginName);
    }

    public function testFormatoCurtoUsuarioRepo(): void
    {
        [$repo, $pluginName] = $this->parseGithubLink('andrefelipeufcg/githubdownloader');
        
        $this->assertEquals('andrefelipeufcg/githubdownloader', $repo, 'Deve aceitar o formato curto user/repo diretamente');
        $this->assertEquals('githubdownloader', $pluginName);
    }

    public function testFalhaComApenasUmaPalavra(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Link inválido');
        
        // Como o explode por '/' resultará em apenas 1 elemento, a exceção deve ser lançada
        $this->parseGithubLink('apenasumapalavra');
    }
}
