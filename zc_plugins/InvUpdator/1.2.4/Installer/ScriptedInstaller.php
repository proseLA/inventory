<?php

	use Zencart\PluginSupport\ScriptedInstaller as ScriptedInstallBase;

	class ScriptedInstaller extends ScriptedInstallBase
	{
		protected function executeInstall()
		{
			zen_deregister_admin_pages(['proseInvUpdator']);
			zen_register_admin_page(
				'proseInvUpdator', 'BOX_ADMIN_INVENTORY', 'FILENAME_INVENTORY', '', 'tools', 'Y', 500);
			//$this->removePrepluginVersion();
		}

		protected function executeUninstall()
		{
			zen_deregister_admin_pages(['proseInvUpdator']);
		}

		protected function removePrepluginVersion()
		{
			if (file_exists($path . 'inventory.php')) {
				unlink($path . 'inventory.php');
			}
			if (file_exists($path . 'includes/extra_datafiles/inventory.php')) {
				unlink($path . 'includes/extra_datafiles/inventory.php');
			}
			if (file_exists($path . DIR_WS_LANGUAGES . $_SESSION['language'] . '/inventory.php')) {
				unlink($path . DIR_WS_LANGUAGES . $_SESSION['language'] . '/inventory.php');
				echo __FILE__ . ':' . __LINE__ . '<br>';
			}

			if (file_exists($path . DIR_WS_LANGUAGES . $_SESSION['language'] . '/extra_definitions/inventory.php')) {
				unlink($path . DIR_WS_LANGUAGES . $_SESSION['language'] . '/extra_definitions/inventory.php');
			}
		}
	}
