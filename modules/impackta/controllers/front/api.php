<?php

class impacktaapiModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_footer = false;
    public $content_only = true;
    public $ssl = true;

    public function initContent()
    {
        // El front controller de PS solo hace de puente: cambia al directorio portal
        // para que los includes relativos funcionen y delega toda la API en portal/api.php.
        $portalDir = dirname(__FILE__) . '/../../portal';
        $previousWorkingDirectory = getcwd();

        if (is_dir($portalDir)) {
            chdir($portalDir);
        }

        include $portalDir . '/api.php';

        if ($previousWorkingDirectory !== false) {
            chdir($previousWorkingDirectory);
        }

        exit;
    }
}
