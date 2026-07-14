<?php

namespace GlpiPlugin\Githubdownloader\Tests;

use Exception;
use PHPUnit\Framework\TestCase;

/**
 * Teste unitário para garantir a segurança da extração de nomes de pastas 
 * (Prevenção de Path Traversal e injeção de comandos)
 */
class DownloaderSecurityTest extends TestCase
{
    /**
     * Replica a lógica inicial de extração de URL e sanitização.
     */
    private function parseGithubLink(string $githubLink): array
    {
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

    public function testPrevencaoDePathTraversal(): void
    {
        // Se o usuário tentar passar ../../etc/passwd
        // O repositório ($parts[1]) será "..", que é limpo pelo regex e se torna vazio.
        // A lógica de segurança identifica que ficou vazio e lança uma exceção, bloqueando totalmente.
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Link inválido. Repositório contendo caracteres não permitidos.');
        
        $maliciousLink = 'https://github.com/ufcg/../../etc/passwd';
        $this->parseGithubLink($maliciousLink);
    }

    public function testSanitizacaoDePathTraversalMisto(): void
    {
        // Se passar pontos misturados no nome
        $maliciousLink = 'https://github.com/ufcg/meu..plugin/outracoisa';
        
        [$repo, $pluginName] = $this->parseGithubLink($maliciousLink);
        
        // Os pontos devem sumir
        $this->assertEquals('ufcg/meuplugin', $repo);
        $this->assertEquals('meuplugin', $pluginName, 'O nome do plugin não pode conter pontos');
    }

    public function testPrevencaoDeComandosBash(): void
    {
        // Se tentar passar caracteres de shell como ; rm -rf /
        $maliciousLink = 'https://github.com/user/repo;rm-rf';
        
        [$repo, $pluginName] = $this->parseGithubLink($maliciousLink);
        
        // Ponto e vírgula e espaços devem sumir
        $this->assertEquals('user/reporm-rf', $repo);
        $this->assertEquals('repormrf', $pluginName, 'Caracteres de injeção e hífens devem ser limpos no nome do plugin final');
    }

    public function testSanitizacaoTracosPluginName(): void
    {
        // Plugins GLPI normalmente não contém traços. 
        // O repositório pode ter, mas a pasta do plugin não.
        $link = 'https://github.com/glpi-project/glpi-inventory-plugin';
        
        [$repo, $pluginName] = $this->parseGithubLink($link);
        
        $this->assertEquals('glpi-project/glpi-inventory-plugin', $repo, 'O repositório mantém os traços para bater com o GitHub');
        $this->assertEquals('glpiinventoryplugin', $pluginName, 'O nome da pasta do plugin deve ter traços limpos');
    }
}
