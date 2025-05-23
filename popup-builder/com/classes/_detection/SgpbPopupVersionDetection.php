<?php

namespace sgpb;


class SgpbPopupVersionDetection
{
	public static function compareVersions()
	{
		if(!self::checkIfIsOnPopupPage()) // check if user is in popup builder page (only admin pages)
		{
			return [];
		}
		wp_update_plugins(); // Check for plugin updates.
		$plugin_info = get_site_transient("update_plugins"); // get plugins to update
		$registeredPlugins = AdminHelper::getAllExtensions(); // getting active PopupBuilder plugins
		$hasOldPlugin = [];

		foreach($registeredPlugins["active"] as $plugin) {
			$pluginData = get_plugin_data(WP_PLUGIN_DIR.'/'.$plugin["pluginKey"]); // getting plugin registered data
			$plugin["name"] = $pluginData["Name"]; // setting name in plugin array! this is for frontend to show full name
			$plugin_slug = null;
			if ($plugin["pluginKey"] === 'popupbuilder-edd/PopupBuilderEdd.php') {
				if (empty($plugin_info->response[$plugin["pluginKey"]])) {
					$plugin["pluginKey"] =  str_replace("\\","/",WP_PLUGIN_DIR).'/'.$plugin["pluginKey"];
				}
			}
			if(isset($plugin_info->response[$plugin["pluginKey"]])) {
				$plugin_slug = $plugin_info->response[$plugin["pluginKey"]]->slug; // getting current slug generated by wordpress without slug will not update the plugin
			}
			/* this logic will work only < stable versions */
			if(version_compare($plugin["stable_version"], $pluginData["Version"], ">")) {
				$hasLicense = self::getLicenseOfPlugin($plugin);
				$hasOldPlugin[] = [
					"plugin"  => $plugin,
					"message" => self::pluginUpdateMessage("extensions"),
					"license" => $hasLicense,
					"slug"    => $plugin_slug,
				];
			}
		}
		$filteredByLicense = self::pluginUpdateMessage(self::filterPluginsByLicense($hasOldPlugin));

		return $filteredByLicense;
	}

	private static function pluginUpdateMessage($filteredByLicense)
	{
		if(empty($filteredByLicense)) {
			return [];
		}
		$headerMessage = empty($filteredByLicense["autoUpdate"]) ? 'You use major updated version of Popup Builder' : 'Updating active Popup Builder extensions';
		$adminLicenseUrl = admin_url("edit.php?post_type=".SG_POPUP_POST_TYPE."&page=".SGPB_POPUP_LICENSE);

		$modalData = [
			"header" => '<h3 class="sgpb-modal-detection-header">'.$headerMessage.'</h3>',
			"logo" => SG_POPUP_IMG_URL.'sgpbLogo.png',
			"manualMessage" => '<p class="sgpb-text-center">As you don’t have updates for the extensions listed below, you will have issues using the plugin.
<br>Please do the following in order to use the plugin properly:</p>
<p class="sgpb-margin-top-10 sgpb-text-center" style="font-style: italic">If you don’t have an active license, purchase a new one.</p>
<p class="sgpb-text-center">OR</p>
<p class="sgpb-text-center" style="font-style: italic">If you have an active license, add its license code <a href="'.$adminLicenseUrl.'">here</a>.</p>',
			"footerMessage" => '<p class="sgpb-modal-footer-message sgpb-margin-top-30 sgpb-text-center" style="font-style: italic">You can download the previous version of Popup Builder Plugin from <a href="https://downloads.wordpress.org/plugin/popup-builder.3.83.zip">here</a>.</p>'
		];

		return array(
			"modalData" => $modalData,
			"autoUpdate" => empty($filteredByLicense['autoUpdate']) ? [] : $filteredByLicense['autoUpdate'],
			"manualUpdate" => empty($filteredByLicense['manualUpdate']) ? [] : $filteredByLicense['manualUpdate'],
		);
	}

	private static function checkIfIsOnPopupPage()
	{
		if (function_exists('get_current_screen')) {
			if( isset( get_current_screen()->id ) && "popupbuilder_page_license" === get_current_screen()->id) {
				return false;
			}
			if(isset( get_current_screen()->post_type ))
			{
				switch(get_current_screen()->post_type) {
					case SG_POPUP_POST_TYPE:
					case "sgpbtemplate":
					case "sgpbautoresponder":
						return true;
					default:
						return false;
				}
			}
			return false;
		}
		return false;
	}

	private static function getLicenseOfPlugin($oldPlugins)
	{

		$licenseClass = new License();
		$licenses = $licenseClass->getLicenses();

		foreach($licenses as $license) {
			if(false === array_search($license['file'], $oldPlugins)  && 'POPUP_EDD' !== $license['key']) {
				continue;
			}
			if ('POPUP_EDD' !== $license['key'] && false === array_search(str_replace("\\","/",$license['file']), $oldPlugins)) {
				continue;
			}

			$key = isset($license["key"]) ?$license["key"] : '' ;
			$licenseKey = trim((string)get_option("sgpb-license-key-".$key));
			$status = get_option("sgpb-license-status-".$key);
			$license["option_key"] = $licenseKey;
			$license["option_status"] = $status;
			if($status == false || $status != "valid") {
				return false;
			}

			return $license;
		}

		return false;
	}

	private static function filterPluginsByLicense($extensions)
	{
		if(empty($extensions)) {
			return [];
		}
		$extensionsToUpdateNow = array_filter($extensions, function($extension){
			return $extension["license"] !== false;
		});
		$extensionsToUpdate = array_filter($extensions, function($extension){
			return $extension["license"] == false;
		});

		wp_enqueue_script("updates");

		return [
			"autoUpdate"   => array_values($extensionsToUpdateNow),
			"manualUpdate" => array_values($extensionsToUpdate)
		];
	}
}
