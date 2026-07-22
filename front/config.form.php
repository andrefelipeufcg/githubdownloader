<?php

include ("../../../inc/includes.php");

use GlpiPlugin\Githubdownloader\Downloader;

Session::checkRight("config", UPDATE);

$is_superadmin = (isset($_SESSION['glpiactiveprofile']['id']) && $_SESSION['glpiactiveprofile']['id'] == 4) 
              || (isset($_SESSION['glpiactiveprofile']['name']) && $_SESSION['glpiactiveprofile']['name'] === 'Super-Admin');

if (!$is_superadmin) {
    Html::displayRightError();
}

if (isset($_POST["download"])) {
    $url = $_POST["github_url"] ?? '';
    
    if (empty($url)) {
        Session::addMessageAfterRedirect("A URL do GitHub é obrigatória.", false, ERROR);
        Html::back();
    } else {
        $downloader = new Downloader();
        try {
            $result = $downloader->downloadAndInstall($url);
            Session::addMessageAfterRedirect($result, false, INFO);
            Html::redirect($CFG_GLPI['root_doc'] . '/front/plugin.php');
        } catch (Exception $e) {
            Session::addMessageAfterRedirect($e->getMessage(), false, ERROR);
            Html::back();
        }
    }
}

Html::header('GitHub Downloader', '', "config", "plugins");

echo "<div class='center'>";
echo "<form method='post' action='config.form.php' onsubmit='return confirm(\"ATENÇÃO: Você está prestes a baixar e instalar código de uma fonte externa. Se o repositório não for confiável ou tiver sido comprometido, isso pode resultar em execução de código malicioso no seu servidor. Você tem certeza que confia neste repositório?\");'>";
echo "<table class='tab_cadre_fixe'>";
echo "<tr><th colspan='2'>Baixar Plugin do GitHub</th></tr>";
echo "<tr class='tab_bg_1'>";
echo "<td>URL do Repositório (ex: https://github.com/ufcg/nomedoplugin)</td>";
echo "<td><input type='text' name='github_url' size='50' class='form-control' placeholder='https://github.com/...'></td>";
echo "</tr>";
echo "<tr class='tab_bg_2'>";
echo "<td colspan='2' class='center'>";
echo "<input type='hidden' name='_glpi_csrf_token' value='" . Session::getNewCSRFToken() . "'>";
echo "<input type='submit' name='download' value='Baixar e Instalar' class='btn btn-primary'>";
echo "</td></tr>";
echo "</table>";
Html::closeForm();
echo "</div>";

Html::footer();
