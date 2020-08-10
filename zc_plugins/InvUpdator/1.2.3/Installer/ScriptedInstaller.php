<?php

use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

class ScriptedInstaller extends ScriptedInstallBase
{
    protected function executeInstall()
    {
        zen_deregister_admin_pages(['proseInvUpdator']);
        zen_register_admin_page(
            'proseInvUpdator', 'BOX_ADMIN_INVENTORY', 'FILENAME_INVENTORY', '', 'tools', 'Y', 500);
    }

    protected function executeUninstall()
    {
        zen_deregister_admin_pages(['proseInvUpdator']);
    }
}
