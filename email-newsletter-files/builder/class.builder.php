<?php

class Email_Newsletter_Builder  {

	var $theme = '';
	var $ID = '';
	var $settings = array();

	function __construct() {
		global $email_newsletter;
		if(isset($email_newsletter->settings) && $email_newsletter->settings) {
			add_action( 'plugins_loaded', array( &$this, 'register_stuff'), 9 );
			add_action( 'plugins_loaded', array( &$this, 'plugins_loaded_early'), 10 );
			add_action( 'plugins_loaded', array( &$this, 'plugins_loaded'), 999 );
			add_action( 'wp_ajax_builder_do_shortcodes', array( &$this, 'ajax_do_shortcodes' ) );
		}
	}
	function register_stuff() {
		global $builder_id, $email_newsletter;

		//Set up builder id as global
		if(isset($_REQUEST['newsletter_id'])) {
			$current_user = wp_get_current_user();

			if(is_numeric($_REQUEST['newsletter_id'])){
				$builder_id = $_REQUEST['newsletter_id'];
				delete_transient('builder_email_id_'.$current_user->ID);
				set_transient('builder_email_id_'.$current_user->ID, $builder_id);
			}
			else
				die(__('Etwas stimmt nicht, wir können nicht feststellen, was Du versuchst zu tun.','email-newsletter'));
		}

		if(!$builder_id) {
			$builder_id = $this->get_builder_email_id();
		}

		$customizer_theme = $this->get_customizer_theme();
		if($customizer_theme) {
			$builder_theme = $this->get_builder_theme();
			if($builder_id && $customizer_theme == $builder_theme) {
				//fix customizer capabilities users without possibility to use customizer
				if(!current_user_can( 'edit_theme_options' )) {
					add_filter('user_has_cap', array( &$this, 'fix_capabilities'), 999, 1);
				}

				$email_newsletter->register_enewsletter_themes();
				
				add_filter( 'allowed_themes', array( &$this, 'allow_enewsletter_themes'));		

				add_filter( 'template', array( &$this, 'inject_builder_template'), 999 );
				add_filter( 'stylesheet', array( &$this, 'inject_builder_stylesheet' ), 999 );
				add_filter( 'customize_loaded_components', array( &$this, 'customizer_remove_panels') );

				//fix for known compatibility problems
				remove_action( 'init', 'wp_widgets_init', 1 );

				include('compatibility.php');
			}
		}

		if(isset($_REQUEST['customize_changeset_uuid']) && isset($_REQUEST['action']) && $_REQUEST['customize_changeset_uuid'] && defined('DOING_AJAX') && $_REQUEST['action'] == 'builder_do_shortcodes') {
			//fix customizer capabilities users without possibility to use customizer
			if(!current_user_can( 'edit_theme_options' )) {
				add_filter('user_has_cap', array( &$this, 'fix_capabilities'), 999, 1);
			}			
		}
	}
    function allow_enewsletter_themes($themes) {
        $builder_theme = $this->get_builder_theme();

        $themes[$builder_theme] = true;

        return $themes;
    }

	function plugins_loaded_early() {
		global $builder_id, $wp_customize;

		$current_user = wp_get_current_user();

		//lets handle newsletter action
		if ( isset( $_REQUEST['newsletter_builder_action'] ) ) {
			$mu_cap = (function_exists('is_multisite' && is_multisite()) ? 'manage_network_options' : 'manage_options');

			switch( $_REQUEST[ 'newsletter_builder_action' ] ) {
				case "create_newsletter":
					if(!(current_user_can('create_newsletter') || current_user_can($mu_cap)))
						wp_die('You do not have permission to do that');

					$builder_id = false;
					$builder_id = $this->create_newsletter(array('template' => $this->get_builder_theme()));

					$return = (isset($_REQUEST['return'])) ? $_GET['return'] : false;
					wp_redirect( $this->generate_builder_link($builder_id, false, $return) );
					exit();
				break;
				case "edit_newsletter":
					if(!(current_user_can('save_newsletter') || current_user_can($mu_cap)) && isset($_REQUEST['newsletter_id']))
						wp_die('You do not have permission to do that');

					$template = (isset($_REQUEST['template'])) ? $_REQUEST['template'] : false;
					$return = (isset($_REQUEST['return'])) ? $_GET['return'] : false;
					wp_redirect( $this->generate_builder_link($builder_id, $template, $return) );
					exit();
				break;
			}
		}
	}
	function plugins_loaded() {
		global $builder_id, $email_newsletter, $wp_customize;

		if(isset($wp_customize)) {
			$customizer_theme = $this->get_customizer_theme();
			$builder_theme = $this->get_builder_theme();
			if($builder_id && $customizer_theme == $builder_theme) {
				//fix for known compatibility problems
				add_action( 'init', array( &$this, 'cleanup_customizer'), 1 );

				add_action( 'setup_theme' , array( &$this, 'setup_builder_header_footer' ), 999 );
				add_filter( 'wp_default_editor', array( &$this, 'force_default_editor' ) );
				add_filter( 'user_can_richedit', array( &$this, 'force_richedit' ) );

				add_action( 'admin_head', array( &$this, 'prepare_tinymce' ), 999 );

				add_action( 'template_redirect', array( &$this, 'enable_customizer') );
			}
		}
	}
	function cleanup_customizer() {
		global $wp_customize;

		remove_all_actions('customize_controls_enqueue_scripts');
		add_action( 'customize_controls_enqueue_scripts', array( $wp_customize, 'enqueue_control_scripts' ) );

		remove_all_actions('customize_register');
		add_action('customize_register', array( $wp_customize, 'register_controls' ) );
		add_action('customize_register', array( $wp_customize, 'register_dynamic_settings' ), 11 );

		add_action( 'customize_register', array( &$this, 'init_newsletter_builder'),9999 );	

		//Lets get rid of all media buttons
		if(apply_filters('email_newsletter_remove_media_buttons', true)) {
			remove_all_actions('media_buttons');
			add_action('media_buttons', 'media_buttons');
		}
		else {
			remove_action('media_buttons', 'new_im_media_buttons',11);
		}

		remove_action('init', 'new_im_tinymce_addbuttons');	
	}
	function fix_capabilities($allcaps) {
		$allcaps['edit_theme_options'] = true;

		return $allcaps;
	}
	function get_customizer_theme() {
		global $wp_customize;

		if(isset($_REQUEST['theme']))
			return $_REQUEST['theme'];
		elseif(isset($_REQUEST['customize_theme']))
			return $_REQUEST['customize_theme'];
		elseif(isset($wp_customize))
			return $wp_customize->get_stylesheet();
		else
			false;
	}
	function generate_builder_link($id=false, $theme=false, $return_url=NULL, $url=false) {
		if(is_numeric($id)) {
			$theme = $this->get_builder_theme($id, $theme, true);
			$final = 'customize.php?wp_customize=on&theme='.$theme.'&newsletter_id='.$id;
			if(empty($return_url))
				$final .= '&return='.urlencode('admin.php?page=newsletters');
			else if($return_url != false)
				$final .= '&return='.urlencode($return_url);

			if($url)
				$final .= '&url='.$url;

			return admin_url($final);
		}
		else
			return '';
	}
	function setup_builder_header_footer() {
		add_action( 'customize_controls_print_scripts', array( &$this, 'customize_controls_print_scripts') );
		add_action( 'customize_controls_print_footer_scripts', array( &$this, 'customize_controls_print_footer_scripts'), 20 );

		add_filter( 'email_newsletter_make_email_footer', array( &$this, 'filter_email_footer' ), 10, 2 );
	}
	function filter_email_footer($current_content, $newsletter_id) {
		global $wp_customize;

		ob_start();
			do_action( 'wp_print_footer_scripts' );
			$wp_customize->customize_preview_settings();
			$this->email_builder_customize_preview();
			$captured = ob_get_contents();
		ob_end_clean();

		return $current_content.$captured;
	}
	function prepare_tinymce() {
		global $enewsletter_tinymce;
		ob_start();

		$tinymce_options = array(
			'teeny' => false,
			'media_buttons' => true,
			'quicktags' => false,
			'textarea_rows' => 25,
			'drag_drop_upload' => true,
			'tinymce' => array(
				'wp_skip_init' => false,
				'theme_advanced_disable' => '',
				'theme_advanced_buttons1_add' => 'code',
				'theme_advanced_resize_horizontal' => true,
				'add_unload_trigger' => false,
				'resize' => 'both'
			),
			'editor_css' => '<style type="text/css">body { background: #000; }</style>',
		);
		$email_content = $this->get_builder_email_content('');
		wp_editor($email_content, 'content_tinymce', $tinymce_options);

		$enewsletter_tinymce = ob_get_clean();
	}
	function customize_controls_print_scripts() {
		do_action('admin_enqueue_scripts');
		do_action('admin_print_scripts');
		do_action('admin_head');
		do_action('email_newsletter_template_builder_print_scripts');
	}
	function customize_controls_print_footer_scripts() {
		global $email_newsletter, $wp_version;

		//this makes it load JS for TinyMce correctly for all
		if(!did_action('admin_print_footer_scripts'))
			do_action( 'admin_print_footer_scripts' );

		// Collect other theme info so we can allow changes
		$themes = wp_get_themes();

		foreach($themes as $key => $theme) {
			if($theme->theme_root != $email_newsletter->template_directory && $theme->theme_root != $email_newsletter->template_custom_directory )
				unset($themes[$key]);
		}

		?>
		<script type="text/javascript">
			_wpCustomizeControlsL10n.save = _wpCustomizeControlsL10n.publish = _wpCustomizeControlsL10n.published = "<?php _e('Newsletter speichern','email-newsletter'); ?>";
			var activate_theme = "<?php _e('Aktiviere Theme','email-newsletter'); ?>";
			var current_theme = "<?php echo $this->get_customizer_theme(); ?>";
			var wp_version = <?php echo floatval($wp_version); ?>;

			email_templates = [
				<?php foreach($themes as $theme): ?>
				{	"name": <?php echo json_encode($theme->get('Name')); ?>,
					"description": <?php echo json_encode($theme->get('Description')); ?>,
					"screenshot": <?php $template = $email_newsletter->get_theme_dir_url($theme, $theme->stylesheet); echo json_encode($template['url'].'screenshot.jpg'); ?>,
					"stylesheet": <?php echo json_encode($theme->stylesheet); ?>,
				},
				<?php endforeach; ?>
			];
			email_templates.sort(function(a, b){
				var aName = a.name.toLowerCase();
				var bName = b.name.toLowerCase();
				return ((aName < bName) ? -1 : ((aName > bName) ? 1 : 0));
			});

			var current = jQuery('#customize-info .accordion-section-content');
			var copy = jQuery('<div class="accordion-section-content">');

			if(current.length > 0) {
				current.html('');
			}
			else {
				jQuery('#customize-info').append(copy.clone());
				current = jQuery('#customize-info .accordion-section-content');
			}
			
			jQuery.each(email_templates, function(i,e) {
				var clone = copy.clone();

				if( e.stylesheet != current_theme ) {
					clone.append('<h3>'+e.name+"</h3>");
					clone.append('<input type="button" value="'+activate_theme+'" id="activate_theme" class="button button-primary save">');
					clone.append('<img src="" class="theme-screenshot" />');
					clone.append('<div class="theme-description"></div>');

					clone.find('img.theme-screenshot').attr('src',e.screenshot);
					clone.find('.theme-description').text(e.description);
					clone.data('theme',e);

					jQuery('#customize-info').append(clone);
				} else {
					// Use this opportunity to change the theme preview area
					jQuery('#customize-info .preview-notice').html("<strong class='theme-name panel-title'>"+e.name+"</strong><?php _e('Template wählen','email-newsletter'); ?>");

					current.addClass('current_theme');
					current.append('<h3>'+e.name+"</h3>");
					current.append('<img src="" class="theme-screenshot" />');
					current.append('<div class="theme-description"></div>');

					current.find('img.theme-screenshot').attr('src',e.screenshot);
					current.find('.theme-description').text(e.description);
					current.data('theme',e);

					jQuery('#customize-info .accordion-section-title').after(current);
				}

			});

			jQuery('#customize-info').on('click', '.accordion-section-title', function() {
				var new_theme;
				var parent = jQuery(this).parent();

				if(wp_version >= 4.3) {
					if(parent.hasClass('open'))
						jQuery(this).parent().removeClass('open');
					else
						jQuery(this).parent().addClass('open');
				}

				jQuery('#customize-info #activate_theme').on('click', function(event) {
					data = jQuery(this).parent().data('theme');
					new_theme = data.stylesheet;

					if( typeof new_theme != 'undefined') {
						event.preventDefault();

						// Use string replace to redirect the url
						jQuery('[data-customize-setting-link="template"]').val(new_theme);
						jQuery('[data-customize-setting-link="template"]').trigger('change');

						//make sure it is set
						var set_val = setInterval(function () {
							if(jQuery('[data-customize-setting-link="template"]').val() == new_theme) {
					        	jQuery("#save").trigger("click");
					        	clearInterval(set_val);
					        }
					    },100);
					}
				});

				wp.customize.on( 'saved', function() {
					var new_theme = jQuery('[data-customize-setting-link="template"]').val();
					if(current_theme != new_theme)
						window.location.href = window.location.href.replace('theme='+current_theme,'theme='+new_theme);
				});
			});

			window.onbeforeunload = function() {
				if(!jQuery("#save").is(":disabled"))
					return "<?php _e('Du hast nicht gespeicherte Daten in diesem Newsletter.','email-newsletter'); ?>";
			};
		</script>

		<style type="text/css">
			body {
				background: #fff;
			}
			#customize-notifications-area, #publish-settings {
				display: none !important;
			}
			#customize-save-button-wrapper .save.has-next-sibling {
				border-radius: 3px !important;
			}
			#content_tinymce_ifr {
				min-height: 200px;
			}
			#customize-control-email_content {
				width:auto;
			}
			.wp-full-overlay-sidebar {
				min-width:550px;
			}
			.wp-full-overlay.collapsed .wp-full-overlay-sidebar {
				margin-left: -550px;
			}
			.wp-full-overlay.expanded {
				margin-left: 550px;
			}
			#customize-info .accordion-section-content {
				text-align: center;
				position: relative;
				min-height: 360px;
			}
			.theme-screenshot {
				min-height:258px;
			}
			.wp-full-overlay {
				z-index: 15000;
			}
			#TB_overlay, #TB_window {
				z-index: 16000!important;
			}
			.open #activate_theme {
				display: inline-block;
				position: absolute;
				top: 280px;
				width:120px;
				left:50%;
				margin-left: -50px;
			}
			.current_theme {
				border-bottom: 1px solid #fff;
				box-shadow: inset 0 -1px 0 0 #dfdfdf;
			}
			#accordion-panel-nav_menus, .customize-panel-description {
				display: none !important;
			}
			.accordion-section-title {
				cursor: pointer !important;
			}
			#customize-controls .customize-info .accordion-section-title:after {
				display: block;
			}
			.customize-help-toggle {
				display: none;
			}
			#customize-footer-actions {
				min-width: 550px;
			}
		</style>
		<?php
	}

	function force_default_editor() {
    	return 'tinymce';
	}

	function force_richedit() {
    	return true;
	}

	function get_builder_email_id() {
		global $email_newsletter;

		$current_user = wp_get_current_user();

		return get_transient('builder_email_id_'.$current_user->ID);
	}
	function get_builder_theme($newsletter_id = false, $theme = false, $check = false) {
		global $builder_id, $email_newsletter;

		if( !$theme ) {
			$newsletter_id = $newsletter_id ? $newsletter_id : $builder_id;
			if($newsletter_id) {
				$data = $email_newsletter->get_newsletter_data($newsletter_id);
			}
			if(isset($data) && isset($data['template']))
				$theme = $data['template'];
			else {
				$arg['limit'] = 'LIMIT 1';
				$arg['orderby'] = 'create_date';
				$arg['order'] = 'desc';
				$latest_newsletter = $email_newsletter->get_newsletters($arg, 0, 0);

				$theme = (isset($latest_newsletter[0]['template']) && !empty($latest_newsletter[0]['template'])) ? $latest_newsletter[0]['template'] : 'iletter';
			}
		}

		if($check) {
			$theme_data = $email_newsletter->get_selected_theme($theme, $newsletter_id);
			$theme = $theme_data['Stylesheet'];
		}

		return $theme;

	}
	function find_builder_theme($theme = '') {
		global $email_newsletter;

        if(empty($theme))
			$theme = $this->get_builder_theme();

		$theme_data = $email_newsletter->get_selected_theme($theme);

		return $theme_data;
	}
	function inject_builder_stylesheet() {
		$theme = $this->find_builder_theme();
		if ($theme)
			return $theme['Stylesheet'];
		else
			return false;
	}
	function inject_builder_template() {
		$theme = $this->find_builder_theme();
		if ($theme)
			return $theme['Template'];
		else
			return false;
	}
	function init_newsletter_builder( $instance ) {
		global $builder_id, $email_newsletter;
		$email_data = $email_newsletter->get_newsletter_data($builder_id);

		$theme = $email_newsletter->get_selected_theme($email_data['template']);
		$template_url  = $theme['url'];

		//pharse theme settings
		$possible_settings = array('BG_COLOR', 'BG_IMAGE', 'HEADER_IMAGE', 'LINK_COLOR', 'BODY_COLOR', 'ALTERNATIVE_COLOR', 'TITLE_COLOR', 'EMAIL_TITLE' );
		foreach ($possible_settings as $possible_setting)
			if(defined('BUILDER_DEFAULT_'.$possible_setting))
				$this->settings[] = $possible_setting;


		// Load our extra control classes
		require_once($email_newsletter->plugin_dir . 'email-newsletter-files/builder/class.tinymce-control.php');
		require_once($email_newsletter->plugin_dir . 'email-newsletter-files/builder/class.textarea-control.php');
		require_once($email_newsletter->plugin_dir . 'email-newsletter-files/builder/class.hidden-control.php');
		require_once($email_newsletter->plugin_dir . 'email-newsletter-files/builder/class.preview-control.php');

		if( in_array('BG_IMAGE', $this->settings) || in_array('HEADER_IMAGE', $this->settings)) {
			$instance->add_section( 'images', array(
				'title'          => __('Bilder','email-newsletter'),
				'priority'       => 37,
			) );

			$images = array();
			if(in_array('BG_IMAGE', $this->settings))
				$images['bg_image'] = 'Background Image';
			if(in_array('HEADER_IMAGE', $this->settings))
				$images['header_image'] = 'Header Image';

			foreach ($images as $value => $label) {
				$image = $email_newsletter->get_default_builder_var($value);
				if(!empty($image)) {
					$image2 = $email_newsletter->get_newsletter_meta($builder_id,$value);
					$image = ($image2 === false) ? $template_url.$image : '';
				}
				else
					$image = '';

				$instance->add_setting( $value, array(
					'default' => $image,
					'type' => 'newsletter_save'
				) );
				$instance->add_control( new WP_Customize_Image_Control( $instance, $value, array(
					'label'   => __($label,'email-newsletter'),
					'section' => 'images',
				)) );
			}
		}

		if( in_array('BG_COLOR', $this->settings) || in_array('LINK_COLOR', $this->settings) || in_array('BODY_COLOR', $this->settings) || in_array('ALTERNATIVE_COLOR' , $this->settings)|| in_array('TITLE_COLOR' , $this->settings) ) {

			$instance->add_section( 'builder_colors', array(
				'title' => __('Farben','email-newsletter'),
				'priority' => 39
			) );

			if( in_array('BG_COLOR', $this->settings) ) {
				$instance->add_setting( 'bg_color', array(
					'default' => $email_newsletter->get_default_builder_var('bg_color'),
					'type' => 'newsletter_save'
				) );
				$instance->add_control( new WP_Customize_Color_Control( $instance, 'bg_color', array(
					'label'        => __('Hintergrundfarbe', 'email-newsletter' ),
					'section'    => 'builder_colors',
					'settings'   => 'bg_color',
				) ) );
			}

			if( in_array('BODY_COLOR', $this->settings) ) {
				$instance->add_setting( 'body_color', array(
					'default' => $email_newsletter->get_default_builder_var('body_color'),
					'type' => 'newsletter_save'
				) );
				$instance->add_control( new WP_Customize_Color_Control( $instance, 'body_color', array(
					'label'        => __( 'Content Textfarbe', 'email-newsletter' ),
					'section'    => 'builder_colors',
					'settings'   => 'body_color',
				) ) );
			}

			if( in_array('ALTERNATIVE_COLOR', $this->settings) ) {
				$instance->add_setting( 'alternative_color', array(
					'default' => $email_newsletter->get_default_builder_var('alternative_color'),
					'type' => 'newsletter_save'
				) );
				$instance->add_control( new WP_Customize_Color_Control( $instance, 'alternative_color', array(
					'label'        => __( 'Alternative Textfarbe', 'email-newsletter' ),
					'section'    => 'builder_colors',
					'settings'   => 'alternative_color',
				) ) );
			}


			if( in_array('TITLE_COLOR', $this->settings) ) {
				$instance->add_setting( 'title_color', array(
					'default' => $email_newsletter->get_default_builder_var('title_color'),
					'type' => 'newsletter_save'
				) );
				$instance->add_control( new WP_Customize_Color_Control( $instance, 'title_color', array(
					'label'        => __( 'Titel Textfarbe', 'email-newsletter' ),
					'section'    => 'builder_colors',
					'settings'   => 'title_color',
				) ) );
			}

			if( in_array('LINK_COLOR', $this->settings) ) {
				$instance->add_setting( 'link_color', array(
					'default' => $email_newsletter->get_default_builder_var('link_color'),
					'type' => 'newsletter_save'
				) );
				$instance->add_control( new WP_Customize_Color_Control( $instance, 'link_color', array(
					'label'        => __( 'Linkfarbe', 'email-newsletter' ),
					'section'    => 'builder_colors',
					'settings'   => 'link_color',
				) ) );
			}
		}

		if( in_array('EMAIL_TITLE',$this->settings) ) {
			$instance->add_setting( 'email_title', array(
				'default' => $email_newsletter->get_default_builder_var('email_title'),
				'type' => 'newsletter_save'
			) );
			$instance->add_control( new Builder_TextArea_Control( $instance, 'email_title', array(
				'label'   => __('Email Titel','email-newsletter'),
				'section' => 'builder_email_content',
				'type'    => 'text',
			) ) );
		}

		// Setup Sections

		$instance->add_section( 'builder_email_settings', array(
			'title'          => __('Einstellungen','email-newsletter'),
			'priority'       => 35,
		) );
		$instance->add_section( 'builder_email_content', array(
			'title'          => __('Content','email-newsletter'),
			'priority'       => 36,
		) );
		$instance->add_section( 'builder_preview', array(
			'title'          => __('Vorschau senden','email-newsletter'),
			'priority'       => 40,
		) );


		// Setup Settings
		$instance->add_setting( 'template', array(
			'default' => $instance->get_stylesheet(),
			'type' => 'newsletter_save'
		) );
		$instance->add_setting( 'subject', array(
			'default' => $email_newsletter->get_default_builder_var('email_title'),
			'type' => 'newsletter_save'
		) );
		$instance->add_setting( 'from_name', array(
			'default' => (isset($email_newsletter->settings['from_name'])) ? $email_newsletter->settings['from_name'] : '',
			'type' => 'newsletter_save'
		) );
		$instance->add_setting( 'from_email', array(
			'default' => (isset($email_newsletter->settings['from_email'])) ? $email_newsletter->settings['from_email'] : '',
			'type' => 'newsletter_save'
		) );
		$instance->add_setting( 'bounce_email', array(
			'default' => (isset($email_newsletter->settings['bounce_email'])) ? $email_newsletter->settings['bounce_email'] : '',
			'type' => 'newsletter_save'
		) );
		$instance->add_setting( 'email_content', array(
			'default' => '',
			'type' => 'newsletter_save'
		) );
		$instance->add_setting( 'email_preview', array(
			'default' => (isset($email_newsletter->settings['preview_email'])) ? $email_newsletter->settings['preview_email'] : '',
			'type' => 'newsletter_save'
		) );

		$instance->add_setting( 'branding_html', array(
			'default' => '',
			'type' => 'newsletter_save',
		) );

		$instance->add_setting( 'contact_info', array(
			'default' => '',
			'type' => 'newsletter_save',
		) );


		// Setup Controls
		$instance->add_control( new Builder_Hidden_Control( $instance, 'template', array(
			'label'   => __('Template','email-newsletter'),
			'section' => 'builder_email_settings',
			'settings'   => 'template',
		) ) );
		$instance->add_control( 'subject', array(
			'label'   => __('Email Betreff','email-newsletter'),
			'section' => 'builder_email_settings',
			'type'    => 'text',
		) );
		$instance->add_control( 'from_name', array(
			'label'   => __('Von Name','email-newsletter'),
			'section' => 'builder_email_settings',
			'type'    => 'text',
		) );
		$instance->add_control( 'from_email', array(
			'label'   => __('Von Email','email-newsletter'),
			'section' => 'builder_email_settings',
			'type'    => 'text',
		) );
		$instance->add_control( 'bounce_email', array(
			'label'   => __('Bounce Email','email-newsletter'),
			'section' => 'builder_email_settings',
			'type'    => 'text',
		) );
		$instance->add_control( new Builder_TinyMCE_Control( $instance, 'email_content', array(
			'label'   => __('Email Inhalt','email-newsletter'),
			'section' => 'builder_email_content',
			'settings'   => 'email_content',
		) ) );
		$instance->add_control( new Builder_TextArea_Control( $instance, 'branding_html', array(
			'label'   => __('Branding HTML/Text','email-newsletter'),
			'section' => 'builder_email_content',
			'settings'   => 'branding_html',
		) ) );
		$instance->add_control( new Builder_TextArea_Control( $instance, 'contact_info', array(
			'label'   => __('Kontaktinformation','email-newsletter'),
			'section' => 'builder_email_content',
			'settings'   => 'contact_info',
		) ) );
		$instance->add_control( new Builder_Preview_Control($instance, 'email_preview', array(
			'label'   => __('Vorschau an E-Mail senden (zuerst speichern)','email-newsletter'),
			'section' => 'builder_preview',
		) ) );

		$customize_values = array('template', 'subject', 'from_name', 'from_email', 'bounce_email', 'email_title', 'branding_html', 'contact_info', 'email_content', 'bg_color', 'link_color', 'body_color', 'alternative_color', 'title_color', 'bg_image', 'header_image');
		$instance_settings = array_merge($customize_values, array('email_preview'));

		foreach ($instance_settings as $setting)
			$instance->get_setting($setting)->transport='postMessage';

		// Add all the filters we need for all the settings to be retreived
		foreach ($customize_values as $value)
			add_filter( 'customize_value_'.$value, array( &$this, 'get_builder_'.$value) );

		add_action( 'customize_save', array( &$this, 'save_builder'), 10, 0 );

		//remove default sections and panels
		$instance->remove_panel( 'themes' );
		$instance->remove_section( 'colors' );
		$instance->remove_section( 'title_tagline' );
		$instance->remove_section( 'static_front_page' );
		$instance->remove_section( 'themes' );
		$instance->remove_section( 'custom_css' );		
	}
	function customizer_remove_panels() {
		return array();
	}

	//this function needs cleaning up - its encoding and decoding for no good reason
	function create_newsletter($new_values) {
		global $email_newsletter, $builder_id, $wpdb;

		$data = array();
		$default = array(
			'subject' => '',
			'content_ecoded' => '',
			'contact_info' => base64_encode($email_newsletter->settings['contact_info']),
			'from_name' => $email_newsletter->settings['from_name'],
			'from_email' => $email_newsletter->settings['from_email'],
			'bounce_email' => $email_newsletter->settings['bounce_email'],
			'sent' => 0,
			'opened' => 0,
			'bounced' => 0,
			'meta' => array('branding_html' => base64_encode($email_newsletter->settings['branding_html'])),
		);

		if(isset($new_values['template']) ) {
			$data['newsletter_template'] = $new_values['template'];
		}

		$possible_vaules = array(
			'default' => array('subject', 'from_name', 'from_email', 'bounce_email'),
			'meta' => array('email_title', 'bg_color', 'link_color', 'body_color', 'title_color', 'alternative_color', 'bg_image', 'header_image'),
			'default_encode' => array(array('content_encoded', 'email_content'), 'contact_info'),
			'meta_encode' => array('branding_html'),
		);
		foreach ($possible_vaules as $type => $values) {
			foreach ($values as $value) {
				if(is_array($value)) {
					$value_target = $value[0];
					$value = $value[1];
				}
				else
					$value_target = $value;

				if(isset($new_values[$value])) {
					if($type == 'default_encode')
						$data[$value_target] = base64_encode($new_values[$value]);
					elseif($type == 'default')
						$data[$value_target] = $new_values[$value];
					elseif($type == 'meta')
						$data['meta'][$value_target] = $new_values[$value];
					elseif($type == 'meta_encode')
						$data['meta'][$value_target] = base64_encode($new_values[$value]);
				}
			}
		}

		$data = array_merge($default,$data);

        $current_theme = $this->get_builder_theme($builder_id);

        $content        = base64_decode( str_replace( "-", "+", (isset($data['content_encoded']) ? $data['content_encoded'] : '' ) ) );
        $contact_info   = base64_decode( str_replace( "-", "+", (isset($data['contact_info']) ? $data['contact_info'] : '' ) ) );

        $fields = array(
            "template"      => $data['newsletter_template'],
            "subject"       => $data['subject'],
            "from_name"     => $data['from_name'],
            "from_email"    => $data['from_email'],
            "bounce_email"  => ( isset( $data['bounce_email'] ) ) ? $data['bounce_email'] : '',
            "content"       => $content,
            "contact_info"  => $contact_info,
        );

        $sql    = "INSERT INTO {$email_newsletter->tb_prefix}enewsletter_newsletters SET create_date = " . time() . " ";

        foreach( $fields as $key=>$val )
            $sql .= $wpdb->prepare(", `".$key."` = %s", trim( $val ));

        $result = $wpdb->query( $sql );

        if( ! $builder_id )
            $builder_id = $wpdb->insert_id;


        $meta = $data['meta'];
        $meta['branding_html'] = base64_decode( str_replace( "-", "+", (isset($meta['branding_html']) ? $meta['branding_html'] : '' ) ) );

        if($builder_id && $data['newsletter_template'] != $current_theme) {
            $exclude = array('branding_html');
            if($meta['email_title'] != BUILDER_DEFAULT_EMAIL_TITLE)
                $exclude[] = 'email_title';

            $email_newsletter->delete_newsletter_meta($builder_id, $exclude, 1 );
        }
        else
            foreach($meta as $meta_key => $meta_value) {
                $email_newsletter->update_newsletter_meta($builder_id, $meta_key, $meta_value);
            }

        do_action( 'enewsletter_newsletter_saved', $builder_id, $data, $meta);

        return $builder_id;
	}

	function save_builder() {
		global $email_newsletter, $builder_id, $wpdb;

		$data = $meta = array();

		$new_values = json_decode(stripslashes($_POST['customized']), true);

		$possible_vaules = array(
			'default' => array('template', 'subject', 'from_name', 'from_email', 'bounce_email', array('content', 'email_content'), 'contact_info'),
			'meta' => array('email_title', 'branding_html', 'bg_color', 'link_color', 'body_color', 'title_color', 'alternative_color', 'bg_image', 'header_image'),
			'default_encode' => array(),
			'meta_encode' => array(),
		);
		foreach ($possible_vaules as $type => $values) {
			foreach ($values as $value) {
				if(is_array($value)) {
					$value_target = $value[0];
					$value = $value[1];
				}
				else
					$value_target = $value;

				if(isset($new_values[$value])) {
					if($type == 'default_encode')
						$data[$value_target] = base64_encode($new_values[$value]);
					elseif($type == 'default')
						$data[$value_target] = $new_values[$value];
					elseif($type == 'meta')
						$meta[$value_target] = $new_values[$value];
					elseif($type == 'meta_encode') 
						$meta[$value_target] = base64_encode($new_values[$value]);
				}
			}
		}

        $current_theme = $this->get_builder_theme($builder_id);

        if(isset($data['template']) && $data['template'] != $current_theme) {
            $exclude = array('branding_html');
            if(isset($meta['email_title']) && $meta['email_title'] != BUILDER_DEFAULT_EMAIL_TITLE)
                $exclude[] = 'email_title';

            $email_newsletter->delete_newsletter_meta($builder_id, $exclude, 1 );
        }
        else
            foreach($meta as $meta_key => $meta_value) {
                $email_newsletter->update_newsletter_meta($builder_id, $meta_key, $meta_value);
            }

        $sql = "UPDATE {$email_newsletter->tb_prefix}enewsletter_newsletters SET ";

        $update = array();
        foreach( $data as $key=>$val )
            $update[] = $wpdb->prepare("`".$key."` = %s", trim( $val ));

        $sql .= implode(', ', $update);
        $sql .= $wpdb->prepare(" WHERE newsletter_id = %d LIMIT 1", $builder_id);

        $result = $wpdb->query( $sql );

        do_action( 'enewsletter_newsletter_saved', $builder_id, $data, $meta);
	}

	// Anything that isnt a text input has to have its own function because
	// WordPress only gives us the $default value to match in the filter
	function get_builder_template($default) {
		return $this->get_customizer_theme();
	}
	function get_builder_bg_color($default) {

		global $builder_id, $email_newsletter;

		$bg_color = $email_newsletter->get_newsletter_meta($builder_id,'bg_color');
		if(!empty($bg_color))
			return $bg_color;
		else
			return $default;
	}
	function get_builder_link_color($default) {
		global $builder_id, $email_newsletter;

		$link_color = $email_newsletter->get_newsletter_meta($builder_id,'link_color');
		if(!empty($link_color))
			return $link_color;
		else
			return $default;
	}
	function get_builder_body_color($default) {
		global $builder_id, $email_newsletter;

		$body_color = $email_newsletter->get_newsletter_meta($builder_id,'body_color');
		if(!empty($body_color))
			return $body_color;
		else
			return $default;
	}
	function get_builder_title_color($default) {
		global $builder_id, $email_newsletter;

		$title_color = $email_newsletter->get_newsletter_meta($builder_id,'title_color');
		if(!empty($title_color))
			return $title_color;
		else
			return $default;
	}
	function get_builder_alternative_color($default) {
		global $builder_id, $email_newsletter;

		$alternative_color = $email_newsletter->get_newsletter_meta($builder_id,'alternative_color');
		if(!empty($alternative_color))
			return $alternative_color;
		else
			return $default;
	}
	function get_builder_email_title($default) {
		global $builder_id, $email_newsletter;

		$email_title = $email_newsletter->get_newsletter_meta($builder_id,'email_title');
		if(isset($email_title) && $email_title !== false )
			return $email_title;
		else
			return $default;
	}
	function get_builder_email_content($default) {
		global $builder_id, $email_newsletter;

		$data = $email_newsletter->get_newsletter_data($builder_id);

		if(!empty($data['content']))
			return $data['content'];
		else
			return $default;
	}
	function get_builder_bg_image($default = 0) {
		global $builder_id, $email_newsletter;

		$bg_image = $email_newsletter->get_newsletter_meta($builder_id,'bg_image');
		if($default !== 0 && empty($bg_image))
			return $default;
		else
			return $bg_image;
	}
	function get_builder_header_image($default = 0) {
		global $builder_id, $email_newsletter;

		$header_image = $email_newsletter->get_newsletter_meta($builder_id,'header_image');
		if($default !== 0 && empty($header_image))
			return $default;
		else
			return $header_image;
	}
	function get_builder_branding_html($default) {
		global $builder_id, $email_newsletter;

		$branding_html = $email_newsletter->get_newsletter_meta($builder_id,'branding_html');
		if(!empty($branding_html))
			return $branding_html;
		else
			return $email_newsletter->settings['branding_html'];
	}
	function get_builder_contact_info($default) {
		global $builder_id, $email_newsletter;

		$data = $email_newsletter->get_newsletter_data($builder_id);
		if(isset($data['contact_info']))
			return $data['contact_info'];
		else
			return $email_newsletter->settings['contact_info'];
	}
	function get_builder_subject($default) {
		global $builder_id, $email_newsletter;

		$data = $email_newsletter->get_newsletter_data($builder_id);
		if(isset($data['subject']))
			return $data['subject'];
		else
			return $email_newsletter->settings['subject'];
	}
	function get_builder_bounce_email($default) {
		global $builder_id, $email_newsletter;

		$data = $email_newsletter->get_newsletter_data($builder_id);
		if(isset($data['bounce_email']) && is_email($data['bounce_email']))
			return $data['bounce_email'];
		else
			return isset($email_newsletter->settings['bounce_email']) ? $email_newsletter->settings['bounce_email'] : $email_newsletter->settings['from_email'];
	}
	function get_builder_from_name($default) {
		global $builder_id, $email_newsletter;

		$data = $email_newsletter->get_newsletter_data($builder_id);
		if(!empty($data['from_name']))
			return $data['from_name'];
		else
			return $email_newsletter->settings['from_name'];
	}
	function get_builder_from_email($default) {
		global $builder_id, $email_newsletter;

		$data = $email_newsletter->get_newsletter_data($builder_id);
		if(isset($data['from_email']) && is_email($data['from_email']))
			return $data['from_email'];
		else
			return $email_newsletter->settings['from_email'];
	}

	function email_builder_customize_preview() {
		$admin_url = admin_url('admin-ajax.php');
		?><script type="text/javascript">
			( function( $ ){
				<?php if( in_array('EMAIL_TITLE',$this->settings)) : ?>
					wp.customize('email_title',function( value ) {
						value.on(function(to) {
							$('[data-builder="email_title"]').html( to ? to : '' );
						});
					});
				<?php endif; ?>
				wp.customize('email_content',function( value ) {
					value.on(function(to) {
						var data = {
							action: 'builder_do_shortcodes',
							content: to
						}
						$.post('<?php echo $admin_url; ?>', data, function(response) {
							if(response != '0') {
								$('[data-builder="email_content"]').html( response );
							}
						});

					});
				});
				wp.customize('from_name',function( value ) {
					value.on(function(to) {
						$('[data-builder="from_name"]').html( to ? to : '' );
					});
				});
				wp.customize('from_email',function( value ) {
					value.on(function(to) {
						$('[data-builder="from_email"]').html( to ? to : '' );
					});
				});
				wp.customize('branding_html',function( value ) {
					value.on(function(to) {
						$('[data-builder="branding_html"]').html( to ? to : '' );
					});
				});
				wp.customize('contact_info',function( value ) {
					value.on(function(to) {
						$('[data-builder="contact_info"]').html( to ? to : '' );
					});
				});
				<?php if( in_array('BG_COLOR',$this->settings)) : ?>
					wp.customize('bg_color',function( value ) {
						value.on(function(to) {
							$('[data-builder="bg"]').css( 'background-color', to ? to : '' );
						});
					});
				<?php endif; ?>
				<?php if( in_array('LINK_COLOR',$this->settings)) : ?>
					wp.customize('link_color',function( value ) {
						value.on(function(to) {
							$('a[href]').css( 'color', to ? to : '' );
						});
					});
				<?php endif; ?>
				<?php if( in_array('BODY_COLOR',$this->settings)) : ?>
					wp.customize('body_color',function( value ) {
						value.on(function(to) {
							$('[data-builder="body_color"]').css( 'color', to ? to : '' );
						});
					});
				<?php endif; ?>
				<?php if( in_array('ALTERNATIVE_COLOR',$this->settings)) : ?>
					wp.customize('alternative_color',function( value ) {
						value.on(function(to) {
							$('[data-builder="alternative_color"]').css( 'color', to ? to : '' );
						});
					});
				<?php endif; ?>
				<?php if( in_array('TITLE_COLOR',$this->settings)) : ?>
					wp.customize('title_color',function( value ) {
						value.on(function(to) {
							$('[data-builder="title_color"]').css( 'color', to ? to : '' );
						});
					});
				<?php endif; ?>
				<?php if( in_array('BG_IMAGE',$this->settings)) : ?>
					wp.customize('bg_image',function( value ) {

						value.on(function(to) {
							$('[data-builder="bg"]').css( 'background-image', 'url(' + to + ')');
						});
					});
				<?php endif; ?>
				<?php if( in_array('HEADER_IMAGE',$this->settings)) : ?>
					wp.customize('header_image',function( value ) {

						value.on(function(to) {
							$('[data-builder="header_image"]').html( '<img src="' + to + '" />');
						});
					});
				<?php endif; ?>
			} )( jQuery )
		</script>
	<?php
	}

	public function ajax_do_shortcodes() {
		global $email_newsletter, $builder_id;

		$content = stripcslashes($_POST['content']);

		$themedata = $this->find_builder_theme();

		$content = apply_filters('email_newsletter_make_email_content', $content);
		$content = $email_newsletter->do_inline_styles('<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body><div id="email_content_customizer">'.$content.'</div></body></html>', $themedata['Style']);
		$content = str_replace( '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>', '', $content);
		$content = str_replace( '</body></html>', '', $content);

        $preview_prepare =
        array(
            'standard' => array(
                'from_name' => $this->get_builder_from_name(''),
                'from_email' => $this->get_builder_from_email(''),
                'date' => (isset($this->settings['date_format']) ? $this->settings['date_format'] : "F j, Y")
            ),
            'colors' => array(
                'link_color' => $this->get_builder_link_color($email_newsletter->get_default_builder_var('link_color')),
                'alternative_color' => $this->get_builder_link_color($email_newsletter->get_default_builder_var('alternative_color')),
                'title_color' => $this->get_builder_link_color($email_newsletter->get_default_builder_var('title_color'))
            )
        );
        $content = $email_newsletter->make_email_values($preview_prepare, $content, $builder_id);

	    echo $content;
		die();
	}

	public function enable_customizer() {
		global $wp_customize, $email_newsletter, $builder_id;

		if(empty($wp_customize) || !$wp_customize->is_preview())
			die();

		$content = $email_newsletter->make_email_body($builder_id, 1);

		$replacements = array(
			'user_name' => '{USER_NAME}',
			'first_name' => '{FIRST_NAME}',
			'last_name' => '{LAST_NAME}',
			'to_email'=> '{TO_EMAIL}'
		);
		$content = $email_newsletter->personalise_email_body( $content, 0, 0, 0, 0, 0, $changes = $replacements );

		echo $content;

		exit();
	}
}
?>
