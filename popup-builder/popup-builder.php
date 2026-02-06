<?php
/**
* Plugin Name: Popup Builder - Create highly converting, mobile friendly marketing popups.
* Plugin URI: https://popup-builder.com
* Description: The most complete popup plugin. Html, image, iframe, shortcode, video and many other popup types. Manage popup dimensions, effects, themes and more.
* Version: 4.4.3
* Author: Looking Forward Software Incorporated.
* Author URI: https://popup-builder.com
* License: GPLv2
* Text Domain:  popup-builder
* Domain Path:  /languages/
*/

/*If this file is called directly, abort.*/
if (!defined('WPINC')) {
	die;
}

if (class_exists('SgpbPopupConfig')) {
	wp_die('Please, deactivate the FREE version of Popup Builder plugin before upgrading to PRO.');
}

if (!defined('SGPB_POPUP_FILE_NAME')) {
	define('SGPB_POPUP_FILE_NAME', plugin_basename(__FILE__));
}

if (!defined('SGPB_POPUP_FOLDER_NAME')) {
	define('SGPB_POPUP_FOLDER_NAME', plugin_basename(dirname(__FILE__)));
}

require_once(plugin_dir_path(__FILE__).'com/boot.php');
require_once(plugin_dir_path(__FILE__).'PopupBuilderInit.php');

add_action('admin_notices', 'sgpb_verify_subscriptionplus_deactivated' );

function sgpb_verify_subscriptionplus_deactivated() {		
  if (get_transient('sgpb_subscriptionplus_status')) {
        ?>
        <div class="notice notice-warning is-dismissible">           
						<p><?php
						 /* translators: %s: Edit Popup Link for administrator */ 
              printf( wp_kses_post ( __(
						        'One or more popups with the Subscription Plus Type were deactivated because you deactivated the Popup Builder Subscription Plus add-on. Click <a href="%s">here</a> to view your Popups.',
						        'popup-builder')) , esc_url( admin_url( 'edit.php?post_status=trash&post_type=popupbuilder' ) ) );
						?></p>
        </div>
        <?php
        // Delete the transient so it only shows once
        delete_transient('sgpb_subscriptionplus_status');
    }
}