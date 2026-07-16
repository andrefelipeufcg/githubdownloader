<?php

use Glpi\Plugin\Hooks;

define('PLUGIN_GITHUBDOWNLOADER_VERSION', '1.0.1');
define('PLUGIN_GITHUBDOWNLOADER_MIN_GLPI', '10.0.0');

function plugin_githubdownloader_boot(): void
{
}

function plugin_init_githubdownloader(): void
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['githubdownloader'] = true;
    
    // Adiciona página de configuração
    $PLUGIN_HOOKS[Hooks::CONFIG_PAGE]['githubdownloader'] = 'front/config.form.php';
}

function plugin_version_githubdownloader(): array
{
    return [
        'name'         => 'GitHub Downloader',
        'version'      => PLUGIN_GITHUBDOWNLOADER_VERSION,
        'author'       => 'andrefelipeufcg',
        'license'      => 'GPLv3+',
        'homepage'     => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_GITHUBDOWNLOADER_MIN_GLPI,
            ],
            'php' => [
                'exts' => [
                    'curl' => ['required' => true],
                    'zlib' => ['required' => true],
                    'phar' => ['required' => true],
                ],
            ],
        ],
    ];
}

function plugin_githubdownloader_check_prerequisites(): bool
{
    if (version_compare(GLPI_VERSION, PLUGIN_GITHUBDOWNLOADER_MIN_GLPI, '<')) {
        echo 'Este plugin requer GLPI >= ' . PLUGIN_GITHUBDOWNLOADER_MIN_GLPI;
        return false;
    }
    return true;
}

function plugin_githubdownloader_check_config(): bool
{
    return true;
}
