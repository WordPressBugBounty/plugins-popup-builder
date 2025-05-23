<?php
namespace sgpb;
use \WP_Query;
use \SgpbPopupConfig;
use \SgpbDataConfig;
use \SGPBConfigDataHelper;

class Actions
{
	public $customPostTypeObj;
	public $insideShortcodes;
	public $mediaButton = false;
	private $excludePopupsQueryString = '';
	
	public function __construct()
	{
		$this->init();
	}

	public function init()
	{
		add_action('init', array($this, 'wpInit'), 100);
		add_action('init', array($this, 'callUnsubcribeUserByEmail'), 9999);
		add_action('admin_init', array($this, 'postTypeInit'), 9999);
		if (method_exists('sgpb\AdminHelper', 'updatesInit') && !has_action('admin_init', array( 'sgpb\AdminHelper', 'updatesInit'))){
			add_action('admin_init', array( 'sgpb\AdminHelper', 'updatesInit'), 9999);
		}
		add_action('admin_menu', array($this, 'addSubMenu'));
		add_action('admin_menu', array($this, 'supportLinks'), 999);
		add_action('admin_head', array($this, 'showPreviewButtonAfterPopupPublish'));
		add_action('admin_head', array($this, 'custom_admin_js'));
		add_action('admin_enqueue_scripts', array($this, 'adminLoadPopups'));
		add_action('admin_action_popupSaveAsNew', array($this, 'popupSaveAsNew'));
		add_action('admin_post_csv_file', array($this, 'getSubscribersCsvFile'));
		add_action('admin_post_sgpb_system_info', array($this, 'getSystemInfoFile'));
		add_action('admin_post_sgpbSaveSettings', array($this, 'saveSettings'), 10, 1);
		add_action('admin_init', array($this, 'userRolesCaps'));
		add_action('admin_notices', array($this, 'pluginNotices'));
		add_action('admin_init', array($this, 'pluginLoaded'));
		add_action('transition_post_status', array($this, 'deletePopup'), 100, 3);
		// activate extensions
		add_action('wp_before_admin_bar_render', array($this, 'pluginActivated'), 10, 2);
		add_action('admin_head', array($this, 'hidePageBuilderEditButtons'));
		add_action('admin_head', array($this, 'hidePublishingActions'));
		add_action('add_meta_boxes', array($this, 'popupMetaboxes'), 100);
		add_filter('post_updated_messages', array($this, 'popupPublishedMessage'), 1, 1);
		add_action('before_delete_post', array($this, 'deleteSubscribersWithPopup'), 1, 1);
		add_action('sgpb_duplicate_post', array($this, 'popupCopyPostMetaInfo'), 10, 2);
		add_filter('get_sample_permalink_html', array($this, 'removePostPermalink'), 1, 1);
		add_action('manage_'.SG_POPUP_POST_TYPE.'_posts_custom_column' , array($this, 'popupsTableColumnsValues'), 10, 2);
		add_action('media_buttons', array($this, 'popupMediaButton'));
		add_filter('mce_external_plugins', array($this, 'editorButton'), 1, 1);
		add_action('admin_enqueue_scripts', array('sgpb\Style', 'enqueueStyles'));
		add_action('admin_enqueue_scripts', array('sgpb\Javascript', 'enqueueScripts'));
		// this action for popup options saving and popup builder classes save ex from post and page
		add_action('save_post', array($this, 'savePost'), 100, 3);
		add_action('wp_enqueue_scripts', array($this, 'enqueuePopupBuilderScripts'));
		add_filter('sgpbOtherConditions', array($this ,'conditionsSatisfy'), 11, 1);
		add_shortcode('sg_popup', array($this, 'sgpbPopupShortcode'));
		add_filter('cron_schedules', array($this, 'cronAddMinutes'), 10, 1);
		add_action('sgpb_send_newsletter', array($this, 'newsletterSendEmail'), 10, 1);
		// add_action('sgpbGetBannerContentOnce', array($this, 'getBannerContent'), 10, 1);
		add_action('plugins_loaded', array($this, 'loadTextDomain'));
		// for change admin popup list order
		add_action('pre_get_posts', array($this, 'preGetPosts'));
		add_action('template_redirect', array($this, 'redirectFromPopupPage'));
		add_filter('views_edit-popupbuilder', array($this, 'mainActionButtons'), 10, 1);
		add_action('wpml_loaded', array($this, 'wpmlRelatedActions'));
		add_action('the_post', array($this, 'postExcludeFromPopupsList'));

		add_filter('get_user_option_screen_layout_'.SG_POPUP_POST_TYPE, array($this, 'screenLayoutSetOneColumn'));
		
		add_filter( 'upload_mimes', array($this, 'popupbuilder_allow_csv_mime_types') );
		add_action( 'plugins_loaded' , array($this, 'popupbuilder_contrucst') ); 		
		
		add_filter('wp_count_posts', array($this ,'sgpbExcludePopupsToShowCounter'), 10, 3);
		add_action( 'wp_trash_post',  array($this, 'sgpb_backupPopupOptionsBeforeTrash') );

	}
	public function popupbuilder_contrucst()
	{
		new SGPBFeedback();
		new SGPBReports();
		new SGPBMenu();
		new Ajax();
	}
	public function popupbuilder_allow_csv_mime_types( $mimes ) {
		$mimes['csv'] = 'text/csv';
		unset( $mimes['exe'] );
		return $mimes;
	}
	
	public function custom_admin_js()
	{
		$currentPostType = AdminHelper::getCurrentPostType();
		if(!empty($currentPostType) && ($currentPostType == SG_POPUP_POST_TYPE || $currentPostType == SG_POPUP_AUTORESPONDER_POST_TYPE || $currentPostType == SG_POPUP_TEMPLATE_POST_TYPE)) {
			wp_register_script( 'sgpb-actions-js-footer', '', array("jquery"), SGPB_POPUP_VERSION, true );
			wp_enqueue_script( 'sgpb-actions-js-footer'  );
			wp_add_inline_script( 'sgpb-actions-js-footer', "jQuery(document).ready(function ($) {
					const myForm = $('.post-type-popupbuilder #posts-filter, .post-type-sgpbtemplate #posts-filter, .post-type-sgpbtemplate #posts-filter');
					myForm.addClass('sgpb-table');
					const searchValue = $('.post-type-popupbuilder #post-search-input, .post-type-sgpbtemplate #posts-filter, .post-type-sgpbautoresponder #posts-filter').val();
					$('.post-type-popupbuilder #posts-filter .tablenav.top .tablenav-pages, .post-type-sgpbtemplate #posts-filter .tablenav.top .tablenav-pages, .post-type-sgpbautoresponder #posts-filter .tablenav.top .tablenav-pages').append($('.subsubsub').addClass('show'));
					myForm.append($('.post-type-popupbuilder #posts-filter .tablenav.bottom .tablenav-pages:not(.no-pages, .one-page) .pagination-links, .post-type-sgpbtemplate #posts-filter .tablenav.bottom .tablenav-pages:not(.no-pages, .one-page) .pagination-links, .post-type-sgpbautoresponder #posts-filter .tablenav.bottom .tablenav-pages:not(.no-pages, .one-page) .pagination-links'));
					$('#sgpbSearchInPosts').val(searchValue);
					$('#sgpbSearchInPosts').keyup('enter', function (e) {
						if (e.key === 'Enter') {
							$('.post-type-popupbuilder #post-search-input, .post-type-sgpbtemplate #post-search-input, .post-type-sgpbautoresponder #post-search-input').val(this.value);
							$(myForm).submit();
						}
					});
					$('#sgpbSearchInPostsSubmit').on('click', function () {
						$('.post-type-popupbuilder #post-search-input, .post-type-sgpbtemplate #post-search-input, .post-type-sgpbautoresponder #post-search-input').val($('#sgpbSearchInPosts').val());
						$(myForm).submit();
					})
				});");			
		}
	}

	public function screenLayoutSetOneColumn()
	{
		return 1;
	}

	public function wpmlRelatedActions()
	{
		// The actions below will be executed right after WPML is fully configured and loaded.
		add_action('admin_head', array($this, 'removeUnneededMetaboxesFromPopups'), 10);
	}

	public function removeUnneededMetaboxesFromPopups()
	{
		if (isset($_GET['post_type']) && sanitize_text_field( wp_unslash( $_GET['post_type'] ) ) == SG_POPUP_POST_TYPE) {
			$exlcudeTrPopupTypes = array(
				'image',
				'video',
				'iframe',
				'recentSales',
				'pdf'
			);
			$exlcudeTrPopupTypes = apply_filters('sgpbNoMcePopupTypes', $exlcudeTrPopupTypes);
			if (isset($_GET['sgpb_type']) && in_array(sanitize_text_field($_GET['sgpb_type']), $exlcudeTrPopupTypes)) {
				remove_meta_box('icl_div', SG_POPUP_POST_TYPE, 'side');
			}
		}
	}

	public function deletePopup($newStatus, $oldStatus, $post)
	{
		$currentPostType = AdminHelper::getCurrentPostType();

		if (!empty($currentPostType) && $currentPostType == SG_POPUP_POST_TYPE) {
			Functions::clearAllTransients();
		}
	}

	public function showPreviewButtonAfterPopupPublish()
	{
		$currentPostType = AdminHelper::getCurrentPostType();
		if (!empty($currentPostType) && ($currentPostType == SG_POPUP_POST_TYPE || $currentPostType == SG_POPUP_AUTORESPONDER_POST_TYPE || $currentPostType == SG_POPUP_TEMPLATE_POST_TYPE)) {
			$styles = '<style>
				.post-type-popupbuilder #save-post,
				.post-type-sgpbtemplate #save-post,
				.post-type-sgpbautoresponder #save-post
				 {
					display:none !important;
				}
				.post-type-popupbuilder .subsubsub,
				.post-type-sgpbtemplate .subsubsub,
				.post-type-sgpbautoresponder .subsubsub
				 {
					display:none !important;
				}
				.post-type-popupbuilder .subsubsub.show,
				.post-type-sgpbtemplate .subsubsub.show,
				.post-type-sgpbautoresponder .subsubsub.show
				 {
					display:block !important;
				}
				.post-type-popupbuilder .search-box,
				.post-type-sgpbtemplate .search-box,
				.post-type-sgpbautoresponder .search-box
				 {
					display:none !important;
				}
				.post-type-popupbuilder .search-box.show,
				.post-type-sgpbtemplate .search-box.show,
				.post-type-sgpbautoresponder .search-box.show
				 {
					display:block !important;
				}
				.post-type-popupbuilder .tablenav-pages.no-pages,
				.post-type-sgpbtemplate .tablenav-pages.no-pages,
				.post-type-sgpbautoresponder .tablenav-pages.no-pages
				 {
				    display: block;
				}
				.post-type-popupbuilder .tablenav-pages *:not(.subsubsub, .subsubsub *),
				.post-type-sgpbtemplate .tablenav-pages *:not(.subsubsub, .subsubsub *),
				.post-type-sgpbautoresponder .tablenav-pages *:not(.subsubsub, .subsubsub *),
				.post-type-popupbuilder .tablenav-pages.no-pages *:not(.subsubsub, .subsubsub *),
				.post-type-sgpbtemplate .tablenav-pages.no-pages *:not(.subsubsub, .subsubsub *),
				.post-type-sgpbautoresponder .tablenav-pages.no-pages *:not(.subsubsub, .subsubsub *),
				.post-type-popupbuilder .tablenav-pages.one-page *:not(.subsubsub, .subsubsub *),
				.post-type-sgpbtemplate .tablenav-pages.one-page *:not(.subsubsub, .subsubsub *),
				.post-type-sgpbautoresponder .tablenav-pages.one-page *:not(.subsubsub, .subsubsub *)
				 {
                    display: none;
                }
			</style>';
			echo wp_kses($styles, AdminHelper::allowed_html_tags());
		}
	}

	public function inactiveExtensionNotice()
	{
		$screen = '';
		$dontShowLicenseBanner = get_option('sgpb-hide-license-notice-banner');
		if ($dontShowLicenseBanner) {
			return $screen;
		}
		$inactive = AdminHelper::getOption('SGPB_INACTIVE_EXTENSIONS');
		$hasInactiveExtensions = AdminHelper::hasInactiveExtensions();
		if (!$inactive) {
			AdminHelper::updateOption('SGPB_INACTIVE_EXTENSIONS', 1);
			if ($hasInactiveExtensions) {
				AdminHelper::updateOption('SGPB_INACTIVE_EXTENSIONS', 'inactive');
				$inactive = 'inactive';
			}

		}
		$licenseSectionUrl = menu_page_url(SGPB_POPUP_LICENSE, false);
		$partOfContent = '<br><br><a href="'.esc_url($licenseSectionUrl).'">'.__('Follow the link', 'popup-builder').'</a> '.__('to finalize the activation.', 'popup-builder');
		if (function_exists('get_current_screen')) {
			$screen = get_current_screen();
			$screenId = $screen->id;
			if ($screenId == SGPB_POPUP_LICENSE_SCREEN) {
				$partOfContent = '';
			}
		}

		if ($hasInactiveExtensions && $inactive == 'inactive') {
			$content = '';
			ob_start();
			?>
			<div id="welcome-panel" class="update-nag sgpb-extensions-notices sgpb-license-notice">
				<div class="welcome-panel-content">
					<b><?php esc_html_e('Thank you for choosing our plugin!', 'popup-builder') ?></b>
					<br>
					<br>
					<b><?php esc_html_e('You have activated Popup Builder extension(s). Please, don\'t forget to activate the license key(s) as well.', 'popup-builder') ?></b>
					<b><?php echo wp_kses($partOfContent, AdminHelper::allowed_html_tags()); ?></b>
				</div>
				<button type="button" class="notice-dismiss" onclick="jQuery('.sgpb-license-notice').remove();"><span class="screen-reader-text"><?php esc_html_e('Dismiss this notice.', 'popup-builder') ?></span></button>
				<span class="sgpb-dont-show-again-license-notice"><?php esc_html_e('Don\'t show again.', 'popup-builder'); ?></span>
			</div>
			<?php
			$content = ob_get_clean();

			echo wp_kses($content, AdminHelper::allowed_html_tags());
			return true;
		}
	}

	public function hidePublishingActions() {
		$currentPostType = AdminHelper::getCurrentPostType();
		if (empty($currentPostType) || $currentPostType != SG_POPUP_POST_TYPE) {
			return false;
		}

		$styles = '<style>
				#misc-publishing-actions .edit-post-status,
				#misc-publishing-actions .edit-timestamp,
				#misc-publishing-actions .edit-visibility {
					display:none !important;
				}
			</style>';
		echo wp_kses($styles, AdminHelper::allowed_html_tags());
	}

	public function hidePageBuilderEditButtons($postId = 0, $post = array())
	{
		$currentPostType = AdminHelper::getCurrentPostType();
		if (empty($currentPostType) || $currentPostType != SG_POPUP_POST_TYPE) {
			return false;
		}
		$excludedPopupTypesFromPageBuildersFunctionality = array(
			'image'
		);

		$excludedPopupTypesFromPageBuildersFunctionality = apply_filters('sgpbHidePageBuilderEditButtons', $excludedPopupTypesFromPageBuildersFunctionality);

		$popupType = AdminHelper::getCurrentPopupType();
		if (in_array($popupType, $excludedPopupTypesFromPageBuildersFunctionality)) {
			$style = '<style>
				#elementor-switch-mode, #elementor-editor {
					display:none !important;
				}
			</style>';
			echo wp_kses($style, AdminHelper::allowed_html_tags());
		}
	}

	public function getBannerContent()
	{
		// right metabox banner content
		$metaboxBannerContent = AdminHelper::getFileFromURL(SGPB_METABOX_BANNER_CRON_TEXT_URL);
		update_option('sgpb-metabox-banner-remote-get', $metaboxBannerContent);

		return true;
	}

	public function wpInit()
	{
		SgpbPopupConfig::addDefine('SGPB_SUBSCRIPTION_ERROR_MESSAGE', __('There was an error while trying to send your request. Please try again', 'popup-builder').'.');
		SgpbPopupConfig::addDefine('SGPB_SUBSCRIPTION_VALIDATION_MESSAGE', __('This field is required', 'popup-builder').'.');
		SgpbPopupConfig::addDefine('SGPB_SUBSCRIPTION_EMAIL_MESSAGE', __('Please enter a valid email address', 'popup-builder').'.');
		
		if (isset($_GET['sgpb_type'])) {
			$_GET['sgpb_type'] = sanitize_text_field( wp_unslash( $_GET['sgpb_type'] ) );
			$fields = array(
				'image',
				'html',
				'fblike',
				'shortcode',
				'iframe',
				'advancedClosing',
				'advancedTargeting',
				'ageVerification',
				'analytics',
				'gamification',
				'geoTargeting',
				'inactivity',
				'login',
				'registration',
				'scroll',
				'pdf',
				'pushNotification',
				'recentSales',
				'video',
				'ageRestriction',
				'social',
				'video',
				'subscription',
				'countdown',
				'contactForm',
				'mailchimp',
				'aweber',
			);
			if (!in_array($_GET['sgpb_type'], $fields)){
				wp_redirect(get_home_url());
				exit();
			}
		}
	}

	public function mainActionButtons($views)
	{
		require_once(SG_POPUP_VIEWS_PATH.'mainActionButtons.php');

		return $views;
	}

	/**
	 * Loads the plugin language files
	 */
	public function loadTextDomain()
	{
		$popupBuilderLangDir = SG_POPUP_BUILDER_PATH.'/languages/';
		$popupBuilderLangDir = apply_filters('popupBuilderLanguagesDirectory', $popupBuilderLangDir);

		$locale = apply_filters('sgpbPluginLocale', get_locale(), SG_POPUP_TEXT_DOMAIN);
		$mofile = sprintf('%1$s-%2$s.mo', 'popup-builder', $locale);

		$mofileLocal = $popupBuilderLangDir.$mofile;

		if (file_exists($mofileLocal)) {
			// Look in local /wp-content/plugins/popup-builder/languages/ folder
			load_textdomain(SG_POPUP_TEXT_DOMAIN, $mofileLocal);
		}
		else {
			// Load the default language files
			load_plugin_textdomain(SG_POPUP_TEXT_DOMAIN, false, $popupBuilderLangDir);
		}

	}

	public function redirectFromPopupPage()
	{
		global $post;
		$currentPostType = '';

		if (is_object($post)) {
			$currentPostType = @$post->post_type;
		}
		// in some themes global $post returns null
		if (empty($currentPostType)) {
			global $post_type;
			$currentPostType = $post_type;
		}
		// for editing popup content via page builders on backend
		if (!isset($_GET) || empty($_GET)) {
			if (!is_admin() && SG_POPUP_POST_TYPE == $currentPostType && !is_preview()) {
				// it's for seo optimization
				status_header(301);
				$homeURL = home_url();
				wp_redirect($homeURL);
				exit();
			}
		}
	}

	public function preGetPosts($query)
	{
		if (!is_admin() || !isset($_GET['post_type']) || sanitize_text_field(wp_unslash($_GET['post_type'])) != SG_POPUP_POST_TYPE) {
			return false;
		}

		// change default order by id and desc
		if (!isset($_GET['orderby'])) {
			$query->set('orderby', 'ID');
			$query->set('order', 'desc');
		}
		$query = apply_filters('sgpbPreGetPosts', $query);

		return true;
	}

	public function pluginNotices()
	{
		
		if (function_exists('get_current_screen')) {
			$screen = get_current_screen();
			$screenId = $screen->id;
			if ($screenId == 'edit-popupbuilder') {
				$notificationsObj = new SGPBNotificationCenter();
				echo wp_kses($notificationsObj->displayNotifications(), AdminHelper::allowed_html_tags());
			}
		}
		$extensions =  AdminHelper::getAllActiveExtensions();
		$updated = get_option('sgpb_extensions_updated');

		$content = '';
		$scan_spam_code = AdminHelper::sgpbScanCustomJsProblem();
		if( $scan_spam_code !== false )
		{			
			$content.= AdminHelper::renderAlertCustomJsProblem( $scan_spam_code['marked_code'] );
		}
		else
		{			
			if (get_option('sgpb-disable-custom-js')) {
				$content.= AdminHelper::renderAlertEnableCustomJS();					
			}			
		}	
		
		// if popup builder has the old version
		if (!get_option('SGPB_POPUP_VERSION')) {
			echo wp_kses( $content, AdminHelper::allowed_html_tags() );		
			return true; 
		}
		$alertProblem = get_option('sgpb_alert_problems');
		// for old users show alert about problems
		if (!$alertProblem) {
			echo wp_kses(AdminHelper::renderAlertProblem(), AdminHelper::allowed_html_tags());
		}
		// Don't show the banner if there's not any extension of Popup Builder or if the user has clicked "don't show"
		if (empty($extensions) || $updated) {
			return $content;
		}
		ob_start();		
		?>
		<div id="welcome-panel" class="update-nag sgpb-extensions-notices">
			<div class="welcome-panel-content">
				<?php echo wp_kses(AdminHelper::renderExtensionsContent(), AdminHelper::allowed_html_tags()); ?>
			</div>
		</div>
		<?php
		$content .= ob_get_clean();
		echo wp_kses($content, AdminHelper::allowed_html_tags());
		return true;
	}

	private function registerImporter()
	{
		require_once SG_POPUP_LIBS_PATH.'SGPBImporter.php';
		$sgpbimporter = new SBPB_WP_Import();
		register_importer(SG_POPUP_POST_TYPE, SG_POPUP_POST_TYPE, __('Popup Builder Importer Tool: Import popups from other website.', 'popup-builder'), array($sgpbimporter, 'dispatch'));
	}

	public function pluginLoaded()
	{
		$this->registerImporter();
		$versionPopup = get_option('SGPB_POPUP_VERSION');
		$convert = get_option('sgpbConvertToNewVersion');
		$unsubscribeColumnFixed = get_option('sgpbUnsubscribeColumnFixed');
		AdminHelper::makeRegisteredPluginsStaticPathsToDynamic();

		if (!$unsubscribeColumnFixed) {
			AdminHelper::addUnsubscribeColumn();
			update_option('sgpbUnsubscribeColumnFixed', 1);
			delete_option('sgpbUnsubscribeColumn');
		}

		if ($versionPopup && !$convert) {
			update_option('sgpbConvertToNewVersion', 1);
			ConvertToNewVersion::convert();
			Installer::registerPlugin();
		}
	}

	public function popupMediaButton()
	{
		if (!$this->mediaButton) {
			$this->mediaButton = true;
			self::enqueueScriptsForPageBuilders();
			if (function_exists('get_current_screen')) {
				$screen = get_current_screen();
				if (!empty($screen)) {
					echo wp_kses(new MediaButton(), AdminHelper::allowed_html_tags());
				}
			}
		}
	}

	public function editorButton($plugins)
	{
		if (empty($this->mediaButton)) {
			$this->mediaButton = true;
			$currentPostType = AdminHelper::getCurrentPostType();
			add_action('admin_footer', function() use ($currentPostType) {
				self::enqueueScriptsForPageBuilders();
				if (!empty($currentPostType) && $currentPostType == SG_POPUP_POST_TYPE) {
					require_once(SG_POPUP_VIEWS_PATH.'htmlCustomButtonElement.php');
				}
			});
		}

		return $plugins;
	}

	public static function enqueueScriptsForPageBuilders()
	{
		require_once(ABSPATH.'wp-admin/includes/screen.php');
		global $post;
		if (function_exists('get_current_screen')) {
			$screen = get_current_screen();
			if ((!empty($screen->id) && $screen->id == SG_POPUP_POST_TYPE) || !empty($post)) {
				if (!isset($_GET['fl_builder'])) {
					Javascript::enqueueScripts('post-new.php');
					Style::enqueueStyles('post-new.php');
				}
			}
		}
		else if (isset($_GET['fl_builder'])) {
			Javascript::enqueueScripts('post-new.php');
			Style::enqueueStyles('post-new.php');
		}
	}

	public function userRolesCaps()
	{
		$userSavedRoles = get_option('sgpb-user-roles');

		if (!$userSavedRoles) {
			$userSavedRoles = array('administrator');
		}
		else {
			array_push($userSavedRoles, 'administrator');
		}

		foreach ($userSavedRoles as $theRole) {
			$role = get_role($theRole);
			if (empty($role)) {
				continue;
			}

			$role->add_cap('read');
			$role->add_cap('read_post');
			$role->add_cap('read_private_sgpb_popups');
			$role->add_cap('edit_sgpb_popup');
			$role->add_cap('edit_sgpb_popups');
			$role->add_cap('edit_others_sgpb_popups');
			$role->add_cap('edit_published_sgpb_popups');
			$role->add_cap('publish_sgpb_popups');
			$role->add_cap('delete_sgpb_popups');
			$role->add_cap('delete_published_posts');
			$role->add_cap('delete_others_sgpb_popups');
			$role->add_cap('delete_private_sgpb_popups');
			$role->add_cap('delete_private_sgpb_popup');
			$role->add_cap('delete_published_sgpb_popups');

			// For popup builder sub-menus and terms
			$role->add_cap('sgpb_manage_options');
			$role->add_cap('manage_popup_terms');
			$role->add_cap('manage_popup_categories_terms');
			$role = apply_filters('sgpbUserRoleCap', $role);
		}

		return true;
	}

	public function pluginActivated()
	{
		if (!get_option('sgpbActivateExtensions') && SGPB_POPUP_PKG != SGPB_POPUP_PKG_FREE) {
			$obj = new PopupExtensionActivator();
			$obj->activate();
			update_option('sgpbActivateExtensions', 1);
		}
	}

	public function sgpbPopupShortcode($args, $content)
	{
		if (empty($args) || empty($args['id'])) {
			return $content;
		}

		$oldShortcode = isset($args['event']) && $args['event'] === 'onload';
		$isInherit = isset($args['event']) && $args['event'] == 'inherit';
		$event = '';

		$shortcodeContent = '';
		$argsId = $popupId = (int)$args['id'];

		// for old popups
		if (function_exists('sgpb\sgpGetCorrectPopupId')) {
			$popupId = sgpGetCorrectPopupId($popupId);
		}

		$popup = SGPopup::find($popupId);
		$popup = apply_filters('sgpbShortCodePopupObj', $popup);

		$event = preg_replace('/on/', '', (isset($args['event']) ? $args['event'] : ''));
		// when popup does not exists or popup post status it's not publish ex when popup in trash
		if (empty($popup) || (!is_object($popup) && $popup != 'publish')) {
			return $content;
		}

		$isActive = $popup->isActive();
		if (!$isActive) {
			return $content;
		}

		$alreadySavedEvents = $popup->getEvents();
		$loadableMode = $popup->getLoadableModes();

		if (!isset($args['event']) && isset($args['insidepopup'])) {
			unset($args['insidepopup']);
			$event = 'insideclick';
			$insideShortcodeKey = $popupId.$event;

			// for prevent infinity chain
			if (is_array($this->insideShortcodes) && in_array($insideShortcodeKey, $this->insideShortcodes)) {
				$shortcodeContent =  SGPopup::renderPopupContentShortcode($content, $argsId, $event, $args);

				return $shortcodeContent;
			}
			$this->insideShortcodes[] = $insideShortcodeKey;
		}
		// if no event attribute is set, or old shortcode
		if (!isset($args['event']) || $oldShortcode || $isInherit) {
			$loadableMode = $popup->getLoadableModes();
			if (!empty($content)) {
				$alreadySavedEvents = false;
			}
			// for old popup, after the update, there aren't any events
			if (empty($alreadySavedEvents)) {
				$event = '';
				if (!empty($content)) {
					$event = 'click';
				}
				if (!empty($args['event'])) {
					$event = $args['event'];
				}
				$event = preg_replace('/on/', '', $event);
				$popup->setEvents(array($event));
			}
			if (empty($loadableMode)) {
				$loadableMode = array();
			}
			$loadableMode['option_event'] = true;
		}
		else {
			$event = $args['event'];
			$event = preg_replace('/on/', '', $event);
			$popup->setEvents(array($event));
		}

		$popup->setLoadableModes($loadableMode);

		$groupObj = new PopupGroupFilter();
		$groupObj->setPopups(array($popup));
		$loadablePopups = $groupObj->filter();
		$scriptsLoader = new ScriptsLoader();
		$scriptsLoader->setLoadablePopups($loadablePopups);
		$scriptsLoader->loadToFooter();

		if (!empty($content)) {
			$matches = SGPopup::getPopupShortcodeMatchesFromContent($content);
			if (!empty($matches)) {
				foreach ($matches[0] as $key => $value) {
					$attrs = shortcode_parse_atts($matches[3][$key]);
					if (empty($attrs['id'])) {
						continue;
					}
					$shortcodeContent = SGPopup::renderPopupContentShortcode($content, $attrs['id'], $attrs['event'], $attrs);
					break;
				}
			}
		}

		if (isset($event) && $event != 'onload' && !empty($content)) {
			$shortcodeContent = SGPopup::renderPopupContentShortcode($content, $argsId, $event, $args);
		}
		$shortcodeContent = apply_filters('sgpbPopupShortCodeContent', $shortcodeContent);

		return do_shortcode($shortcodeContent);
	}

	public function deleteSubscribersWithPopup($postId)
	{
		global $post_type;

		if ($post_type == SG_POPUP_POST_TYPE) {
			AdminHelper::deleteSubscriptionPopupSubscribers($postId);
		}
	}

	public function cronAddMinutes($schedules)
	{
		$schedules['sgpb_newsletter_send_every_minute'] = array(
			'interval' => SGPB_CRON_REPEAT_INTERVAL * 60,
			'display' => __('Once Every Minute', 'popup-builder')
		);

		$schedules['sgpb_banners'] = array(
			'interval' => SGPB_TRANSIENT_TIMEOUT_WEEK,
			'display' => __('Once Every Week', 'popup-builder')
		);

		$schedules = apply_filters('sgpbCronTimeoutSettings', $schedules);

		return $schedules;
	}

	public function newsletterSendEmail()
	{
		global $wpdb;
		$newsletterOptions = get_option('SGPB_NEWSLETTER_DATA');

		if (empty($newsletterOptions)) {
			wp_clear_scheduled_hook('sgpb_send_newsletter');
		}
		$subscriptionFormId = (int)$newsletterOptions['subscriptionFormId'];
		$subscriptionFormTitle = get_the_title($subscriptionFormId);
		$emailsInFlow = (int)$newsletterOptions['emailsInFlow'];
		$mailSubject = $newsletterOptions['newsletterSubject'];
		$fromEmail = $newsletterOptions['fromEmail'];
		$emailMessage = $newsletterOptions['messageBody'];

		$allAvailableShortcodes = array();
		$allAvailableShortcodes['patternFirstName'] = '/\[First name]/';
		$allAvailableShortcodes['patternLastName'] = '/\[Last name]/';
		$allAvailableShortcodes['patternBlogName'] = '/\[Blog name]/';
		$allAvailableShortcodes['patternUserName'] = '/\[User name]/';
		$allAvailableShortcodes['patternUnsubscribe'] = '';

		$pattern = "/\[(\[?)(Unsubscribe)(?![\w-])([^\]\/]*(?:\/(?!\])[^\]\/]*)*?)(?:(\/)\]|\](?:([^\[]\*+(?:\[(?!\/\2\])[^\[]\*+)\*+)\[\/\2\])?)(\]?)/";
		preg_match($pattern, $emailMessage, $matches);
		$title = __('Unsubscribe', 'popup-builder');
		if ($matches) {
			$patternUnsubscribe = $matches[0];
			// If user didn't change anything inside the [unsubscribe] shortcode $matches[2] will be equal to 'Unsubscribe'
			if ($matches[2] == 'Unsubscribe') {
				$pattern = '/\s(\w+?)="(.+?)"]/';
				preg_match($pattern, $matches[0], $matchesTitle);
				if (!empty($matchesTitle[2])) {
					$title = AdminHelper::removeAllNonPrintableCharacters($matchesTitle[2], 'Unsubscribe');
				}
			}
			$allAvailableShortcodes['patternUnsubscribe'] = $patternUnsubscribe;
		}

		// When email is not valid we don't continue
		if (!preg_match('/^[a-zA-Z0-9_.-]+@[a-zA-Z0-9-]+.[a-zA-Z0-9-.]+$/', $fromEmail)) {
			wp_clear_scheduled_hook('sgpb_send_newsletter');
			return false;
		}
		$table_subscription = $wpdb->prefix.SGPB_SUBSCRIBERS_TABLE_NAME;
		$selectionQuery = "SELECT id FROM $table_subscription WHERE";
		$selectionQuery = apply_filters('sgpbUserSelectionQuery', $selectionQuery);	
		
		$result = $wpdb->get_row( $wpdb->prepare("$selectionQuery and subscriptionType = %d limit 1", $subscriptionFormId), ARRAY_A);//db call ok
		$currentStateEmailId = isset($result['id']) ? (int)$result['id'] : 0;
		$table_subscription = $wpdb->prefix.SGPB_SUBSCRIBERS_TABLE_NAME;
		$totalSubscribers = $wpdb->get_var( $wpdb->prepare("SELECT count(*) FROM $table_subscription WHERE unsubscribed = 0 and subscriptionType = %d", $subscriptionFormId) );

		// $currentStateEmailId == 0 when all emails status = 1
		if ($currentStateEmailId == 0) {
			// Clear schedule hook
			$headers  = 'MIME-Version: 1.0'."\r\n";
			$headers .= 'Content-type: text/html; charset=UTF-8'."\r\n";
			$successTotal = get_option('SGPB_NEWSLETTER_'.$subscriptionFormId);
			if (!$successTotal) {
				$successTotal = 0;
			}
			$failedTotal = $totalSubscribers - $successTotal;
			/* translators: subscription Form Title, success Total ,total Subscribers, failed Total . */
			$emailMessageCustom = __('Your mail list %1$s delivered successfully!
						%2$d of the %3$d emails succeeded, %4$d failed.
						For more details, please download log file inside the plugin.
						This email was generated via Popup Builder plugin.', 'popup-builder');
			$emailMessageCustom = sprintf($emailMessageCustom, $subscriptionFormTitle, $successTotal, $totalSubscribers, $failedTotal);

			wp_mail($fromEmail, $subscriptionFormTitle.' list has been successfully delivered!', $emailMessageCustom, $headers);
			delete_option('SGPB_NEWSLETTER_'.$subscriptionFormId);
			wp_clear_scheduled_hook('sgpb_send_newsletter');
			return;
		}

		$getAllDataSql = 'SELECT id, firstName, lastName, email FROM '.$wpdb->prefix.SGPB_SUBSCRIBERS_TABLE_NAME.' WHERE';
		$getAllDataSql = apply_filters('sgpbUserSelectionQuery', $getAllDataSql);
		$subscribers = $wpdb->get_results( $wpdb->prepare( "$getAllDataSql and id >= %d and subscriptionType = %s limit %d", $currentStateEmailId, $subscriptionFormId, $emailsInFlow), ARRAY_A);

		$subscribers = apply_filters('sgpNewsletterSendingSubscribers', $subscribers);

		$blogInfo = wp_specialchars_decode( get_bloginfo() );
		$headers = array(
			'From: "'.$blogInfo.'" <'.$fromEmail.'>' ,
			'MIME-Version: 1.0' ,
			'Content-type: text/html; charset=UTF-8'
		);

		foreach ($subscribers as $subscriber) {
			$replacementId = $subscriber['id'];
			$allAvailableShortcodes = apply_filters('sgpbNewsletterShortcodes', $allAvailableShortcodes, $subscriptionFormId, $replacementId);
			$replacementFirstName = $subscriber['firstName'];
			$replacementLastName = $subscriber['lastName'];
			$replacementBlogName = $newsletterOptions['blogname'];
			$replacementUserName = $newsletterOptions['username'];
			$replacementEmail = $subscriber['email'];
			$replacementUnsubscribe = get_home_url();
			$replacementUnsubscribe .= '?sgpbUnsubscribe='.md5($replacementId.$replacementEmail);
			$replacementUnsubscribe .= '&email='.$subscriber['email'];
			$replacementUnsubscribe .= '&popup='.$subscriptionFormId;
			$replacementUnsubscribe = '<br><a href="'.$replacementUnsubscribe.'">'.$title.'</a>';

			// Replace First name and Last name from email message
			$emailMessageCustom = preg_replace($allAvailableShortcodes['patternFirstName'], $replacementFirstName, $emailMessage);
			$emailMessageCustom = preg_replace($allAvailableShortcodes['patternLastName'], $replacementLastName, $emailMessageCustom);
			$emailMessageCustom = preg_replace($allAvailableShortcodes['patternBlogName'], $replacementBlogName, $emailMessageCustom);
			$emailMessageCustom = preg_replace($allAvailableShortcodes['patternUserName'], $replacementUserName, $emailMessageCustom);
			$emailMessageCustom = str_replace($allAvailableShortcodes['patternUnsubscribe'], $replacementUnsubscribe, $emailMessageCustom);
			if (!empty($allAvailableShortcodes['extraShortcodesWithValues'])) {
				$customFields = $allAvailableShortcodes['extraShortcodesWithValues'];
				foreach ($customFields as $customFieldKey => $customFieldValue) {
					$finalShortcode = '/\['.$customFieldKey.']/';
					$emailMessageCustom = preg_replace($finalShortcode, $customFieldValue, $emailMessageCustom);
				}
			}
			$emailMessageCustom = stripslashes($emailMessageCustom);

			$emailMessageCustom = apply_filters('sgpNewsletterSendingMessage', $emailMessageCustom);
			$mailStatus = wp_mail($subscriber['email'], $mailSubject, $emailMessageCustom, $headers);
			if (!$mailStatus) {
				$table_sgpb_subscription_error_log = $wpdb->prefix.SGPB_SUBSCRIBERS_ERROR_TABLE_NAME;
				$wpdb->query( $wpdb->prepare("INSERT INTO $table_sgpb_subscription_error_log (`popupType`, `email`, `date`) VALUES (%s, %s, %s)", $subscriptionFormId, $subscriber['email'], gmdate('Y-m-d H:i')) );continue;
			}

			$successCount = get_option('SGPB_NEWSLETTER_'.$subscriptionFormId);
			if (!$successCount) {
				update_option('SGPB_NEWSLETTER_'.$subscriptionFormId, 1);
			}
			else {
				update_option('SGPB_NEWSLETTER_'.$subscriptionFormId, ++$successCount);
			}
		}
		// Update the status of all the sent mails
		$table_sgpb_subscription = $wpdb->prefix.SGPB_SUBSCRIBERS_TABLE_NAME;
		$wpdb->query( $wpdb->prepare("UPDATE $table_sgpb_subscription SET status = 1 where id >= %d and subscriptionType = %d limit %d", $currentStateEmailId, $subscriptionFormId, $emailsInFlow) );
	}

	private function unsubscribe($params = array())
	{
		AdminHelper::deleteUserFromSubscribers($params);
	}

	public function enqueuePopupBuilderScripts()
	{
		// for old popups
		if (get_option('SGPB_POPUP_VERSION')) {
			ConvertToNewVersion::saveCustomInserted();
		}

		$popupLoaderObj = PopupLoader::instance();
		if (is_object($popupLoaderObj)) {
			$popupLoaderObj->loadPopups();
		}
	}

	public function adminLoadPopups($hook)
	{
		$allowedPages = array();
		$allowedPages = apply_filters('sgpbAdminLoadedPages', $allowedPages);

		if (!empty($allowedPages) && is_array($allowedPages) && in_array($hook, $allowedPages)) {
			$scriptsLoader = new ScriptsLoader();
			$scriptsLoader->setIsAdmin(true);
			$scriptsLoader->loadToFooter();
		}
	}

	public function postTypeInit()
	{
		/**
		 * We only allow administrator or roles allowed in setting to do this action
		*/ 			
		
		$allowToAction = AdminHelper::userCanAccessTo();

		if( !$allowToAction )
		{
			/**
			 * We only allow administrator or roles allowed in setting to do this action
			*/ 			
			if ( ! current_user_can( 'manage_options' ) ) {
				
				return;
			}
		}
		if( is_user_logged_in() ) {
		
			$current_user = get_current_user_id();
			$sgpb_save_subcribers_custom = get_user_meta( $current_user , 'sgpb_save_subcribers_custom', true);
				
			if( isset( $sgpb_save_subcribers_custom ) && sanitize_text_field( wp_unslash( $sgpb_save_subcribers_custom ) ) == '1')
			{
				
				add_filter('upload_dir', [$this, 'sgpb_setCustomUploadSubscribersPathImport']);
				add_filter('wp_handle_upload_prefilter', [$this, 'sgpb_setCustomNameUploadFilter']);
			}
			else
			{
				remove_filter('wp_handle_upload_prefilter', [$this, 'sgpb_setCustomNameUploadFilter']);
				remove_filter('upload_dir', [$this, 'sgpb_setCustomUploadSubscribersPathImport']);
			}
			
		}

		$adminUrl = admin_url();

		if (isset($_GET['page']) && sanitize_text_field(wp_unslash( $_GET['page'])) == 'PopupBuilder') {
			echo '<span>';
					printf(
						/* translators: Link to edit Popup item, location. */
						esc_html__('Popup Builder plugin has been successfully updated. Please %1$s to go to the new Dashboard of the plugin.', 'popup-builder'),
						sprintf(
							/* translators: admin Url, Popup Post type. */
							'<a href="%1$sedit.php?post_type=%2$s">click here</a>',
								esc_url($adminUrl),
								esc_html__( 'popupbuilder', 'popup-builder' )
						)
					);
					echo '</span>';
			wp_die();
		}
		

		AdminHelper::removeUnnecessaryCodeFromPopups();
		
		/**
		 * We only allow administrator to do this action
		*/ 
		if (isset($_POST['sgpb-is-preview']) && $_POST['sgpb-is-preview'] == 1 && isset($_POST['post_ID'])) {			
		
			/* Validate nonce */			
			$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';			
			$postId = sanitize_text_field( wp_unslash( $_POST['post_ID'] ) );			

			if ( empty( $nonce ) || !wp_verify_nonce( $nonce, 'update-post_'.$postId ) ) { 		
				return;
			}
			
			$post = get_post($postId);
			
			/**
			* We only allow administrator to do this action
			*/
		
			$this->savePost($postId, $post, false);
		}
		
		
	}

	public function callUnsubcribeUserByEmail()
	{			
		/**
		 * We collect GET parameters for Unsubscriber such as 'sgpbUnsubscribe', 'email', 'popup ID'. 
		 * This happens when User wants to unsubcibe on the Subscription Popup through Link in email.
		*/ 
		$unsubscribeArgs = $this->collectUnsubscriberArgs();
		
		if (!empty($unsubscribeArgs)) {
			$this->unsubscribe($unsubscribeArgs);
		}

		// This should call with init hook to register new post type popup
		$this->customPostTypeObj = new RegisterPostType();
		
	}

	public function collectUnsubscriberArgs()
	{
		if (!isset($_GET['sgpbUnsubscribe'])) {
			return false;
		}
		$args = array();
		if (isset($_GET['sgpbUnsubscribe'])) {
			$args['token'] = sanitize_text_field( wp_unslash ( $_GET['sgpbUnsubscribe'] ) );
		}
		if (isset($_GET['email'])) {
			$args['email'] = sanitize_email( wp_unslash( $_GET['email'] ) );
		}
		if (isset($_GET['popup'])) {
			$args['popup'] = sanitize_text_field( wp_unslash( $_GET['popup'] ) );
		}

		return $args;
	}

	public function addSubMenu()
	{
		// We need to check license keys and statuses before adding new menu "License" item
		new Updates();

		$this->customPostTypeObj->addSubMenu();
	}

	public function supportLinks()
	{
		if (SGPB_POPUP_PKG == SGPB_POPUP_PKG_FREE) {
			if (method_exists($this->customPostTypeObj, 'supportLinks')) {
				$this->customPostTypeObj->supportLinks();
			}
		}
	}

	public function popupMetaboxes()
	{
		$this->customPostTypeObj->addPopupMetaboxes();
	}

	public function savePost($postId = 0, $post = array(), $update = false)
	{
		global $SGPB_OPTIONS;
	
		if ($post->post_type !== SG_POPUP_POST_TYPE) {
			return;
		}
		
		if( !isset( $_POST['sgpb-type'] ) )
		{
			return;
		}
		/* Validate nonce */			
		$nonce = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';		
		if ( empty( $nonce ) || !wp_verify_nonce( $nonce, 'update-post_'.$postId ) ) { 		
			return;
		}
		
		$allowToAction = AdminHelper::userCanAccessTo();
		Functions::clearAllTransients();	
		
		// Do not processing the whole input
		$sgpb_postData = array_filter(	
			$_POST,
			function ($key) { return preg_match('/sgpb-*/', $key) === 1;},			
			ARRAY_FILTER_USE_KEY
		);		
		
		$postData = SGPopup::parsePopupDataFromData($sgpb_postData);
			
		$saveMode = '';
		$postData['sgpb-post-id'] = $postId;
		// If preview mode
		if (isset($postData['sgpb-is-preview']) && $postData['sgpb-is-preview'] == 1) {
			$saveMode = '_preview';
			SgpbPopupConfig::popupTypesInit();
			SgpbDataConfig::init();
			// published popup
			if (empty($post)) {
				global $post;
				$postId = $post->ID;
			}
			if ($post->post_status != 'draft') {
				$posts = array();
				$popupContent = $post->post_content;

				$query = new WP_Query(
					array(
						'post_parent'    => $postId,
						'posts_per_page' => - 1,
						'post_type'      => 'revision',
						'post_status'    => 'inherit'
					)
				);
				$query = apply_filters('sgpbSavePostQuery', $query);

				while ($query->have_posts()) {
					$query->the_post();
					if (empty($posts)) {
						$posts[] = $post;
					}
				}
				if (!empty($posts[0])) {
					$popup = $posts[0];
					$popupContent = $popup->post_content;
				}
			}
		}


		if (empty($post)) {
			$saveMode = '';
		}

		if ($post->post_status == 'draft') {
			$saveMode = '_preview';
		}

		/* In preview mode saveMode should be true*/
		if ((!empty($post) && $post->post_type == SG_POPUP_POST_TYPE) || $saveMode || (empty($post) && !$saveMode)) {
			if (!$allowToAction) {
				wp_redirect(get_home_url());
				exit();
			}
			if (!empty($postData['sgpb-type'])) {
				$popupType = $postData['sgpb-type'];
				$popupClassName = SGPopup::getPopupClassNameFormType($popupType);
				$popupClassPath = SGPopup::getPopupTypeClassPath($popupType);
				require_once($popupClassPath.$popupClassName.'.php');
				$popupClassName = __NAMESPACE__.'\\'.$popupClassName;
				$popupClassName::create($postData, $saveMode, 1);
			}
		}
		else {
			$content = get_post_field('post_content', $postId);
			SGPopup::deletePostCustomInsertedData($postId);
			SGPopup::deletePostCustomInsertedEvents($postId);
			/*We detect all the popups that were inserted as a custom ones, in the content.*/			
			SGPopup::savePopupsFromContentClasses($content, $post);
		}
	}

	/**
	 * Check Popup is satisfy for popup condition
	 *
	 * @param array $args
	 *
	 * @return array
	 *
	 *@since 1.0.0
	 *
	 */
	public function conditionsSatisfy($args = array())
	{
		if (isset($args['status']) && $args['status'] === false) {
			return $args;
		}
		$args['status'] = PopupChecker::checkOtherConditionsActions($args);

		return $args;
	}

	public function popupsTableColumnsValues($column, $postId)
	{
		$postId = (int)sanitize_text_field($postId);// Convert to int for security reasons
		global $post_type;
		if ($postId) {
			$args['status'] = array('publish', 'draft', 'pending', 'private', 'trash');
			$popup = SGPopup::find($postId, $args);
		}

		if (empty($popup) && $post_type == SG_POPUP_POST_TYPE) {
			return false;
		}
		if(get_post_status( $postId ) !== 'trash') {
			if ($column == 'shortcode') {				
				$shortcodeInput = '<input type="text" onfocus="this.select();" readonly value="[sg_popup id='.esc_attr($postId).']" class="large-text code">';
				echo wp_kses($shortcodeInput, AdminHelper::allowed_html_tags());
			}	
			else if ($column == 'counter') {
				$count = $popup->getPopupOpeningCountById($postId);
				$counter =  '<div ><span>'.$count.'</span>'.'<input onclick="SGPBBackend.resetCount('.esc_attr($postId).', true);" type="button" name="" class="sgpb-btn sgpb-btn-dark-outline" value="'.__('reset', 'popup-builder').'"></div>';
				echo wp_kses($counter, AdminHelper::allowed_html_tags());
			}
			else if ($column == 'onOff') {
				$popupPostStatus = get_post_status($postId);
				if ($popupPostStatus == 'publish' || $popupPostStatus == 'draft'|| $popupPostStatus == 'private') {
					$isActive = $popup->getOptionValue('sgpb-is-active', true);
				}
				$checked = isset($isActive) && $isActive ? 'checked' : '';
				$switcher = '<label class="sgpb-switch">
	                    <input class="sg-switch-checkbox sgpb-popup-status-js" value="1" data-switch-id="'.esc_attr($postId).'" type="checkbox" '.esc_attr($checked).'>
	                    <div class="sgpb-slider sgpb-round"></div>
	                </label>';
				echo wp_kses($switcher, AdminHelper::allowed_html_tags());
			}
			else if ($column == 'sgpbIsRandomEnabled') {
				$showValues = apply_filters('sgpbAddRandomTableColumnValues', $postId);
				echo wp_kses($showValues, AdminHelper::allowed_html_tags());
			}
			else if ($column == 'options') {
				$cloneUrl = AdminHelper::popupGetClonePostLink($postId);
				$actionButtons = '<div class="icon icon_blue">
									<img src="'.SG_POPUP_PUBLIC_URL.'icons/iconEdit.png" title="Edit" alt="Edit" class="icon_edit" onclick="location.href=\''.get_edit_post_link($postId).'\'">
								</div>';
				$actionButtons .= '<div class="icon icon_blue">
									<img src="'.SG_POPUP_PUBLIC_URL.'icons/iconClone.png"  title="Clone" alt="Clone" class="icon_clone" onclick="location.href=\''.esc_url($cloneUrl).'\'">
								</div>';
				$actionButtons .= '<div class="icon icon_pink">
									<img src="'.SG_POPUP_PUBLIC_URL.'icons/recycle-bin.svg" title="Remove" alt="Remove" class="icon_remove" onclick="location.href=\''.get_delete_post_link($postId).'\'">
								</div>';

				echo wp_kses($actionButtons, AdminHelper::allowed_html_tags());
			}
			else if ($column == 'className') {
				$className = '<input type="text" onfocus="this.select();" readonly value="sg-popup-id-'.esc_attr($postId).'" class="large-text code">';
				echo wp_kses($className, AdminHelper::allowed_html_tags());
			}
			
		}
		if ($column == 'type') {
			global $SGPB_POPUP_TYPES;
			$type = $popup->getType();
			if (isset($SGPB_POPUP_TYPES['typeLabels'][$type])) {
				$type = $SGPB_POPUP_TYPES['typeLabels'][$type];
			}
			echo esc_html($type);			
		}		
		
	}

	/*
	 * This function calls the creation of a new copy of the selected post (by default preserving the original publish status)
	 * then redirects to the post list
	 */
	public function popupSaveAsNew($status = '')
	{
		/**
		 * We only allow administrator or roles allowed in setting to do this action
		*/ 		

		$allowToAction = AdminHelper::userCanAccessTo();

		if( !$allowToAction )
		{
			/**
			 * We only allow administrator or roles allowed in setting to do this action
			*/ 			
			if ( ! current_user_can( 'manage_options' ) ) {
				
				wp_die(esc_html__('You do not have permission to clone the popup!', 'popup-builder'));
			}
		}
		
		/* Validate nonce */			
		$nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';			
		$postId = isset($_REQUEST['post']) ? (int) sanitize_text_field( wp_unslash( $_REQUEST['post'] ) ) : 0;			

		if ( empty( $nonce ) || !wp_verify_nonce( $nonce, 'duplicate-post_'.$postId ) ) { 		
			wp_die(esc_html__('You do not have permission to clone the popup!', 'popup-builder'));
		}
		
		if (!(isset($_GET['post']) || isset($_POST['post']) || (isset($_REQUEST['action']) && 'popupSaveAsNew' == sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) ) ) ) {
			wp_die(esc_html__('No post to duplicate has been supplied!', 'popup-builder'));
		}
		// Get the original post
		$id = ( isset($_GET['post']) ? sanitize_text_field( wp_unslash( $_GET['post'] ) ) : sanitize_text_field( wp_unslash( $_POST['post'] ) ) );

		check_admin_referer('duplicate-post_'.$id);

		$post = get_post($id);

		// Copy the post and insert it
		if (isset($post) && $post != null) {
			$newId = $this->popupCreateDuplicate($post, $status);
			$postType = $post->post_type;

			if ($status == '') {
				$sendBack = wp_get_referer();
				if (!$sendBack ||
					strpos($sendBack, 'post.php') !== false ||
					strpos($sendBack, 'post-new.php') !== false) {
					if ('attachment' == $postType) {
						$sendBack = admin_url('upload.php');
					}
					else {
						$sendBack = admin_url('edit.php');
						if (!empty($postType)) {
							$sendBack = add_query_arg('post_type', $postType, $sendBack);
						}
					}
				}
				else {
					$sendBack = remove_query_arg(array('trashed', 'untrashed', 'deleted', 'cloned', 'ids'), $sendBack);
				}
				// Redirect to the post list screen
				wp_redirect(add_query_arg(array('cloned' => 1, 'ids' => $post->ID), $sendBack));
			}
			else {
				// Redirect to the edit screen for the new draft post
				wp_redirect(add_query_arg(array('cloned' => 1, 'ids' => $post->ID), admin_url('post.php?action=edit&post='.$newId)));
			}
			exit;

		}
		else {
			/* translators: ID of original. */
			wp_die( sprintf( wp_kses_post( __('Copy creation failed, could not find original: %d','popup-builder') ), esc_html( $id ) ) );
		}
	}

	/**
	 * Create a duplicate from a post
	 */
	public function popupCreateDuplicate($post, $status = '', $parent_id = '')
	{
		$newPostStatus = (empty($status))? $post->post_status: $status;

		if ($post->post_type != 'attachment') {
			$title = $post->post_title;
			if ($title == '') {
				// empty title
				$title = __('(no title) (clone)', 'popup-builder');
			}
			else {				
				$title .= ' ' . __('(clone)', 'popup-builder');
			}

			if ('publish' == $newPostStatus || 'future' == $newPostStatus) {
				// check if the user has the right capability
				if (is_post_type_hierarchical($post->post_type)) {
					if (!current_user_can('publish_pages')) {
						$newPostStatus = 'pending';
					}
				}
				else {
					if (!current_user_can('publish_posts')) {
						$newPostStatus = 'pending';
					}
				}
			}
		}

		$newPostAuthor = wp_get_current_user();
		$newPostAuthorId = $newPostAuthor->ID;
		// check if the user has the right capability
		if (is_post_type_hierarchical($post->post_type)) {
			if (current_user_can('edit_others_pages')) {
				$newPostAuthorId = $post->post_author;
			}
		}
		else {
			if (current_user_can('edit_others_posts')) {
				$newPostAuthorId = $post->post_author;
			}
		}

		$newPost = array(
			'menu_order'            => $post->menu_order,
			'comment_status'        => $post->comment_status,
			'ping_status'           => $post->ping_status,
			'post_author'           => $newPostAuthorId,
			'post_content'          => $post->post_content,
			'post_content_filtered' => $post->post_content_filtered,
			'post_excerpt'          => $post->post_excerpt,
			'post_mime_type'        => $post->post_mime_type,
			'post_parent'           => $newPostParent = empty($parent_id)? $post->post_parent : $parent_id,
			'post_password'         => $post->post_password,
			'post_status'           => $newPostStatus,
			'post_title'            => $title,
			'post_type'             => $post->post_type,
		);

		$newPost['post_date'] = $newPostDate = $post->post_date;
		$newPost['post_date_gmt'] = get_gmt_from_date($newPostDate);
		$newPostId = wp_insert_post(wp_slash($newPost));

		// If the copy is published or scheduled, we have to set a proper slug.
		if ($newPostStatus == 'publish' || $newPostStatus == 'future') {
			$postName = $post->post_name;
			$postName = wp_unique_post_slug($postName, $newPostId, $newPostStatus, $post->post_type, $newPostParent);

			$newPost = array();
			$newPost['ID'] = $newPostId;
			$newPost['post_name'] = $postName;

			// Update the post into the database
			wp_update_post(wp_slash($newPost));
		}

		// If you have written a plugin which uses non-WP database tables to save
		// information about a post you can hook this action to dupe that data.
		if ($post->post_type == 'page' || is_post_type_hierarchical($post->post_type)) {
			do_action('dp_duplicate_page', $newPostId, $post, $status);
		}
		else {
			do_action('sgpb_duplicate_post', $newPostId, $post, $status);
		}

		delete_post_meta($newPostId, '_sgpb_original');
		add_post_meta($newPostId, '_sgpb_original', $post->ID);

		return $newPostId;
	}

	/**
	 * Copy the meta information of a post to another post
	 */
	public function popupCopyPostMetaInfo($newId, $post)
	{
		$postMetaKeys = get_post_custom_keys($post->ID);
		$metaBlacklist = '';

		if (empty($postMetaKeys) || !is_array($postMetaKeys)) {
			return;
		}
		$metaBlacklist = explode(',', $metaBlacklist);
		$metaBlacklist = array_filter($metaBlacklist);
		$metaBlacklist = array_map('trim', $metaBlacklist);
		$metaBlacklist[] = '_edit_lock';
		$metaBlacklist[] = '_edit_last';
		$metaBlacklist[] = '_wp_page_template';
		$metaBlacklist[] = '_thumbnail_id';

		$metaBlacklist = apply_filters('duplicate_post_blacklist_filter' , $metaBlacklist);

		$metaBlacklistString = '('.implode(')|(',$metaBlacklist).')';
		$metaKeys = array();

		if (strpos($metaBlacklistString, '*') !== false) {
			$metaBlacklistString = str_replace(array('*'), array('[a-zA-Z0-9_]*'), $metaBlacklistString);

			foreach ($postMetaKeys as $metaKey) {
				if (!preg_match('#^'.$metaBlacklistString.'$#', $metaKey)) {
					$metaKeys[] = $metaKey;
				}
			}
		}
		else {
			$metaKeys = array_diff($postMetaKeys, $metaBlacklist);
		}

		$metaKeys = apply_filters('duplicate_post_meta_keys_filter', $metaKeys);

		foreach ($metaKeys as $metaKey) {
			$metaValues = get_post_custom_values($metaKey, $post->ID);
			foreach ($metaValues as $metaValue) {
				$metaValue = maybe_unserialize($metaValue);
				if (is_array($metaValue)) {
					$metaValue['sgpb-post-id'] = $newId;
				}
				add_post_meta($newId, $metaKey, $this->popupWpSlash($metaValue));
			}
		}
	}

	public function popupAddSlashesDeep($value)
	{
		if (function_exists('map_deep')) {
			return map_deep($value, array($this, 'popupAddSlashesToStringsOnly'));
		}
		else {
			return wp_slash($value);
		}
	}

	public function popupAddSlashesToStringsOnly($value)
	{
		return is_string($value) ? addslashes($value) : $value;
	}

	public function popupWpSlash($value)
	{
		return $this->popupAddSlashesDeep($value);
	}

	public function removePostPermalink($args)
	{
		global $post_type;

		if ($post_type == SG_POPUP_POST_TYPE && is_admin()) {
			// hide permalink for popupbuilder post type
			return '';
		}

		return $args;
	}

	// remove link ( e.g.: (View post) ), from popup updated/published message
	public function popupPublishedMessage($messages)
	{
		global $post_type;

		if ($post_type == SG_POPUP_POST_TYPE) {
			// post(popup) updated
			if (isset($messages['post'][1])) {
				$messages['post'][1] = __('Popup updated.', 'popup-builder');
			}
			// post(popup) published
			if (isset($messages['post'][6])) {
				$messages['post'][6] = __('Popup published.', 'popup-builder');
			}
		}
		$messages = apply_filters('sgpbPostUpdateMessage', $messages);

		return $messages;
	}
	private function subscriberFields() {
		return  array('id', 'firstName', 'lastName', 'email', 'cDate', 'subscriptionType');
	}
	private function encrypt_data($data, $secret_key) {

		if( !AdminHelper::getOption('sgpb-disable-enctyption-data') )
		{
			// Combine the IV and encrypted data (IV is needed for decryption)
			$sgpb_mahoa_secret_key = base64_encode($secret_key);		
			$sgpb_mahoa_data = base64_encode( wp_json_encode( $data ) );
			
			// Combine the IV and encrypted data (IV is needed for decryption) Return result 
			$sgpb_encripted = $sgpb_mahoa_secret_key.$sgpb_mahoa_data;
			return $sgpb_encripted;
		}
		return $data;
	}
	public function getSubscribersCsvFile()
	{
		global $wpdb;
		$allowToAction = AdminHelper::userCanAccessTo();
		if (!$allowToAction) {

			wp_redirect(get_home_url());
			exit();
		}
		$fields = $this->subscriberFields();
		$query = AdminHelper::subscribersRelatedQuery();
		if (isset($_GET['orderby']) && !empty($_GET['orderby'])) {
			$orderBy = sanitize_text_field( wp_unslash( $_GET['orderby'] ) );
			if (!in_array($orderBy, $fields)){
				wp_redirect(get_home_url());
				exit();
			}
			if (isset($_GET['order']) && !empty($_GET['order'])) {
				$order = array('ASC', 'DESC');
				if (!in_array(sanitize_text_field($_GET['order']), $order)){
					wp_redirect(get_home_url());
					exit();
				}
				$query .= ' ORDER BY '.$orderBy.' '.sanitize_text_field( wp_unslash( $_GET['order'] ) );
			}
		}
		$content = '';
		$rows = array('first name', 'last name', 'email', 'date', 'popup');
		foreach ($rows as $value) {
			$content .= $value;
			if ($value != 'popup') {
				$content .= ',';
			}
		}
		$content .= "\n";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- No applicable variables for this query.
		$subscribers = $wpdb->get_results($query, ARRAY_A);

		$subscribers = apply_filters('sgpbSubscribersCsv', $subscribers);

		foreach($subscribers as $values) {
			foreach ($values as $key => $value) {
				$content .= $value;
				if ($key != 'subscriptionTitle') {
					$content .= ',';
				}
			}
			$content .= "\n";
		}

		$content = apply_filters('sgpbSubscribersContent', $content);
		
		//Encrypt sensitive data before saving it to the CSV.
		// This should be a strong secret key
		$secret_key = get_option('sgpb-secret-code') ? get_option('sgpb-secret-code') : rtrim( base64_encode( get_option('admin_email')) , '=' ); 
		$content = $this->encrypt_data($content, $secret_key);
		
		header('Pragma: public');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Cache-Control: private', false);
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=subscribersList.csv;');
		header('Content-Transfer-Encoding: binary');
		echo wp_kses($content, AdminHelper::allowed_html_tags());
	}

	public function getSystemInfoFile()
	{
		$allowToAction = AdminHelper::userCanAccessTo();
		if (!$allowToAction) {
			return false;
		}

		$content = AdminHelper::getSystemInfoText();

		header('Pragma: public');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Cache-Control: private', false);
		header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename=popupBuilderSystemInfo.txt;');
		header('Content-Transfer-Encoding: binary');

		echo wp_kses($content, AdminHelper::allowed_html_tags());
	}

	public function saveSettings()
	{
		$allowToAction = AdminHelper::userCanAccessTo();
		$nonce = isset($_POST['sgpb_saveSettings_nonce']) ? sanitize_text_field( wp_unslash( $_POST['sgpb_saveSettings_nonce'] ) ) : '';
		if (!$allowToAction || !wp_verify_nonce($nonce, 'sgpbSaveSettings')) {
			wp_redirect(get_home_url());
			exit();
		}

		$deleteData = 0;
		$enableDebugMode = 0;
		$disableAnalytics = 0;
		$disableCustomJs = 0;
		$disableEnctyptionData = 0;
		$secret_keycode = '';
		if (isset($_POST['sgpb-dont-delete-data'])) {
			$deleteData = 1;
		}
		if (isset($_POST['sgpb-enable-debug-mode'])) {
			$enableDebugMode = 1;
		}
		if (isset($_POST['sgpb-disable-custom-js'])) {
			$disableCustomJs = 1;
		}
		if (isset($_POST['sgpb-disable-enctyption-data'])) {
			$disableEnctyptionData = 1;
		}
		if (isset($_POST['sgpb-disable-analytics-general'])) {
			$disableAnalytics = 1;
		}
		if (!empty($_POST['sgpb-user-roles'])){
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$userRoles = wp_unslash( $_POST['sgpb-user-roles'] );
			array_walk_recursive($userRoles, function(&$item){
				$item = sanitize_text_field($item);
			});
			update_option('sgpb-user-roles', $userRoles);
		}

		if (!empty($_POST['sgpb-secret-code'])){
			$secret_keycode = sanitize_text_field( wp_unslash( $_POST['sgpb-secret-code'] ) );
		}
		update_option('sgpb-dont-delete-data', $deleteData);
		update_option('sgpb-enable-debug-mode', $enableDebugMode);
		update_option('sgpb-disable-analytics-general', $disableAnalytics);
		update_option('sgpb-disable-custom-js', $disableCustomJs);
		update_option('sgpb-disable-enctyption-data', $disableEnctyptionData);
		update_option('sgpb-secret-code', $secret_keycode);
		AdminHelper::filterUserCapabilitiesForTheUserRoles('save');

		wp_redirect(admin_url().'edit.php?post_type='.SG_POPUP_POST_TYPE.'&page='.SG_POPUP_SETTINGS_PAGE);
	}

	/*
	 * this method will add a filter to exclude the current post from popup list
	 * which have a post_type=popupbuilder and it is on post edit page
	 * */
	public function postExcludeFromPopupsList(){
		global $pagenow;
		if ( isset($pagenow) && $pagenow === 'post.php') {
			if (get_post_type() === SG_POPUP_POST_TYPE){
				add_filter('sgpb_exclude_from_popups_list', function($excludedPopups) {
					array_push($excludedPopups, get_the_ID());
					return $excludedPopups;
				});
			}
		}
	}
	
	/**
	 * Modifies the popup counts by excluding specific popups as defined in the exclusion list.
	 *
	 * This function adjusts the popup counts by subtracting inactive popups identified
	 * in the excludePopupsQueryString. Primarily intended to filter out specific popups from the counts.
	 *
	 * @param object $counts The original counts of popups.
	 * @param string $type The type of post, expected to match SG_POPUP_POST_TYPE.
	 * @param mixed $perm Permissions or other data for filtering (unused in this function).
	 * @return object Modified counts with specified popups excluded.
	 */
	public function sgpbExcludePopupsToShowCounter( $counts, $type, $perm )
	{
		if( $type == SG_POPUP_POST_TYPE)
		{
			// Retrieve excluded popups from query string
			$excludePopupsQueryString = SGPopup::$num_excluded_popups;
			$excludePopups = [];	
			// Populate the excludePopups array with counts from query string			
			if( isset( $excludePopupsQueryString ) && count( $excludePopupsQueryString ) > 0 )
			{
				foreach( $excludePopupsQueryString as $sgpb_popup_key => $sgpb_popup_value)
				{
					if( isset( $excludePopups[$sgpb_popup_value] ) )
					{
						$excludePopups[$sgpb_popup_value] += 1;
					}
					else
					{
						$excludePopups[$sgpb_popup_value] = 1;
					}	
				}
			}
			 // Adjust original counts based on excludePopups	
			foreach($counts as $key => $value) {
			 
			  	if( isset( $excludePopups[$key] ) )
			  	{
			  		if( $key == 'trash')			  			
			  			$counts->$key = (int)$counts->$key - $excludePopups[$key];	
			  	}
			}
		}
			
		return $counts;		
	}
	/**
	 * Back up the popup options for a given post or multiple posts before they are moved to the trash.
	 *
	 * This function checks if multiple posts are being trashed. If so, it iterates over each post ID,
	 * retrieves the associated popup options using the `SGPopup::getPopupOptionsById` method, 
	 * and updates the post meta for each post with the key 'sg_popup_options_preview'. 
	 * If only a single post ID is provided, it performs the same backup operation for that post.
	 *
	 * @param int|string $sgpb_post_id The ID of the post whose popup options should be backed up.
	 *                                  Can be a single post ID or an empty string for batch processing.
	 */
	public function sgpb_backupPopupOptionsBeforeTrash( $sgpb_post_id = '' ) {
	    // Verify if is trashing multiple posts
	    if ( isset( $_GET['post'] ) && is_array( $_GET['post'] ) ) {
	        foreach ( $_GET['post'] as $sgpb_post_id ) {
	            $popupOptionsData = SGPopup::getPopupOptionsById( $sgpb_post_id, '');
				update_post_meta($sgpb_post_id, 'sg_popup_options_preview', $popupOptionsData);
	        }
	    } else {
	        $popupOptionsData = SGPopup::getPopupOptionsById( $sgpb_post_id, '');
			update_post_meta($sgpb_post_id, 'sg_popup_options_preview', $popupOptionsData);
	    }
	}

	public function sgpb_setCustomUploadSubscribersPathImport( $dir )
    {
        remove_filter('upload_dir', [$this, 'sgpb_setCustomUploadSubscribersPathImport']);

        $dir = wp_upload_dir();

        $path = $dir['basedir'].DIRECTORY_SEPARATOR.'subscribersimportsgpb' ;
        $url = $dir['baseurl'].'/'.'subscribersimportsgpb';

        add_filter('upload_dir', [$this, 'sgpb_setCustomUploadSubscribersPathImport']);

        if (!is_dir($path)) {
            wp_mkdir_p($path);
        }

        if (!file_exists($path . '/.htaccess')) {
            file_put_contents($path . '/.htaccess', 'deny from all');
        }

        if (!file_exists($path . '/index.html')) {
            global $wp_filesystem;
			if ( ! function_exists( 'WP_Filesystem' ) ) {
			    require_once ABSPATH . 'wp-admin/includes/file.php';
			}

			WP_Filesystem();

			if ( $wp_filesystem ) {
			    $wp_filesystem->touch( $path . '/index.html' );
			}
        }

        return array(
            'path'   => $path,
            'url'    => $url,
            'subdir' => '/subscribersimportsgpb',
        ) + $dir;
    
    }
    public function sgpb_setCustomNameUploadFilter( $file )
    {
	    $info = pathinfo($file['name']);
	    $ext  = empty($info['extension']) ? '' : '.' . $info['extension'];
	    $name = basename($file['name'], $ext);
	    $file['name'] = 'sgpb_'. $name . $ext;
		return $file; 
    }

}
