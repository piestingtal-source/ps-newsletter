<?php
/*
Plugin Name: PS-eNewsletter
Plugin URI: https://n3rds.work/piestingtal_source/psenewsletter-wordpress-newsletter-ohne-drittanbieter/
Description: Das ultimative Newsletter Plugin für WordPress. Keine Drittanbieterdienste oder Abo-Kosten, Newsletter direkt aus dem WordPress-Dashboard managen und versenden.
Natürlich mit der großartigen Piestingtal.Source Kompatibilität.
Version: 2.8.3
Text Domain: email-newsletter
Author: Webmasterservice N3rds@Work,
Author URI: https://n3rds.work


Copyright 2018-2021 WMS N3rds@Work (https://n3rds.work), Corecode von WPMUDEV (eNewsletter)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License (Version 2 - GPLv2) as published by
the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/
require 'email-newsletter-files/external/psource-plugin-update/plugin-update-checker.php';
$MyUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	'https://n3rds.work//wp-update-server/?action=get_metadata&slug=ps-newsletter', 
	__FILE__, 
	'ps-newsletter' 
);


require_once( 'email-newsletter-files/class.functions.php' );
require_once( 'email-newsletter-files/builder/class.builder.php' );
include_once( 'email-newsletter-files/class.wp_widgets.php' );
/**
* Plugin main class
**/

class Email_Newsletter extends Email_Newsletter_functions {

    var $plugin_ver;
    var $plugin_main_file;
    var $plugin_dir;
    var $plugin_url;
    var $template_directory;
    var $template_custom_directory;
    var $settings;
    var $tb_prefix;
    var $cron_send_name;
    var $cron_bounce_name;
    var $plugin_templates = array();
    var $capabilities = array();
    var $loaded_theme_options = '';

    var $debug;

    /**
     * PHP 5 constructor
     **/
    function __construct() {
        global $wpdb;


        $this->plugin_ver = 2.82;

        //enable or disable debugging
        $this->debug = 0;

        //checking for MultiSite
        if ( 1 < $wpdb->blogid )
            $this->tb_prefix = $wpdb->base_prefix . $wpdb->blogid . '_';
        else
            $this->tb_prefix = $wpdb->base_prefix;

        //set cron names
        $this->cron_send_name = 'e_newsletter_cron_send_' . $wpdb->blogid;
        $this->cron_bounce_name = 'e_newsletter_cron_check_bounces_' . $wpdb->blogid;

        //setup proper directories
        $this->plugin_main_file = __FILE__;
        $this->plugin_dir = plugin_dir_path( __FILE__ );
        $this->plugin_url = plugins_url( '/', __FILE__ );
        if(!isset($this->plugin_dir) || !isset($this->plugin_url))
            wp_die( __('Es gab ein Problem beim Bestimmen des Plugin-Pfads oder der URL', 'email-newsletter' ) );

        //templates directories
        $this->template_directory = $this->plugin_dir . 'email-newsletter-files/templates';
        $this->template_custom_directory = $this->get_custom_theme_dir();

        //get all setting of plugin
        $this->settings = $this->get_settings();

        // Setup all plugin capabilities
        $this->capabilities['create_newsletter'] = __('Newsletter erstellen','email-newsletter');
        $this->capabilities['save_newsletter'] = __('Newsletter bearbeiten','email-newsletter');
        $this->capabilities['send_newsletter'] = __('Newsletter senden','email-newsletter');
        $this->capabilities['delete_newsletter'] = __('Newsletter löschen','email-newsletter');
        $this->capabilities['create_newsletter_group'] = __('Newsletter-Gruppen erstellen','email-newsletter');
        $this->capabilities['edit_newsletter_group'] = __('Newsletter-Gruppen bearbeiten','email-newsletter');
        $this->capabilities['delete_newsletter_group'] = __('Newsletter-Gruppen löschen','email-newsletter');
        $this->capabilities['change_newsletter_group'] = __('Newsletter-Gruppen ändern','email-newsletter');
        $this->capabilities['view_newsletter_members'] = __('Newsletter-Abonnenten anzeigen','email-newsletter');
        $this->capabilities['add_newsletter_member'] = __('Newsletter-Abonnenten hinzufügen','email-newsletter');
        $this->capabilities['edit_newsletter_member'] = __('Newsletter-Abonnenten bearbeiten','email-newsletter');
        $this->capabilities['delete_newsletter_member'] = __('Newsletter-Abonnenten löschen','email-newsletter');
        $this->capabilities['add_members_group'] = __('Abonnenten zur Gruppe hinzufügen','email-newsletter');
        $this->capabilities['delete_members_group'] = __('Abonnenten aus Gruppe löschen','email-newsletter');
        $this->capabilities['save_newsletter_settings'] = __('Newsletter-Einstellungen Speichern','email-newsletter');
        $this->capabilities['view_newsletter_dashboard'] = __('Dashboard-Seite anzeigen','email-newsletter');
        $this->capabilities['import_newsletter_members'] = __('Abonnenten importieren','email-newsletter');
        $this->capabilities['install_newsletter'] = __('Erstinstallation','email-newsletter');
        $this->capabilities['uninstall_newsletter'] = __('Deinstalliere alle Newsletter-Daten','email-newsletter');

        //Activate/deactivate actions
        register_activation_hook( $this->plugin_dir . 'e-newsletter.php', array( &$this, 'do_activation' ) );
        register_deactivation_hook( $this->plugin_dir . 'e-newsletter.php', array( &$this, 'do_deactivation' ) );

        //add new rewrite rules
        add_filter( 'rewrite_rules_array', array( &$this, 'insert_rewrite_rules' ) );
        add_filter( 'query_vars', array( &$this, 'insert_query_vars' ) );

        add_action('plugins_loaded',array(&$this,'upgrade_check'));

        add_action( 'email_newsletter_upgrade_cron',array( &$this, 'upgrade_cron' ) );

        add_action( 'admin_init', array( &$this, 'admin_init' ) );
        add_action( 'admin_enqueue_scripts', array(&$this,'admin_enqueue_scripts'));

        // filter schedules
        add_filter( 'cron_schedules', array( &$this, 'add_new_cron_time' ) );

        add_action( 'init', array( &$this, 'init' ), 999 );

        //some actions for MultiSite
        if ( function_exists( 'is_multisite' ) && is_multisite() ) {
            add_action( 'wpmu_activate_user', array( &$this, 'user_create' ) );
            add_action( 'wpmu_new_user', array( &$this, 'user_create' ) );
            add_action( 'added_existing_user', array( &$this, 'user_create' ) );
            add_action( 'remove_user_from_blog', array( &$this, 'user_remove_from_site' ) );
            add_action( 'wpmu_delete_user', array( &$this, 'user_delete' ) );
            add_action( 'delete_blog', array( &$this, 'uninstall' ) );
            add_action( 'network_admin_menu', array( &$this, 'admin_page' ) );
        }
        //changing list of members when we create or delete user of the standard site
        add_action( 'user_register', array( &$this, 'user_create' ) );
        add_action( 'delete_user', array( &$this, 'user_delete' ) );

        //Update member when editing user action
        add_action( 'edit_user_profile_update', array( &$this, 'edit_user_update_member' ) );
        add_action( 'personal_options_update', array( &$this, 'edit_user_update_member' ) );

        add_action( 'edit_user_profile', array( &$this, 'wp_admins_profile' ) );
        add_action( 'show_user_profile', array( &$this, 'wp_admins_profile' ) );

        //creating menu of the plugin
        add_action( 'admin_menu', array( &$this, 'admin_page' ) );

        //send email by WP-CRON
        add_action( $this->cron_send_name, array( &$this, 'send_by_wpcron' ) );

        //check bounces email by WP-CRON
        add_action( $this->cron_bounce_name .'_1', array( &$this, 'check_bounces' ) );
        add_action( $this->cron_bounce_name .'_2', array( &$this, 'check_bounces' ) );

        //subscribe widget stuff
        add_shortcode( 'enewsletter_subscribe', array( &$this, 'subscribe_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( &$this, 'email_newsletter_widgets_scripts' ) );

        //unsubscribe message
        add_shortcode( 'enewsletter_unsubscribe_message', array( &$this, 'unsubscribe_message_shortcode' ) );
        //unsubscribe message
        add_shortcode( 'enewsletter_subscribe_message', array( &$this, 'subscribe_message_shortcode' ) );


        //ajax action for sent preview (test) email
        //add_action( 'wp_ajax_nopriv_send_preview', array( &$this, 'send_preview_ajax' ) );
        add_action( 'wp_ajax_send_email_preview', array( &$this, 'send_preview_ajax' ) );

        //ajax action for change member's group on members page
        add_action( 'wp_ajax_nopriv_change_groups', array( &$this, 'change_groups_ajax' ) );
        add_action( 'wp_ajax_change_groups', array( &$this, 'change_groups_ajax' ) );

        //ajax action for show transparent image 1x1 for check that email was opened
        add_action( 'wp_ajax_nopriv_check_email_opened', array( &$this, 'check_email_opened_ajax' ) );
        add_action( 'wp_ajax_check_email_opened', array( &$this, 'check_email_opened_ajax' ) );

        //ajax action for test connection to bounces email
        add_action( 'wp_ajax_nopriv_test_bounces', array( &$this, 'test_bounces_ajax' ) );
        add_action( 'wp_ajax_test_bounces', array( &$this, 'test_bounces_ajax' ) );

        //ajax action for test connection to smtp server
        add_action( 'wp_ajax_nopriv_test_smtp', array( &$this, 'test_smtp_ajax' ) );
        add_action( 'wp_ajax_test_smtp', array( &$this, 'test_smtp_ajax' ) );

        //ajax action for sand email to member
        add_action( 'wp_ajax_nopriv_send_email_to_member', array( &$this, 'send_email_to_member' ) );
        add_action( 'wp_ajax_send_email_to_member', array( &$this, 'send_email_to_member' ) );

        //ajax action for subscribing
        add_action( 'wp_ajax_manage_subscriptions_ajax', array( &$this, 'manage_subscriptions_ajax' ));
        add_action( 'wp_ajax_nopriv_manage_subscriptions_ajax', array( &$this, 'manage_subscriptions_ajax'));

        // filter does shortcodes
        add_filter('email_newsletter_make_email_content', 'do_shortcode', 11);


        add_action( 'template_redirect', array( &$this, 'template_redirect' ), 12 );


        //privacy
        add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_plugin_exporter' ), 10 );
        add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_plugin_eraser' ), 10 );


        //depraciated
        add_action( 'wp_ajax_nopriv_confirm_subscibe', array( &$this, 'confirm_subscibe_ajax' ) );
        add_action( 'wp_ajax_confirm_subscibe', array( &$this, 'confirm_subscibe_ajax' ) );
        add_action( 'wp_ajax_nopriv_newsletter_unsubscribe', array( &$this, 'unsubscribe_ajax' ) );
        add_action( 'wp_ajax_newsletter_unsubscribe', array( &$this, 'unsubscribe_ajax' ) );
    }

    /**
     * Do the stuff on activation
     *
     */
    function do_activation() {
        global $email_builder;

        //Update rewrite_rules
        flush_rewrite_rules( false );

        //create folder for custom themes
        $custom_theme_dir = $this->get_custom_theme_dir();

        if (!is_dir($custom_theme_dir)) {
            mkdir($custom_theme_dir);
        }

        //sets up cron
        $settings = $this->get_settings();
        if ( 1 == $settings['cron_enable'] ) {
            if ( wp_next_scheduled( $this->cron_send_name ) )
                wp_clear_scheduled_hook( $this->cron_send_name );

            wp_schedule_event( time(), '2mins', $this->cron_send_name );
        }
        else {
            if ( wp_next_scheduled( $this->cron_send_name ) )
                wp_clear_scheduled_hook( $this->cron_send_name );
        }
    }

    /**
     * Do the stuff on deactivation
     *
     */
    function do_deactivation() {
        if ( wp_next_scheduled( $this->cron_send_name ) )
            wp_clear_scheduled_hook( $this->cron_send_name );
        if ( wp_next_scheduled( $this->cron_bounce_name .'_1' ) )
            wp_clear_scheduled_hook( $this->cron_bounce_name .'_1' );
        if ( wp_next_scheduled( $this->cron_bounce_name .'_2' ) )
            wp_clear_scheduled_hook( $this->cron_bounce_name .'_2' );
    }

    /**
     * Do the stuff on upgrade
     *
     */
    function upgrade_check() {
        //check if upgrade is necessary
        if($this->is_plugin_active_for_network(plugin_basename($this->plugin_main_file))) {
            $prev = get_site_option('email_newsletter_version', 2);
            $upgraded_cron = get_site_option('email_newsletter_upgraded_cron', 1);
        }
        else {
            $prev = get_option('email_newsletter_version', 1.25);
            $upgraded_cron = get_option('email_newsletter_upgraded_cron', 1);
        }

        if ($this->plugin_ver > $prev) {
            $this->upgrade('', $prev);

            if($this->is_plugin_active_for_network(plugin_basename($this->plugin_main_file)))
                update_site_option('email_newsletter_version', $this->plugin_ver);
            else
                update_option('email_newsletter_version', $this->plugin_ver);
        }
        if(!$upgraded_cron && !wp_next_scheduled('email_newsletter_upgrade_cron')) {
            wp_schedule_single_event(time(), 'email_newsletter_upgrade_cron');
        }
    }

    /**
     * Adding a new rule
     **/
    function insert_rewrite_rules( $rules ) {
        $newrules = array();
        $newrules['e-newsletter/unsubscribe/([\w\d]{15})(\d*)/?$'] = 'index.php?unsubscribe_page=1&unsubscribe_code=$matches[1]&unsubscribe_member_id=$matches[2]';
        $newrules['e-newsletter/view/([\w\d]{15})(\d*)/?$'] = 'index.php?view_newsletter=1&view_newsletter_code=$matches[1]&view_newsletter_send_id=$matches[2]';
        return $newrules + $rules;
    }

    /**
     * Adding the var for unsubscribe page
     **/
    function insert_query_vars( $vars ) {
        array_push( $vars, 'subscribe_page' );
        array_push( $vars, 'subscribe_code' );
        array_push( $vars, 'subscribe_member_id' );

        array_push( $vars, 'unsubscribe_page' );
        array_push( $vars, 'unsubscribe_code' );
        array_push( $vars, 'unsubscribe_member_id' );

        array_push( $vars, 'view_newsletter' );
        array_push( $vars, 'view_newsletter_code' );
        array_push( $vars, 'view_newsletter_send_id' );
        return $vars;
    }

    /**
     * Loads script for enewsletter admin area
     */
    function admin_enqueue_scripts($hook) {
        global $wp_version;

         //including JS scripts and CSS for Newsletter pages
        if ( isset( $_REQUEST['page'] ) && 1 == $this->is_enewsletter_page( $_REQUEST['page'] ) ) {
            wp_enqueue_script( 'jquery' );

            //including JS scripts
            wp_enqueue_script( 'jquery-ui-tabs' );
            wp_enqueue_script( 'jquery-ui-core' );

            //including JS scripts for tooltips
            wp_register_script( 'jquery_tooltips', $this->plugin_url . 'email-newsletter-files/js/jquery.tools.min.js' );
            wp_enqueue_script( 'jquery_tooltips' );

            //including JS scripts for progressbar
            //wp_register_script( 'jquery_ui_widget', $this->plugin_url . 'email-newsletter-files/js/ui.widget.js' );
            wp_enqueue_script( 'jquery-ui-widget' );

            //including JS scripts for progressbar
            //wp_register_script( 'jquery_progressbar', $this->plugin_url . 'email-newsletter-files/js/jquery.ui.progressbar.js' );
            wp_enqueue_script( 'jquery-ui-progressbar' );

            // Including CSS file
            wp_register_style( 'enewsletter-style', $this->plugin_url . 'email-newsletter-files/css/admin.css' );
            wp_enqueue_style( 'enewsletter-style' );

            // Including JS file
            wp_register_script( 'enewsletter-script', $this->plugin_url . 'email-newsletter-files/js/admin.js' );
            wp_enqueue_script( 'enewsletter-script' );

            $admin_js_options = array(
                'edit' => __( 'Bearbeiten', 'email-newsletter' ),
                'close' => __( 'Schließen', 'email-newsletter' ),
                'save' => __( 'Speichern', 'email-newsletter' ),
                'write_email' => __( 'Bitte schreibe eine E-Mailadresse des Abonnents', 'email-newsletter' ),
                'show_add_member' => __( 'Öffne Formular für neue Abonnenten/Importe an', 'email-newsletter' ),
                'hide_add_member' => __( 'Blende die neuen Abonnenten-/Importformulare aus', 'email-newsletter' ),
                'show_export_member' => __( 'Öffne Formular für Abonnentenexport an', 'email-newsletter' ),
                'hide_export_member' => __( 'Blende das Formular für Exportmitglieder aus', 'email-newsletter' ),
                'proper_email' => __( 'Bitte verwende die richtige E-Mail', 'email-newsletter' ),
                'proper_email' => __( 'Bitte verwende die richtige E-Mail', 'email-newsletter' ),
                'confirm' => __( 'Bist Du Dir sicher?', 'email-newsletter' ),
                'save_groups' => __( 'Gruppen speichern', 'email-newsletter' ),
                'change_groups' => __( 'Gruppen ändern', 'email-newsletter' ),
                'select_members' => __( 'Wähle Abonnenten aus.', 'email-newsletter' ),
                'settings_tab' => (isset($_GET['tab'])) ? $_GET['tab'] : (!$this->settings ? 'tabs-2' : 'tabs-1'),
                'smtp_warning' => __( 'Bitte schreibe SMTP Outgoing Server oder wähle eine andere Sendemethode!', 'email-newsletter' )
            );
            wp_localize_script( 'enewsletter-script', 'enewsletter', $admin_js_options );
        }

        wp_register_style( 'enewsletter-mp6', $this->plugin_url . 'email-newsletter-files/css/mp6.css');
        wp_enqueue_style('enewsletter-mp6');
    }

    /**
     * Manage page redirects if necessary
     **/
    function template_redirect() {
        if ( $this->is_enewsletter_page( 'unsubscribe_page' ) ) {
            $member_id = get_query_var( 'unsubscribe_member_id' );
            $unsubscribe_code = get_query_var( 'unsubscribe_code' );
            $result = $this->unsubscribe_by_code( $unsubscribe_code );
            if ( !$result['error'] ) {
                $message = $result['message'];
                $unsubscribed = 1;
            }
            else {
                $message = $result['message'];
                $unsubscribed = 0;
            }

            if(isset($this->settings['unsubscribe_page_id']) && is_numeric($this->settings['unsubscribe_page_id']) && get_post($this->settings['unsubscribe_page_id']))
                wp_redirect( add_query_arg( array('member_id' => $member_id, 'message' => urlencode($message), 'enewsletter_unsubscribed' => $unsubscribed), get_permalink($this->settings['unsubscribe_page_id']) ) );
            else {
                if($unsubscribed)
                    echo "<center><br /><br /><br /><h2 style='color: #19700A;'>" . $message . "</h2></center>";
                else
                    echo "<center><br /><br /><br /><h2 style='color: #ff0000;'>" . $message . "</h2></center>";
            }
            exit;
        }
        if ( $this->is_enewsletter_page( 'subscribe_page' ) ) {
            global $wpdb;

            $subscribed = 0;

            $member_id = get_query_var( 'subscribe_member_id' );
            $subscribe_code = get_query_var( 'subscribe_code' );

            if ( $subscribe_code != md5( "sometext123" . $member_id ) )
                $message = __( 'Fehler: Falsche Abonnementdaten!', 'email-newsletter' );

            $member_data = $this->get_member( $member_id );
            if($member_data) {
                if(empty($member_data['unsubscribe_code'])) {
                    $unsubscribe_code = $this->gen_unsubscribe_code();

                    $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_members SET member_info = '', unsubscribe_code = '%s' WHERE member_id = %d", $unsubscribe_code, $member_id ) );

                    //creating new list of groups for user
                    if ( is_array( $member_data['future_groups_id'] ) )
                        $this->add_members_to_groups( $member_id, $member_data['future_groups_id'] );

                    if($this->settings['subscribe_newsletter']) {
                        $send_details = $this->add_send_email_info( $this->settings['subscribe_newsletter'], $member_id, 0, 'waiting_send' );
                        $this->send_email_to_member($send_details['send_id']);
                    }

                    $subscribed = 1;
                    $message = __( 'Erfolgreiches Abonnement!', 'email-newsletter' );
                }
                else {
                    $message = __( 'Abonnent bereits abonniert!', 'email-newsletter' );
                }
            }
            else {
                $message = __( 'Beim Abonnieren ist ein Problem aufgetreten!', 'email-newsletter' );
            }

            if(isset($this->settings['subscribe_page_id']) && is_numeric($this->settings['subscribe_page_id']) && get_post($this->settings['subscribe_page_id']))
                wp_redirect( add_query_arg( array('member_id' => $member_id, 'message' => urlencode($message), 'enewsletter_subscribed' => $subscribed), get_permalink($this->settings['subscribe_page_id']) ) );
            else {
                if($subscribed)
                    echo "<center><br /><br /><br /><h2 style='color: #19700A;'>" . $message . "</h2></center>";
                else
                    echo "<center><br /><br /><br /><h2 style='color: #ff0000;'>" . $message . "</h2></center>";
            }
            exit;
        }
        elseif ( $this->is_enewsletter_page( 'view_newsletter' ) ) {
            require_once( $this->plugin_dir . "email-newsletter-files/page-view-newsletter.php" );
            exit;
        }
    }

    /**
     * init for admin
     **/
    function admin_init() {
        $mu_cap = (function_exists('is_multisite' && is_multisite()) ? 'manage_network_options' : 'manage_options');

        //Force caps for admin
        $admin_role = get_role('administrator');
        if(is_object($admin_role))
            foreach($this->capabilities as $key => $cap) {
                if(!isset($admin_role->capabilities[$key]) || $admin_role->capabilities[$key] == false ) {
                    $admin_role->add_cap($key,true);
                }
            }

        //private actions of the plugin
        if ( isset( $_REQUEST['newsletter_action'] ) ) {
            //handle custom redirects
            if(isset($_REQUEST['redirect_to']))
                $redirect = esc_url_raw($_REQUEST['redirect_to']);

            switch( $_REQUEST[ 'newsletter_action' ] ) {

                //action for save Newsletter
                case "clone_newsletter":
                    if(! (current_user_can('create_newsletter') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $result = $this->clone_newsletter( $_REQUEST['newsletter_id'], $_REQUEST['page'] );

                    $redirect = !isset($redirect) ? add_query_arg( array( 'page' => 'newsletters', 'updated' => 'true', 'message' => urlencode( __( 'Der Newsletter wurde kopiert!', 'email-newsletter' ) ) ), 'admin.php' ) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                //action for delete Newsletter
                case "delete_newsletter":
                    if(! (current_user_can('delete_newsletter') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $result = $this->delete_newsletter( $_REQUEST['newsletter_id'] );

                    $redirect = !isset($redirect) ? add_query_arg( array( 'page' => 'newsletters', 'updated' => 'true', 'message' => urlencode( __( 'Der Newsletter wird gelöscht!', 'email-newsletter' ) ) ), 'admin.php' ) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                //action for create new group
                case "create_group":
                    if(! (current_user_can('create_newsletter_group') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $edit_public = ( isset( $_REQUEST['public'] ) ) ? '1' : '0';
                    $result = $this->create_edit_group( $_REQUEST['group_name'], $edit_public );

                    $redirect = !isset($redirect) ? add_query_arg( array( 'page' => 'newsletters-groups', 'updated' => 'true', 'message' => urlencode( $result['message'] ) ), 'admin.php' ) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                //action for edit group
                case "edit_group":
                    if(! (current_user_can('edit_newsletter_group') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $edit_public = ( isset( $_REQUEST['edit_public'] ) ) ? '1' : '0';
                    $result = $this->create_edit_group( $_REQUEST['edit_group_name'], $edit_public, $_REQUEST['group_id'] );

                    $redirect = !isset($redirect) ? add_query_arg( array( 'page' => 'newsletters-groups', 'updated' => 'true', 'message' => urlencode( $result['message'] ) ), 'admin.php' ) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                //action for delete group
                case "delete_group":
                    if(! (current_user_can('delete_newsletter_group') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $result = $this->delete_group( $_REQUEST['group_id'] );

                    $redirect = !isset($redirect) ? add_query_arg( array( 'page' => 'newsletters-groups', 'updated' => 'true', 'message' => urlencode( __( 'Gruppe wird gelöscht!', 'email-newsletter' ) ) ), 'admin.php' ) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                //action add new member
                case "add_member":
                    if(! (current_user_can('add_newsletter_member') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $result = $this->add_member( $_REQUEST['member'] );

                    $redirect = !isset($redirect) ? esc_url_raw(add_query_arg( array( 'page' => 'newsletters-members', 'updated' => 'true', 'message' => urlencode( $result['message'] ) ) )) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                //action edit member
                case "edit_member":
                    if(! (current_user_can('edit_newsletter_member') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $result = $this->edit_member( $_REQUEST['member_id'], $_REQUEST['edit_member_nicename'], $_REQUEST['edit_member_email'] );

                    $redirect = !isset($redirect) ? esc_url_raw(add_query_arg( array( 'page' => 'newsletters-members', 'updated' => 'true', 'message' => urlencode( $result['message'] ) ) ) ) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                //action delete member
                case "delete_member":
                    if(! (current_user_can('delete_newsletter_member') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $member_id = array($_REQUEST['member_id']);
                    $result = $this->delete_members( $member_id );

                    $redirect = !isset($redirect) ? esc_url_raw(add_query_arg( array( 'page' => 'newsletters-members', 'updated' => 'true', 'message' => urlencode( $result['message'] ) ) ) ) : $redirect;
                    wp_redirect( $redirect );
                    exit;
                break;

                //Bulk action delete members
                case "delete_members":
                    if(! (current_user_can('delete_newsletter_member') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $result = $this->delete_members( $_REQUEST['members_id'] );

                    $redirect = !isset($redirect) ? esc_url_raw(add_query_arg( array( 'page' => 'newsletters-members', 'updated' => 'true', 'message' => urlencode( $result['message'] ) ) )) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                //Bulk action add members to group
                case "add_members_group":

                    if(! (current_user_can('add_members_group') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    if($_REQUEST['list_group_id'] == 'subscribed' || $_REQUEST['list_group_id'] == 'unsubscribed') {
                        global $wpdb;

                        foreach ($_REQUEST['members_id'] as $member_id) {
                            $member_data = $this->get_member( $member_id );

                            if($member_data) {
                                if(empty($member_data['unsubscribe_code']) && $_REQUEST['list_group_id'] == 'subscribed')
                                    $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_members SET unsubscribe_code = '%s' WHERE member_id = %d", $this->gen_unsubscribe_code(), $member_id ) );
                                elseif(!empty($member_data['unsubscribe_code']) && $_REQUEST['list_group_id'] == 'unsubscribed')
                                    $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_members SET unsubscribe_code = '' WHERE member_id = %d", $member_id ) );

                            }
                        }
                    }
                    else
                        $result = $this->add_members_to_groups( $_REQUEST['members_id'], $_REQUEST['list_group_id'] );

                    $redirect = !isset($redirect) ? esc_url_raw(add_query_arg( array( 'page' => 'newsletters-members', 'updated' => 'true', 'message' => urlencode( __( 'Abonnenten werden der Gruppe hinzugefügt!', 'email-newsletter' ) ) ) )) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                //action for change group
                case "change_group":

                    if(! (current_user_can('change_newsletter_group') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    //subscribe/unsubscribe if necessary TODO Consider turning it into function
                    $member_data = $this->get_member( $_REQUEST['member_id'] );
                    if($member_data) {
                        global $wpdb;

                        $subscribed = isset($_REQUEST['groups_id']) ? array_search('subscribed', $_REQUEST['groups_id']) : false;
                        if($subscribed !== false && empty($member_data['unsubscribe_code']))
                            $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_members SET unsubscribe_code = '%s' WHERE member_id = %d", $this->gen_unsubscribe_code(), $_REQUEST['member_id'] ) );
                        elseif($subscribed === false && !empty($member_data['unsubscribe_code']))
                            $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_members SET unsubscribe_code = '' WHERE member_id = %d", $_REQUEST['member_id'] ) );
                    }
                    if($subscribed !== false && isset($_REQUEST['groups_id'][$subscribed]))
                        unset($_REQUEST['groups_id'][$subscribed]);

                    $groups_id = ( isset( $_REQUEST['groups_id'] ) ) ? $_REQUEST['groups_id'] : array();
                    $result = $this->add_members_to_groups( $_REQUEST['member_id'], $groups_id, 1 );

                    $redirect = !isset($redirect) ? esc_url_raw(add_query_arg( array( 'page' => 'newsletters-members', 'updated' => 'true', 'message' => urlencode( __( 'Gruppen werden geändert!', 'email-newsletter' ) ) ) )) : $redirect;
                    wp_redirect( $redirect );
                    exit;
                break;

                //Bulk action add members to group
                case "delete_members_group":

                    if(! (current_user_can('delete_members_group') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $result = $this->delete_members_group( $_REQUEST['members_id'], $_REQUEST['list_group_id'] );

                    $redirect = !isset($redirect) ? esc_url_raw(add_query_arg( array( 'page' => 'newsletters-members', 'updated' => 'true', 'message' => urlencode( __( 'Abonnenten werden aus der Gruppe gelöscht!', 'email-newsletter' ) ) ) )) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                //Bulk action add members to group
                case "export_members":

                    if(! (current_user_can('edit_newsletter_member') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    if($_REQUEST['separ_sign'] == 1)
                        $separate_by = ';';
                    else
                        $separate_by = ',';

                    $groups_id = isset($_REQUEST['groups_id']) ? $_REQUEST['groups_id'] : array();
                    $groups_ungrouped = isset($_REQUEST['groups_ungrouped']) ? $_REQUEST['groups_ungrouped'] : 0;

                    $this->export_members($groups_id, $groups_ungrouped, $separate_by);
                break;

                //action save settings
                case "save_settings":

                    if(! (current_user_can('save_newsletter_settings') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $this->save_settings( $_REQUEST['settings'] );
                break;

                //action send newsletter
                case "send_newsletter":

                    if(! (current_user_can('send_newsletter') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    //Handles sending ajax sent stopped newsletters
                    if ( isset( $_REQUEST['cron'] ) && 'add_to_cron' == $_REQUEST['cron'] )
                        $this->add_to_cron( $_REQUEST['newsletter_id'], $_REQUEST['send_id'] );
                    elseif ( isset( $_REQUEST['cron'] ) && 'remove_from_cron' == $_REQUEST['cron'] )
                        $this->remove_from_cron( $_REQUEST['newsletter_id'], $_REQUEST['send_id'] );
                    //handles main send buttons
                    else if ( isset( $_REQUEST['action'] ) && 'send' == $_REQUEST["action"] )
                        $this->send_newsletter( $_REQUEST['newsletter_id'] );
                break;

                //action import members
                case "import_members":

                    if(! (current_user_can('import_newsletter_members') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $this->import_members();
                break;

                //action install data in DB
                case "install":

                    if(! (current_user_can('install_newsletter') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $this->install();
                    $this->save_settings( $_REQUEST['settings'] );
                break;

                //action uninstall data from DB
                case "uninstall":

                    if(! (current_user_can('uninstall_newsletter') || current_user_can($mu_cap)) )
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    $this->uninstall();

                    //redirection must happen here because it will stop site deleting in network
                    $redirect = !isset($redirect) ? add_query_arg( array( 'page' => 'newsletters-settings', 'updated' => 'true', 'message' => urlencode( __( "eNewsletter Daten wurden gelöscht.", 'email-newsletter' ) ) ), 'admin.php' ) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                case "dismiss_install":

                    if(!current_user_can($mu_cap))
                        wp_die('Du hast keine Erlaubnis das zu tun');

                    update_option('email_newsletter_install_dismissed', 1);
                break;

            }
        }
        if(!$this->settings && get_option('email_newsletter_install_dismissed', 0) == 0 && current_user_can($mu_cap))
            add_action( 'all_admin_notices', array( &$this, 'install_notice' ), 5 );

        //privacy stuff
        if(function_exists('wp_add_privacy_policy_content')) {
            $privacy_text = __( "Diese Website verfügt über ein Newsletter-Anmeldeformular, das Folgendes erfasst: Name des Besuchers, E-Mail-Adresse, Beitrittszeit und Abonnementgruppen. Diese Details werden später verwendet, um Newsletter an Abonnenten zu senden. Statistiken zum Newsletter (gesendet, geöffnet und zurückgeschickt) werden ebenfalls gesammelt. Benutzer können sich jederzeit mit einem Link in jedem Newsletter abmelden.", 'email-newsletter' );
            wp_add_privacy_policy_content('E-Newsletter', $privacy_text);
        }
    }

    function install_notice() {
        echo '<div class="updated fade"><p>' . sprintf(__('Bitte <strong><a href="%s" title="Installiere jetzt &raquo;">und konfiguriere PS-eNewsletter</a></strong> um alle Features nutzen zu können. <small><a style="color:red;" href="%s">(dismiss)</a></small>', 'email-newsletter'), admin_url('admin.php?page=newsletters-settings'), add_query_arg('newsletter_action', 'dismiss_install')) . '</a></p></div>';
    }

    /**
     * init for all users
     **/
    function init() {

        //load translation files
        load_plugin_textdomain( 'email-newsletter', false, dirname( plugin_basename( __FILE__ ) ) . '/email-newsletter-files/languages/' );

        //public actions of the plugin
        if ( isset( $_REQUEST['newsletter_action'] ) && !defined('DOING_AJAX') ) {
            //handle custom redirects
            if(isset($_REQUEST['redirect_to']))
                $redirect = $_REQUEST['redirect_to'];

            switch( $_REQUEST['newsletter_action'] ) {
                //action for subscribe
                case "new_subscribe":
                    $result = $this->new_subscribe();

                    $redirect = isset($redirect) ? $redirect : (isset($result['data']['redirect']) ? $result['data']['redirect'] : 0);
                    if($redirect) {
                        wp_redirect( $redirect );
                        exit();
                    }
                break;

                //action for subscribe
                case "subscribe":
                    $result = $this->subscribe();

                    $redirect = !isset($redirect) ? add_query_arg( array( 'page' => 'newsletters-subscribes', 'updated' => 'true', 'message' => urlencode( $result['message'] )), 'admin.php' ) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                //action for save selected groups of subscribe
                case "save_subscribes":
                    $result = $this->save_subscribes();

                    $redirect = !isset($redirect) ? add_query_arg( array( 'page' => 'newsletters-subscribes', 'updated' => 'true', 'message' => urlencode( $result['message'] ) ), 'admin.php' ) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;

                //action for Unsubscribe
                case "unsubscribe":
                    $unsubscribe_code = (isset($_REQUEST['unsubscribe_code'])) ? $_REQUEST['unsubscribe_code'] : '';
                    $result = $this->unsubscribe_by_code( $unsubscribe_code );

                    $redirect = !isset($redirect) ? add_query_arg( array( 'page' => 'newsletters-subscribes', 'updated' => 'true', 'message' => urlencode( $result['message']) ), 'admin.php' ) : $redirect;
                    wp_redirect( $redirect );
                    exit();
                break;
            }
        }
    }

    /**
     * subscribtion actions moslty(only:)) for ajax widget
     */
    function manage_subscriptions_ajax() {
        if ( isset( $_REQUEST['newsletter_action'] ) ) {
            switch( $_REQUEST['newsletter_action'] ) {
                //action for save selected groups of subscribe
                case "save_subscribes":
                    $result = $this->save_subscribes();

                    $data['message'] = $result['message'];
                    $data['view'] = 'manage_subscriptions';
                    $data['hide'] = '';

                    echo json_encode($data);
                    die();
                break;

                //action for forcing into groups
                case "subscribe_to_groups":
                    $result = $this->save_subscribes('add');

                    $data['message'] = $result['message'];
                    $data['view'] = 'unsubscribe_from_groups';
                    $data['hide'] = 'subscribe_to_groups';

                    echo json_encode($data);
                    die();
                break;

                //action for forcing out off groups
                case "unsubscribe_from_groups":
                    $result = $this->save_subscribes('remove');

                    $data['message'] = $result['message'];
                    $data['view'] = 'subscribe_to_groups';
                    $data['hide'] = 'unsubscribe_from_groups';

                    echo json_encode($data);
                    die();
                break;

                //action for subscribe
                case "subscribe":
                    $result = $this->subscribe();

                    $data['message'] = $result['message'];
                    if(!$result['error']) {
                        if(isset($this->settings['subscribe_page_id']) && is_numeric($this->settings['subscribe_page_id']) && get_post($this->settings['subscribe_page_id']))
                            $data['redirect'] = add_query_arg(array('message' => urlencode($result['message']), 'enewsletter_subscribed' => 1), get_permalink($this->settings['subscribe_page_id']));

                        $data['view'] = 'manage_subscriptions';
                        $data['hide'] = 'subscribe';
                        $data['unsubscribe_code'] = $result['data']['unsubscribe_code'];
                        if(isset($result['data']['subscribe_groups']))
                            $data['subscribe_groups'] = $result['data']['subscribe_groups'];
                    }
                    else {
                        $data['view'] = 'subscribe';
                        $data['hide'] = 'subscribe';
                    }

                    echo json_encode($data);
                    die();
                break;

                //action for Unsubscribe
                case "unsubscribe":
                    $unsubscribe_code = (isset($_REQUEST['unsubscribe_code'])) ? $_REQUEST['unsubscribe_code'] : '';
                    $result = $this->unsubscribe_by_code( $unsubscribe_code );

                    if(isset($this->settings['unsubscribe_page_id']) && is_numeric($this->settings['unsubscribe_page_id']) && get_post($this->settings['unsubscribe_page_id']))
                        $data['redirect'] = add_query_arg(array('message' => urlencode($result['message']), 'enewsletter_unsubscribed' => 1), get_permalink($this->settings['unsubscribe_page_id']));

                    $data['message'] = $result['message'];
                    $data['view'] = 'subscribe';
                    $data['hide'] = 'manage_subscriptions';

                    echo json_encode($data);
                    die();
                break;

                //action for Subscribe of public member (not user of site)
                case "new_subscribe":
                    $result = $this->new_subscribe();

                    $data = array('message' => $result['message']);
                    if(isset($result['data']['redirect']))
                        $data['redirect'] = $result['data']['redirect'];

                    echo json_encode($data);
                    die();
                break;
            }
        }

        die();
    }



    /**
     * Add new member
     **/
    function add_member( $member_data, $double_opt_in = 0 ) {
        global $wpdb;

        do_action( 'enewsletter_before_user_add', $member_data );

        //first lets check if email exists somewhere
        if ( email_exists( $member_data['email'] ) !== false ) {
            //if email of new member == email of site user
            $wp_user_id = email_exists( $member_data['email'] );
            $member_id = $this->get_members_by_wp_user_id( $wp_user_id );

            //check that this site's user there is on list of members
            if ( 0 < $member_id )
                $message =  __( 'Diese E-Mail wird bereits verwendet!', 'email-newsletter' );

        } else {
            //check email of new member that isn't on list of members
            $member =  $this->get_member_by_email($member_data['email']);
            if ( isset($member['unsubscribe_code']) && !empty($member['unsubscribe_code']) )
                $message =   __( 'Diese E-Mail ist bereits abonniert!', 'email-newsletter' );
        }
        if(isset($message))
            return array('action' => 'email_exists', 'error' => true, 'message' => $message, 'data' => array());

        //New email, lets add it!
        $subscribe = $double_opt_in ? "" : 1;

        $member_data['member_info'] = $double_opt_in ? serialize(array("future_groups_id" => $member_data['groups_id'])) : '';
        $member_data_ready = array(
                'member_fname' => $member_data['fname'],
                'member_lname' => $member_data['lname'],
                'member_email' => $member_data['email'],
                'member_info' => $member_data['member_info']
            );
        $result = $this->create_update_member_user('', $member_data_ready, $subscribe);
        $member_id = $result['member_id'];
        do_action( 'enewsletter_after_user_add', $member_id );

        if ( $double_opt_in ) {
            $status = $this->do_double_opt_in( $member_id );
            if($status)
                return array('action' => 'optin_sent', 'error' => false, 'message' => __( 'Bestätigungs-E-Mail wurde gesendet! Bitte bestätige Dein Abonnement.', 'email-newsletter' ));
            else
                return array('action' => 'optin_sent', 'error' => true, 'message' => __( 'Fehler beim Senden der Anmelde-E-Mail. Stelle sicher, dass Du diese noch nicht in Deinem Posteingang hast.', 'email-newsletter' ));
        } else {
            //creating new list of groups for user
            if ( isset( $member_data['groups_id'] ) && is_array( $member_data['groups_id'] ) )
                $this->add_members_to_groups( $member_id, $member_data['groups_id'] );

            //set sending welcome newsletter if necessary
            if($this->settings['subscribe_newsletter']) {
                $send_details = $this->add_send_email_info( $this->settings['subscribe_newsletter'], $member_id, 0, 'waiting_send' );
                $this->send_email_to_member($send_details['send_id']);
            }
        }

        return array('action' => 'member_added', 'error' => false, 'message' => __( 'Das neue Abonnent wird hinzugefügt!', 'email-newsletter' ));
    }

    /**
     *  Public Subscribe on Newsletters
     **/
    function new_subscribe() {
        global $wpdb;

        if(!is_email($_REQUEST['e_newsletter_email'])) {
            $data['message'] = __( 'Bitte verwende die richtige E-Mail-Adresse!', 'email-newsletter' );

            return array('action' => 'new_subscribed', 'error' => true, 'message' => $data['message']);
        }

        $settings = $this->get_settings();

        //Sets up groups to subscribe to on the beginning
        $subscribe_groups = (isset($settings['subscribe_groups'])) ? explode(',', $settings['subscribe_groups']) : array();
        if(isset($_REQUEST['e_newsletter_groups_id']))
            $subscribe_groups = array_merge($_REQUEST['e_newsletter_groups_id'], $subscribe_groups);
        if(isset($_REQUEST['e_newsletter_auto_groups_id']))
            $subscribe_groups = array_merge($_REQUEST['e_newsletter_auto_groups_id'], $subscribe_groups);
        $subscribe_groups = array_unique($subscribe_groups);

        //set up if double opt in is on
        $double_opt_in = ( isset( $this->settings['double_opt_in'] ) && $this->settings['double_opt_in'] ) ? 1 : 0;

        $member_data['email']       =  ( isset( $_REQUEST['e_newsletter_email'] ) ) ? $_REQUEST['e_newsletter_email'] : '';
        $member_data['fname']       =  ( isset( $_REQUEST['e_newsletter_name'] ) ) ? $_REQUEST['e_newsletter_name'] : '';
        $member_data['lname']       =  '';
        $member_data['groups_id']   =  $subscribe_groups;

        $result = $this->add_member( $member_data, $double_opt_in );
        if(!$result['error']) {
            if($result['action'] != 'optin_sent' && isset($this->settings['subscribe_page_id']) && is_numeric($this->settings['subscribe_page_id']) && get_post($this->settings['subscribe_page_id']))
                $data['redirect'] = add_query_arg(array('message' => urlencode($result['message']), 'enewsletter_subscribed' => 1), get_permalink($this->settings['subscribe_page_id']));
            else
                $data['redirect'] = 0;

            if($result['action'] == 'optin_sent')
                $data['message'] = $result['message'];
                $data['message'] = __( 'Du wurdest erfolgreich abonniert!', 'email-newsletter' );

            return array('action' => 'new_subscribed', 'error' => false, 'message' => $data['message'], 'data' => array('redirect' => $data['redirect']));
        }
        else
            return array('action' => 'new_subscribed', 'error' => true, 'message' => $result['message']);
    }

    /**
     *  Subscribe on Newsletters
     **/
    function subscribe() {
        global $wpdb;

        $current_user = wp_get_current_user();
        $user_id = $current_user->data->ID;
        $member_data = array();

        $result = $this->create_update_member_user($user_id, $member_data, 1);
        $member_id = $result['member_id'];

        if($member_id) {
            do_action( 'enewsletter_user_subscribe', $member_id );

            //Sets up groups to subscribe to on the beginning
            $subscribe_groups = (isset($this->settings['subscribe_groups'])) ? explode(',', $this->settings['subscribe_groups']) : array();
            if(isset($_REQUEST['e_newsletter_groups_id']))
                $subscribe_groups = array_merge($_REQUEST['e_newsletter_groups_id'], $subscribe_groups);
            if(isset($_REQUEST['e_newsletter_auto_groups_id']))
                $subscribe_groups = array_merge($_REQUEST['e_newsletter_auto_groups_id'], $subscribe_groups);
            $subscribe_groups = array_unique($subscribe_groups);

            $result_groups = $this->add_members_to_groups( $member_id, $subscribe_groups );

            if($this->settings['subscribe_newsletter']) {
                $send_details = $this->add_send_email_info( $this->settings['subscribe_newsletter'], $member_id, 0, 'waiting_send' );
                $this->send_email_to_member($send_details['send_id']);
            }

            return array('action' => 'subscribed', 'error' => false, 'message' => __( 'Du hast Dich erfolgreich angemeldet!', 'email-newsletter' ), 'data' => array('member_id' => $member_id, 'unsubscribe_code' => $result['unsubscribe_code'], 'subscribe_groups' => $subscribe_groups));
        }
        else
            return array('action' => 'subscribed', 'error' => true, 'message' => __( 'Beim Abonnieren ist ein Fehler aufgetreten!', 'email-newsletter' ), 'data' => array('member_id' => $member_id, 'unsubscribe_code' => $result['unsubscribe_code']));
    }

    /**
     * Save Subscribes
     **/
    function save_subscribes($type = 'both') {
        $current_user = wp_get_current_user();
        $member_id = $this->get_members_by_wp_user_id( $current_user->data->ID );
        $remove_old = $type == 'both' ? 1 : 0;

        //remove from unsubscribed if action like this is preformed
        $member_data = $this->get_member( $member_id );
        if($member_data)
            if(empty($member_data['unsubscribe_code'])) {
                global $wpdb;
                $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_members SET unsubscribe_code = '%s' WHERE member_id = %d", $this->gen_unsubscribe_code(), $member_id ) );
            }


        if($type == 'both' || $type == 'add') {
            if($type == 'both') {
                $groups_id = isset($_REQUEST['e_newsletter_groups_id']) ? $_REQUEST['e_newsletter_groups_id'] : array();
                $message = __( 'Abonnements wurden gespeichert!', 'email-newsletter' );
            }
            else {
                $groups_id = isset($_REQUEST['e_newsletter_add_groups_id']) ? $_REQUEST['e_newsletter_add_groups_id'] : array();
                $message = __( 'Du hast Dich erfolgreich angemeldet!', 'email-newsletter' );
            }
           $result = $this->add_members_to_groups( $member_id, $groups_id, $remove_old );
        }
        elseif($type == 'remove') {
            $groups_id = (isset($_REQUEST['e_newsletter_remove_groups_id'])) ? $_REQUEST['e_newsletter_remove_groups_id'] : array();
            $result = $this->delete_members_group( $member_id, $groups_id );
            $message = __( 'Du wurdest erfolgreich abgemeldet!', 'email-newsletter' );
        }

        do_action( 'enewsletter_user_save_subscribe', $member_id, $groups_id );

        return array('action' => 'subscription_saved', 'error' => false, 'message' => __( 'Abonnements wurden gespeichert!', 'email-newsletter' ), 'data' => array('member_id' => $member_id, 'groups_ids' => $groups_id));
    }

    /**
     * Edit member
     **/
    function edit_member( $member_id, $member_nicename, $member_email ) {
        global $wpdb;

        do_action( 'enewsletter_user_edit', $member_id, $member_nicename, $member_email );

        if ( $member_id && is_email( $member_email ) ) {
            $member_name = explode(' ', $member_nicename);
            if(!isset($member_name[1]))
                $member_name[1] = '';

            $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_members SET
            member_fname = %s,
            member_lname = %s,
            member_email = %s
            WHERE member_id = %d
            ", $member_name[0], $member_name[1], $member_email, $member_id ) );
        }

        if(isset($result) && $result)
            return array('action' => 'member_updated', 'error' => false, 'message' => __( 'Benutzer aktualisiert!', 'email-newsletter' ));
        else
            return array('action' => 'member_updated', 'error' => true, 'message' => __( 'Aktualisierung des Benutzers fehlgeschlagen!', 'email-newsletter' ));
    }

    /**
     * Unsubscribe on Newsletters
     **/
    function unsubscribe_by_code( $unsubscribe_code ) {
        global $wpdb;
        if ($unsubscribe_code) {
            $member =  $this->get_member_id_by_code($unsubscribe_code);
            if ( 0 < $member['member_id'] ) {
                if(apply_filters('email_newsletter_keep_unsubscribed', false)) {
                    //delete unsubscribe_code of member
                    $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_members SET unsubscribe_code = '' WHERE unsubscribe_code = '%s'", $unsubscribe_code ) );
                }
                else {
                    $result = $this->delete_members( array($member['member_id']) );
                }

                return array('action' => 'unsubscribed', 'error' => false, 'message' => __( 'Du bist abgemeldet!', 'email-newsletter' ));
            }
            elseif( 0 < $member['wp_only_user_id'] ) {
                update_user_meta( $member['wp_only_user_id'], 'email_newsletter_unsubscribe_code', 'unsubscribed' );
                return array('action' => 'unsubscribed', 'error' => false, 'message' => __( 'Du bist abgemeldet!', 'email-newsletter' ));
            }
            return array('action' => 'unsubscribed', 'error' => false, 'message' => __( 'Du bist bereits abgemeldet oder noch nicht abonniert!', 'email-newsletter' ));
        }
    }

    /**
     * Delete members
     **/
    function delete_members( $members_id ) {
        global $wpdb;

        do_action( 'enewsletter_user_delete', $members_id );

        if ( $members_id ) {
            foreach( ( array ) $members_id as $member_id ) {
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_member_group WHERE member_id = %d", $member_id ) );
                $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_members WHERE member_id = %d", $member_id ) );
            }

            if(count($members_id) == 1)
                return array('action' => 'member_deleted', 'error' => false, 'message' => __( 'Abonnent gelöscht!', 'email-newsletter' ));
            elseif(count($members_id) == 0)
                return array('action' => 'member_deleted', 'error' => true, 'message' => __( 'Keine zu löschenden Abonnenten!', 'email-newsletter' ));
            else
                return array('action' => 'member_deleted', 'error' => false, 'message' => __( 'Abonnenten gelöscht!', 'email-newsletter' ));
        }
    }

    /**
     * Adding new member when create new user
     **/
    function user_create( $userID ) {
        if( !isset( $this->settings['wp_user_register_subscribe'] ) || ( isset( $this->settings['wp_user_register_subscribe'] ) && $this->settings['wp_user_register_subscribe'] ))
            $subscribe = 1;
        else
            $subscribe = 0;

        $result = $this->create_update_member_user($userID, array(), $subscribe);

        $member_id = $result['member_id'];

        if($member_id) {
            global $wpdb;

            if($this->settings['subscribe_newsletter']) {
                $send_details = $this->add_send_email_info( $this->settings['subscribe_newsletter'], $member_id, 0, 'waiting_send' );
                $this->send_email_to_member($send_details['send_id']);
            }

            //creating new list of groups for user
            $subscribe_groups = isset($this->settings['subscribe_groups']) ? explode(',', $this->settings['subscribe_groups']) : 0;
            if ( $subscribe_groups && is_array( $subscribe_groups ) )
                $this->add_members_to_groups( $member_id, $subscribe_groups );
        }
    }

    /**
     * Updates newsletters member details when updating any wp profile
     **/
    function edit_user_update_member( $user_id ) {
        if ( current_user_can('edit_user',$user_id) ) {
            if(is_email( $_POST['email'] )) {
                $blogs = get_blogs_of_user( $user_id );
                foreach ($blogs as $blog) {
                    if(is_multisite()) {
                        if(!isset($current_blog) || !$current_blog)
                            $current_blog = get_current_blog_id();
                        switch_to_blog( $blog->userblog_id  );
                    }

                    $member_data_ready = array(
                            'wp_user_id' => $user_id,
                            'member_email' => $_POST['email']
                        );
                    if(!empty($_POST['first_name'])) {
                        $member_data_ready['member_fname'] = $_POST['first_name'];
                        if(!empty($_POST['last_name']))
                            $member_data_ready['member_lname'] = $_POST['last_name'];
                    }
                    elseif(!empty($_POST['nickname'])) {
                        $member_data_ready['member_fname'] = $_POST['nickname'];
                        $member_data_ready['member_lname'] = '';
                    }
                    else {
                        $member_data_ready['member_fname'] = '';
                        $member_data_ready['member_lname'] = '';
                    }

                    $result = $this->create_update_member_user($user_id, $member_data_ready, '', 1);
                }

                if(is_multisite())
                    switch_to_blog( $current_blog  );
            }

            if(isset($_POST['email_newsletter_unsubscribe_code'])) {
                if($_POST['email_newsletter_unsubscribe_code'] == 'unsubscribed')
                    update_user_meta( $user_id, 'email_newsletter_unsubscribe_code', 'unsubscribed' );
                elseif($_POST['email_newsletter_unsubscribe_code'] == 'yes')
                    update_user_meta( $user_id, 'email_newsletter_unsubscribe_code', $this->gen_unsubscribe_code(1) );
            }
        }
    }

    /**
     * Allows admins to control if they want to recieve mass newsletters for admins
     **/
    function wp_admins_profile() {
        global $user_ID, $wpdb;

        if ( !empty( $_GET['user_id'] ) ) {
            $user_id = $_GET['user_id'];
        } else {
            $user_id = $user_ID;
        }
        if($this->is_plugin_active_for_network(plugin_basename($this->plugin_main_file))) {
            $results = $wpdb->get_results( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key LIKE %s AND meta_value LIKE %s AND user_id = %d", '%capabilities', '%administrator%', $user_id) );
            if($results) {
                $unsubscribe_code = get_user_meta( $user_id, 'email_newsletter_unsubscribe_code', true );
                ?>
                <h3><?php _e('Multisite Benutzer Newsletters', 'email-newsletter'); ?></h3>

                <table class="form-table">
                <tr>
                    <th><label for="email_newsletter_unsubscribe_code"><?php _e('Erhalte Newsletter für Seiten-Administratoren', 'email-newsletter'); ?></label></th>
                    <td>
                        <select name="email_newsletter_unsubscribe_code" id="email_newsletter_unsubscribe_code">
                                <option value="yes"<?php if ( $unsubscribe_code != 'unsubscribed' ) { echo ' selected="selected" '; } ?>><?php _e('Ja', 'email-newsletter'); ?></option>
                                <option value="unsubscribed"<?php if ( $unsubscribe_code == 'unsubscribed' ) { echo ' selected="selected" '; } ?>><?php _e('Nein', 'email-newsletter'); ?></option>
                        </select>
                    </td>

                </tr>
                </table>
            <?php
            }
        }
    }

    /**
     * Deleting member's groups and member when delete site user
     **/
    function user_delete( $userID ) {
        global $wpdb;

        if ( function_exists('is_multisite' ) && is_multisite() ) {
                $blogids = $wpdb->get_col( "SELECT blog_id FROM $wpdb->blogs" );
        } else {
                $blogids[] = 1;
        }

        foreach ( $blogids as $blog_id ) {
            //Checking DB prefix TODO - maybe function?
            if ( 1 < $blog_id )
                $tb_prefix = $wpdb->base_prefix . $blog_id . '_';
            else
                $tb_prefix = $wpdb->base_prefix;

            if($this->get_settings($tb_prefix)) {
                $member_id = $this->get_members_by_wp_user_id( $userID, $blog_id );

                if ( 0 < $member_id ) {
                    $wpdb->query( $wpdb->prepare( "DELETE FROM {$tb_prefix}enewsletter_member_group WHERE member_id = %d", $member_id ) );
                    $wpdb->query( $wpdb->prepare( "DELETE FROM {$tb_prefix}enewsletter_members WHERE member_id = %d", $member_id ) );
                }
            }
        }
    }

    /**
     * Deleting member's groups and member when remove user from site
     **/
    function user_remove_from_site( $userID ) {
        global $wpdb;

        $member_id = $this->get_members_by_wp_user_id( $userID );

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_member_group WHERE member_id = %d", $member_id ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_members WHERE member_id = %d", $member_id ) );
    }


    /**
     * Create/Edit new Group
     **/
    function create_edit_group( $group_name, $public, $group_id = 0 ) {
        global $wpdb;

        //update when editing group
        if ( $group_id ) {

            $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_groups SET group_name = '%s', public = '%s' WHERE group_id = %d", trim( $group_name ), $public, $group_id ) );
            if( $result && $result['group_id'] != $group_id )
                return array('action' => 'group_modified', 'error' => false, 'message' => __( 'Die Gruppe wurde geändert.', 'email-newsletter' ));
        } else {
            $result = $wpdb->get_row( $wpdb->prepare( "SELECT group_id FROM {$this->tb_prefix}enewsletter_groups WHERE LOWER(group_name) = '%s'",  strtolower( $group_name ) ), "ARRAY_A");
            if ( $result )
                return array('action' => 'group_exists', 'error' => true, 'message' => __( 'Die Gruppe existiert bereits!', 'email-newsletter' ));

            $result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$this->tb_prefix}enewsletter_groups SET group_name = '%s', public = '%s'", trim( $group_name), $public ) );
                return array('action' => 'group_created', 'error' => false, 'message' => __( 'Die Gruppe wurde erstellt.', 'email-newsletter' ));
        }
    }


    /**
     * Delete Group
     **/
    function delete_group( $group_id ) {
        global $wpdb;

        $result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_member_group WHERE group_id = %d", $group_id ) );
        $result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_groups WHERE group_id = %d", $group_id ) );

        return $result;
    }

    /**
     * Add members to groups
     **/
    function add_members_to_groups( $members_id, $groups_id, $delete_old = 0 ) {
        global $wpdb;
        $result = 0;

        if(!is_array($members_id))
            $members_id = array($members_id);
        if(!is_array($groups_id))
            $groups_id = array($groups_id);

        if ( count($members_id) > 0 )
            foreach( $members_id as $member_id ) {
                //deleting old list of groups if necessary
                if($delete_old)
                    $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_member_group WHERE member_id = %d", $member_id ) );

                foreach( $groups_id as $group_id ) {
                    if(!$this->get_group_by_id( $group_id ))
                        continue;

                    $result = $wpdb->get_var( $wpdb->prepare( "SELECT group_id FROM {$this->tb_prefix}enewsletter_member_group WHERE member_id = %d AND group_id = %d", $member_id, $group_id ) );
                    if ( !$result )
                        $result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$this->tb_prefix}enewsletter_member_group SET member_id = %d, group_id =  %d", $member_id, $group_id ) );
                }
            }

        return $result;
    }

    /**
     * Bulk option -  delete member from group
     **/
    function delete_members_group( $members_id, $groups_id ) {
        global $wpdb;

        if(!is_array($members_id))
            $members_id = array($members_id);
        if(!is_array($groups_id))
            $groups_id = array($groups_id);

        if ( count($members_id) > 0 )
            foreach( $members_id as $member_id ) {
                foreach( $groups_id as $group_id ) {
                    if(!$this->get_group_by_id( $group_id ))
                        continue;

                    $result = $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_member_group WHERE member_id = %d AND group_id = %d", $member_id, $group_id ) );
                }
            }

        return $result;
    }




    /**
     * Delete Newsletter
     **/
    function delete_newsletter( $newsletter_id ) {
        global $wpdb;

        do_action( 'enewsletter_delete_newsletter', $newsletter_id );

        $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_newsletters WHERE newsletter_id = %d", $newsletter_id ) );
        $wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_meta WHERE email_id = %d", $newsletter_id ) );
        $wpdb->query( $wpdb->prepare( "DELETE A FROM {$this->tb_prefix}enewsletter_send_members A INNER JOIN wp_enewsletter_send B ON A.send_id = B.send_id WHERE B.newsletter_id = %d", $newsletter_id ) );
        //$wpdb->query( $wpdb->prepare( "DELETE FROM {$this->tb_prefix}enewsletter_send WHERE newsletter_id = %d", $newsletter_id ) );

        $this->delete_newsletter_meta($newsletter_id);

        return true;
    }

    /**
     * Clone Newsletter
     **/
    function clone_newsletter( $newsletter_id = NULL, $page_redirect ) {
        global $wpdb, $email_builder;

        do_action( 'enewsletter_clone_newsletter', $newsletter_id );

        $result = $wpdb->query( $wpdb->prepare( "
            INSERT INTO {$this->tb_prefix}enewsletter_newsletters
            (create_date, template, subject, from_name, from_email, content, contact_info, bounce_email, sent, opened, bounced)
            SELECT %d, template, subject, from_name, from_email, content, contact_info, bounce_email, 0, 0, 0
            FROM {$this->tb_prefix}enewsletter_newsletters
            WHERE newsletter_id = %d
            "
             , time(), $newsletter_id  ) );

        $new_newsletter_id = $wpdb->insert_id;

        $result = $wpdb->query( $wpdb->prepare( "
            INSERT INTO {$this->tb_prefix}enewsletter_meta
            (email_id, meta_key, meta_value)
            SELECT %d, meta_key, meta_value
            FROM {$this->tb_prefix}enewsletter_meta
            WHERE email_id = %d
            "
             , $new_newsletter_id, $newsletter_id  ) );

        return true;
    }

    /**
     * Check that email was opened
     **/
    function check_email_opened_ajax() {
        global $wpdb;

        //write opened time to table
        $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_send_members a LEFT JOIN {$this->tb_prefix}enewsletter_send b ON (a.send_id = b.send_id) SET opened_time = %d WHERE (a.send_id = %d OR b.start_time = %d) AND a.member_id = %d AND a.wp_only_user_id = %d AND a.opened_time = 0" , time(), $_REQUEST['send_id'], $_REQUEST['send_id'], $_REQUEST['member_id'], $_REQUEST['wp_only_user_id'] ) );
        if($result) {
            $this->plus_one_member_stats($_REQUEST['member_id'], 'opened');
            $newsletter = $wpdb->get_row( $wpdb->prepare(
                "SELECT b.newsletter_id AS newsletter_id
                FROM {$this->tb_prefix}enewsletter_send_members a
                LEFT JOIN {$this->tb_prefix}enewsletter_send b ON (a.send_id = b.send_id)
                WHERE (a.send_id = %d OR b.start_time = %d)",
                $_REQUEST['send_id'], $_REQUEST['send_id']
            ), "ARRAY_A" );

            if(isset($newsletter['newsletter_id']) && $newsletter['newsletter_id'])
                $this->plus_one_newsletter_stats($newsletter['newsletter_id'], 'opened');
        }

        //show blank image 1x1
        header('Content-Type: image/jpeg');
        $filename = $this->plugin_dir . "email-newsletter-files/images/spacer.gif";
        $handle = fopen( $filename, "r" );
        $content = fread( $handle, filesize( $filename ) );
        fclose( $handle );
        echo $content;
        die();
    }

    /**
     * Write inforamtion of Send newsletter to DB
     **/
    function send_newsletter( $newsletter_id ) {
        global $wpdb;

        do_action( 'enewsletter_before_send', $newsletter_id );

        $members_id = array();
        if ( isset( $_REQUEST["all_members"] ) && "1" == $_REQUEST["all_members"] ) {
            $args = array (
                'where' => "unsubscribe_code != ''"
            );

            $members = $this->get_members( $args, 0, 0 );
            foreach ( $members as $member ) {
                $members_id[] = $member['member_id'];
            }
        } else {
            //Get ids for eNewsletter group members
            if ( isset( $_REQUEST["target"]["groups"] ) && is_array($_REQUEST["target"]["groups"]) )
                foreach ( $_REQUEST["target"]["groups"] as $group_id ) {
                    $members_id = array_merge ( $members_id,  $this->get_members_of_group( $group_id, '', 1 ) );
                }

            //Get ids for Membership 2 subscribers
            if ( isset( $_REQUEST["target"]["m2"] ) && is_array($_REQUEST["target"]["m2"]) ) {
                foreach ( $_REQUEST["target"]["m2"] as $membership_id ) {
                    $users_id = $this->get_members_of_membership2($membership_id);
                    foreach ( $users_id as $user_id ) {
                        $member_id = $this->get_members_by_wp_user_id( $user_id, '', 1 );
                        if ( 0 < $member_id )
                            $members_id[] = $member_id;
                    }
                }
            }

            // Deprecated: Membership plugin was replaced by M2 (above)
            //Get ids for Membership levels being subscribed eNewsletter members
            if ( isset( $_REQUEST["target"]["membership_levels"] ) && is_array($_REQUEST["target"]["membership_levels"]) )
                foreach ( $_REQUEST["target"]["membership_levels"] as $membership_level ) {
                    $members = $this->get_members_of_membership($membership_level);
                    foreach ( $members as $member ) {
                        $members_id[] = $member['member_id'];
                    }
                }

            //Get ids for Roles being eNewsletter members
            if ( isset( $_REQUEST["target"]["roles"] ) && is_array($_REQUEST["target"]["roles"]) )
                foreach ( $_REQUEST["target"]["roles"] as $role_name ) {
                    $users_id = get_users( array( 'role' => $role_name ) );
                    foreach ( $users_id as $user_id ) {
                        $member_id = $this->get_members_by_wp_user_id( $user_id->ID, '', 1 );
                        if ( 0 < $member_id )
                            $members_id[] = $member_id;
                    }
                }

            $members_id = array_unique( $members_id );
        }
        //Get ids for admins of other sites
        if ( isset( $_REQUEST["target"]["site_admins"] ) && $_REQUEST["target"]["site_admins"] == 'yes' )
            $wp_only_users_id = $this->get_global_wp_user_ids();
        else
            $wp_only_users_id = 0;

        if ( 'cron_time' == $_REQUEST['cron_time'] ) {
            $time_str = $_REQUEST['aa'].'-'.$_REQUEST['mm'].'-'.$_REQUEST['jj'].' '.($_REQUEST['hh']-get_option('gmt_offset')).':'.$_REQUEST['mn'].':00 GMT';
            $status = $start_time = strtotime($time_str);
        }
        elseif ( 'cron' == $_REQUEST["cron"] )
            $status = 'by_cron';
        else
            $status = 'waiting_send';

        $dont_send_duplicate = (isset( $_REQUEST['dont_send_duplicate'] )) ? $_REQUEST['dont_send_duplicate'] : 0;
        $send_to_bounced = (isset( $_REQUEST['send_to_bounced'] )) ? $_REQUEST['send_to_bounced'] : 0;

        $result = $this->add_send_email_info( $newsletter_id, $members_id, $wp_only_users_id, $status, $dont_send_duplicate, $send_to_bounced );
        if ( !$result['count'] )
            wp_redirect( add_query_arg( array( 'page' => $_REQUEST['page'], 'newsletter_action' => 'send_newsletter', 'newsletter_id' => $newsletter_id, 'updated' => 'true', 'message' => urlencode( __( 'Alle Abonnenten haben es bereits erhalten oder es ist kein Benutzer abonniert!', 'email-newsletter' ) ) ), 'admin.php' ) );
        else
            if ( 'cron' == $_REQUEST["cron"] )
                wp_redirect( add_query_arg( array( 'page' => $_REQUEST['page'], 'newsletter_action' => 'send_newsletter', 'newsletter_id' => $newsletter_id, 'updated' => 'true', 'message' => urlencode( $result['count'] . ' ' . __( 'Abonnenten werden zur CRON-Liste hinzugefügt', 'email-newsletter' ) ) ), 'admin.php' ) );
            else
                wp_redirect( add_query_arg( array( 'page' => $_REQUEST['page'], 'newsletter_action' => 'send_newsletter', 'newsletter_id' => $newsletter_id, 'send_id' => $result['send_id'], 'check_key' => $_REQUEST['check_key'] ), 'admin.php' ) );

        exit();
    }

    /**
     * Add email or send to CRON list
     **/
    function add_to_cron( $newsletter_id, $send_id ) {
        global $wpdb;

        $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_send_members SET status = 'by_cron' WHERE send_id = %d AND status = 'waiting_send'", $send_id ) );

        wp_redirect( add_query_arg( array( 'page' => $_REQUEST['page'], 'newsletter_action' => 'send_newsletter', 'newsletter_id' => $newsletter_id, 'updated' => 'true', 'message' => urlencode( __( 'Abonnenten werden zur CRON-Liste hinzugefügt', 'email-newsletter' ) ) ), 'admin.php' ) );

        exit;
    }

    /**
     * Remove from CRON list
     **/
    function remove_from_cron( $newsletter_id, $send_id ) {
        global $wpdb;

        $result = $wpdb->query( $wpdb->prepare( "UPDATE {$this->tb_prefix}enewsletter_send_members SET status = 'waiting_send' WHERE send_id = %d AND status != 'waiting_send'", $send_id ) );

        wp_redirect( add_query_arg( array( 'page' => $_REQUEST['page'], 'newsletter_action' => 'send_newsletter', 'newsletter_id' => $newsletter_id, 'updated' => 'true', 'message' => urlencode( __( 'Abonnenten werden aus der CRON-Liste entfernt', 'email-newsletter' ) ) ), 'admin.php' ) );

        exit;
    }

    /**
     * Send email to member
     **/
    function send_email_to_member($send_id = 0) {
        global $wpdb;

        if ( isset($_REQUEST['action']) && $_REQUEST['action'] == 'send_email_to_member' && defined('DOING_AJAX') && !wp_verify_nonce( $_REQUEST['check_key'], 'newsletter_send' ) )
             die( 'Security check' );

        if(!$send_id)
            $send_id = $_REQUEST['send_id'];

        do_action( 'enewsletter_before_send_newsletter', $send_id );

        $send_member = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->tb_prefix}enewsletter_send_members WHERE send_id = %d AND status = 'waiting_send' LIMIT 0, 1",  $send_id ), "ARRAY_A");

        if ( ! $send_member ) {
            if ( ! wp_next_scheduled( 'e_newsletter_cron_check_bounces_' . $wpdb->blogid .'_1' ) )
                wp_schedule_single_event( time() + 60*2, 'e_newsletter_cron_check_bounces_' . $wpdb->blogid .'_1' );
            else {
                wp_clear_scheduled_hook( 'e_newsletter_cron_check_bounces_' . $wpdb->blogid .'_1' );
                wp_schedule_single_event( time() + 60*2, 'e_newsletter_cron_check_bounces_' . $wpdb->blogid .'_1' );
            }

            $message = 'end';
            do_action( 'enewsletter_after_send_newsletter', $send_id );
        }
        else{
            //configure correct bounce hash to detect if standard user or wp only user
            if($send_member['member_id']) {
                $member_data = $this->get_member( $send_member['member_id'] );
                $bounce_id = $send_member['member_id'];
                $bounce_hash = md5( 'Hash of bounce member_id='. $bounce_id . ', send_id='. $send_id );
            }
            elseif($send_member['wp_only_user_id']) {
                $member_data = $this->get_wp_user_only( $send_member['wp_only_user_id'] );
                $bounce_id = $send_member['wp_only_user_id'];
                $bounce_hash = md5( 'Hash of bounce wp_only_user_id='. $bounce_id . ', send_id='. $send_id );
            }

            $send_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->tb_prefix}enewsletter_send WHERE send_id = %d",  $send_id ), "ARRAY_A");

            if( !empty($member_data["member_email"]) && is_email($member_data["member_email"]) ) {
                //get data of newsletter

                $newsletter_data = $this->get_newsletter_data( $send_data['newsletter_id'] );

                $contents = $send_data['email_body'];

                //Replace some content inside the email body
                $user_name = $this->get_nicename($member_data['wp_user_id'], $member_data['member_nicename']);
                $first_name = $this->get_firstname($member_data['wp_user_id'], $member_data['member_nicename']);
                $last_name = $this->get_lastname($member_data['wp_user_id'], $member_data['member_nicename']);

                $replacements = array(
                    'user_name' => $user_name,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'to_email' => $member_data["member_email"]
                );


                $contents = $this->personalise_email_body( $contents, $send_member['member_id'], $send_member['wp_only_user_id'], $member_data['join_date'], $member_data['unsubscribe_code'], $send_data['start_time'], $replacements );

                $newsletter_data["subject"] = $this->personalise_email_body($newsletter_data["subject"], $send_member['member_id'], $send_member['wp_only_user_id'], $member_data['join_date'], $member_data['unsubscribe_code'], $send_data['start_time'], array('user_name' => $user_name, 'first_name' => $first_name, 'to_email' => $member_data["member_email"]));

                if((isset($newsletter_data['bounce_email']) && !empty($newsletter_data['bounce_email'])) || (isset($this->settings['bounce_email']) && !empty($this->settings['bounce_email'])))
                    $options['bounce_email'] = (isset($newsletter_data['bounce_email']) && !empty($newsletter_data['bounce_email'])) ? $newsletter_data['bounce_email'] : $this->settings['bounce_email'];

                $from_domain = explode('@',$newsletter_data['from_email']);
                $from_domain = isset($from_domain[1]) ? '@'.$from_domain[1] : '';
                $options['message_id'] = 'newsletters-' . $bounce_id . '-' . $send_id . '-'. $bounce_hash.$from_domain;

                $sent_status = $this->send_email( $newsletter_data['from_name'], $newsletter_data['from_email'], $member_data["member_email"], $newsletter_data["subject"], $contents, $options );
                $this->write_log( 'Send status: '.$sent_status);
                if( $sent_status === true ) {
                    //write info of Sent in DB
                    $result = $this->set_send_email_status('sent', $send_id, $send_member['member_id'], $send_member['wp_only_user_id'], $send_data['newsletter_id'] );
                    if ( $result )
                        $message = 'ok';
                    else
                        $message = __( 'Fehler beim Aktualisieren der Datenbank.', 'email-newsletter' );
                } else {
                    if( $sent_status == 'recipients_failed' || $sent_status == 'invalid_address' || strpos( strtolower( $sent_status ), 'recipient') !== false ) {
                        $result = $this->set_send_email_status( 'bounced', $send_id, $send_member['member_id'], $send_member['wp_only_user_id'], $send_data['newsletter_id'] );
                        if ( $result )
                            $message = 'ok';
                        else
                            $message = __( 'Fehler beim Aktualisieren der Datenbank.', 'email-newsletter' );
                    }
                    else {
                        $message = __( 'Fehler beim Senden der E-Mail. Bitte überprüfe die Einstellungen für ausgehende E-Mails.', 'email-newsletter' );
                    }
                }
            }
            else {
                $result = $this->set_send_email_status( 'bounced', $send_id, $send_member['member_id'], $send_member['wp_only_user_id'], $send_data['newsletter_id'] );
                if ( $result )
                    $message = 'ok';
                else
                    $message = __( 'Fehler beim Aktualisieren der Datenbank.', 'email-newsletter' );
            }
        }

        if( isset($_REQUEST['action']) && $_REQUEST['action'] == 'send_email_to_member' && defined('DOING_AJAX') )
            die($message);
        else
            return $message;
    }

    /**
     * Send email to member
     **/
    function send_by_wpcron() {
        global $wpdb;

        @set_time_limit( 0 );

        if ( 1 > $wpdb->get_var( "SELECT Count(send_id) FROM {$this->tb_prefix}enewsletter_send_members WHERE status = 'by_cron' OR status < UNIX_TIMESTAMP()") )
            return false;

        $process_id = time();
        //writing some information in the plugin log file
        $this->write_log( $process_id . " 01 - start" );

        if ( ! get_option( 'enewsletter_cron_send_run' ) ) {

            //writing some information in the plugin log file
            $this->write_log( $process_id . " 02 - before enewsletter_cron_send_run 1" );

            //add new column for check limit
            if ( 1 != $wpdb->query( "DESCRIBE {$this->tb_prefix}enewsletter_send_members sent_time" ) ) {
                $wpdb->query( "ALTER TABLE {$this->tb_prefix}enewsletter_send_members ADD sent_time INT" );
            }

            update_option( 'enewsletter_cron_send_run', time() );

            //writing some information in the plugin log file
            $this->write_log( $process_id . " 03 - set enewsletter_cron_send_run 1" );


            if ( 0 < $this->settings['send_limit'] ) {

                $month  = date( 'n', time() );
                $year   = date( 'Y', time() );
                $day    = date( 'j', time() );
                $hour   = date( 'H', time() );
                $min    = date( 'i', time() );

                switch ( $this->settings['cron_time'] ) {
                case '1':
                    $limit_time_start   = mktime( $hour , 0, 0, $month, $day, $year ) ;
                    $limit_time_end     = mktime( $hour + 1, 0, -1, $month, $day, $year );
                    break;
                case '2':
                    $limit_time_start   = mktime( 0, 0, 0, $month, $day, $year );
                    $limit_time_end     = mktime( 0, 0, -1, $month, $day + 1, $year );
                    break;
                case '3':
                    $limit_time_start   =  mktime( 0, 0, 0, $month, 1, $year);
                    $limit_time_end     =  mktime( 0, 0, -1, $month + 1, 1, $year);
                    break;
                }

                //writing some information in the plugin log file
                $this->write_log( $process_id . " 04 - cron_time: " . $this->settings['cron_time'] . "  limit_time_start:" . $limit_time_start . "  limit_time_end:" . $limit_time_end );

                $current_count_sent = $wpdb->get_var( $wpdb->prepare( "SELECT Count(send_id) FROM {$this->tb_prefix}enewsletter_send_members WHERE sent_time BETWEEN %d AND %d", $limit_time_start, $limit_time_end ) );

                //writing some information in the plugin log file
                $this->write_log( $process_id . " 05 - current_count_sent: " . $current_count_sent  . "  send_limit:" . $this->settings['send_limit'] );
            }


            if ( ! isset( $current_count_sent ) || $current_count_sent < $this->settings['send_limit'] ) {
                $send_limit = 'LIMIT 0, 500';
                if(!isset($current_count_sent))
                    $current_count_sent = 0;

                //writing some information in the plugin log file
                $this->write_log( $process_id . " 06 - NOT LIMIT YET" );

                //Remember not to use numbers as status other then unixtimestamp (status > 0)
                $send_members = $wpdb->get_results( "SELECT * FROM {$this->tb_prefix}enewsletter_send_members WHERE status = 'by_cron' OR (status > 0 and status < UNIX_TIMESTAMP()) " . $send_limit , "ARRAY_A");

                //writing some information in the plugin log file
                $this->write_log( $process_id . " 07 - send_members count:" . count($send_members) );

                if ( ! $send_members ) {
                    delete_option( 'enewsletter_cron_send_run' );
                    die(1);
                }

                foreach ( $send_members as $send_member ) {
                    do_action( 'enewsletter_before_cron_send_newsletter', $send_member );

                    update_option( 'enewsletter_cron_send_run', time() );

                    if($send_member['member_id']) {
                        $member_data = $this->get_member( $send_member['member_id'] );
                        $bounce_id = $send_member['member_id'];
                        $bounce_hash = md5( 'Hash of bounce member_id='. $bounce_id . ', send_id='. $send_member['send_id'] );
                    }
                    elseif($send_member['wp_only_user_id']) {
                        $member_data = $this->get_wp_user_only( $send_member['wp_only_user_id'] );
                        $bounce_id = $send_member['wp_only_user_id'];
                        $bounce_hash = md5( 'Hash of bounce wp_only_user_id='. $bounce_id . ', send_id='. $send_member['send_id'] );
                    }

                    $send_data = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$this->tb_prefix}enewsletter_send WHERE send_id = %d",  $send_member['send_id'] ), "ARRAY_A");
                    if( !empty($member_data["member_email"]) && is_email($member_data["member_email"]) ) {
                        //get data of newsletter
                        $newsletter_data = $this->get_newsletter_data( $send_data['newsletter_id'] );

                        if( !empty($newsletter_data) ) {
                            $this->write_log( $process_id . " 07-2 - send_member_id:" . $send_member['member_id'] . "/" . $send_member['wp_only_user_id'] . "/" . $send_data['newsletter_id'] . "/" . $newsletter_data['from_name'] . "/" . $send_member['send_id'] );
                            if(isset($this->settings['cron_wait']) && is_numeric($this->settings['cron_wait']) && $this->settings['cron_wait'])
                                $options['cron_wait'] = $this->settings['cron_wait'];

                            $contents = $send_data['email_body'];

                            //Replace some content inside the email body
                            $user_name = $this->get_nicename($member_data['wp_user_id'], $member_data['member_nicename']);
                            $first_name = $this->get_firstname($member_data['wp_user_id'], $member_data['member_nicename']);
                            $contents = $this->personalise_email_body($contents, $send_member['member_id'], $send_member['wp_only_user_id'], $member_data['join_date'], $member_data['unsubscribe_code'], $send_data['start_time'], array('user_name' => $user_name, 'first_name' => $first_name, 'to_email' => $member_data["member_email"]));

                            if((isset($newsletter_data['bounce_email']) && !empty($newsletter_data['bounce_email'])) || (isset($this->settings['bounce_email']) && !empty($this->settings['bounce_email'])))
                                $options['bounce_email'] = (isset($newsletter_data['bounce_email']) && !empty($newsletter_data['bounce_email'])) ? $newsletter_data['bounce_email'] : $this->settings['bounce_email'];

                            $from_domain = explode('@',$newsletter_data['from_email']);
                            $from_domain = isset($from_domain[1]) ? '@'.$from_domain : '';
                            $options['message_id'] = 'Newsletters-' . $bounce_id . '-' . $send_member['send_id'] . '-'. $bounce_hash;

                            $sent_status = $this->send_email( $newsletter_data['from_name'], $newsletter_data['from_email'], $member_data["member_email"], $newsletter_data["subject"], $contents, $options );
                            if( $sent_status === true ) {
                                //write info of Sent in DB
                                $result = $this->set_send_email_status('sent', $send_member['send_id'], $send_member['member_id'], $send_member['wp_only_user_id'], $send_data['newsletter_id']);

                                //writing some information in the plugin log file
                                $this->write_log( $process_id . " 09 - send OK" );

                                if ( ++$current_count_sent == $this->settings['send_limit'] ) {
                                    //writing some information in the plugin log file
                                    $this->write_log( $process_id . " 10 - STOP - LIMIT" );

                                    delete_option( 'enewsletter_cron_send_run' );
                                    die(2);
                                }
                            } else {
                                //writing some information in the plugin log file
                                $this->write_log( $process_id . " 08 - send_errors" );
                            }
                        }
                        else {
                            $result = $this->set_send_email_status('bounced', $send_member['send_id'], $send_member['member_id'], $send_member['wp_only_user_id'], $send_data['newsletter_id']);

                            $this->write_log( $process_id . " 08 - send_errors:" . " newsletter data empty" );
                        }
                    }
                    else {
                        $result = $this->set_send_email_status('bounced', $send_member['send_id'], $send_member['member_id'], $send_member['wp_only_user_id'], $send_data['newsletter_id']);

                        $this->write_log( $process_id . " 08 - send_errors:" . " no_email" );
                    }

                    do_action( 'enewsletter_after_cron_send_newsletter', $send_member );
                }

                if ( ! wp_next_scheduled( 'e_newsletter_cron_check_bounces_' . $wpdb->blogid .'_2' ) )
                    wp_schedule_single_event( time() + 60*5, 'e_newsletter_cron_check_bounces_' . $wpdb->blogid .'_2' );
                else {
                    wp_clear_scheduled_hook( 'e_newsletter_cron_check_bounces_' . $wpdb->blogid .'_2' );
                    wp_schedule_single_event( time() + 60*5, 'e_newsletter_cron_check_bounces_' . $wpdb->blogid .'_2' );
                }

            } else {
                delete_option( 'enewsletter_cron_send_run' );
            }
        } elseif ( get_option( 'enewsletter_cron_send_run' ) < time() - 3*60 ) {
            //writing some information in the plugin log file
            $this->write_log( $process_id . " 11 - CRON works more 3 min - restart CRON" );

            delete_option( 'enewsletter_cron_send_run' );
            die(3);
        }
        //writing some information in the plugin log file
        $this->write_log( $process_id . " 12 - END" );

        die(4);
    }

    /**
     * Check bounces email
     **/
    function check_bounces() {
        if(!function_exists('imap_open'))
            return false;

        global $wpdb;

        @set_time_limit( 0 );
        $email_address  = $this->settings['bounce_email'];
        $email_username = $this->settings['bounce_username'];
        $email_password = $this->settings['bounce_password'];
        $email_host     = trim( $this->settings['bounce_host'] );
        $email_port     = ( $this->settings['bounce_port'] ) ? $this->settings['bounce_port'] : 110;

        $email_password = $this->_decrypt($email_password);

        if( ! $email_host )
            return true;

        $email_security = ( $this->settings['bounce_security'] ) ? $this->settings['bounce_security'] : '';

        $mbox = $this->pop3_connet($email_host, $email_port, $email_security, $email_username, $email_password );

        if( ! $mbox ) {
            $this->write_log( 'bounce: error cant connect. Error details: '.strip_tags(imap_last_error()) );
            return 'Error: Failed to connect when checking bounces!';
        } else {
            $this->write_log('bounce: connected to check bounce');

            $MC     = imap_check( $mbox );
            $mails  = imap_fetch_overview( $mbox, "1:{$MC->Nmsgs}", 0 );

            foreach ( $mails as $mail ) {
                $this->write_log('bounce: checked email');


                if(
                    strpos($mail->from,'MAILER-DAEMON') !== FALSE ||
                    strpos($mail->from,'mailer-daemon') !== FALSE
                ){
                    $body = imap_body ( $mbox, $mail->msgno );

                    if(
                        preg_match( '/X-Mailer:\s*<?Newsletters-(\d+)-(\d+)-([A-Fa-f0-9]{32})/i', $body, $matches) ||
                        preg_match( '/X-Mailer:\s*<?newsletters-(\d+)-(\d+)-([A-Fa-f0-9]{32})/i', $body, $matches) ||
                        preg_match( '/Message-ID:\s*<?Newsletters-(\d+)-(\d+)-([A-Fa-f0-9]{32})/i', $body, $matches)  ||
                        preg_match( '/Message-ID:\s*<?newsletters-(\d+)-(\d+)-([A-Fa-f0-9]{32})/i', $body, $matches)
                    ) {

                        $member_id      = ( int ) $matches[1];
                        $send_id        = ( int ) $matches[2];
                        $email_hash     = trim( $matches[3] );
                        $hash           = md5( 'Hash of bounce member_id='. $member_id . ', send_id='. $send_id );
                        $hash_wp        = md5( 'Hash of bounce wp_only_user_id='. $member_id . ', send_id='. $send_id );

                        $this->write_log('bounce: data: '.$member_id.'/'.$send_id.'/'.$email_hash.'/'.$hash.'/'.$hash_wp);

                        if( $email_hash == $hash || $email_hash == $hash_wp ){
                            if($email_hash == $hash_wp) {
                                $wp_only_user_id = $member_id;
                                $member_id = 0;
                            }
                            else {
                                $wp_only_user_id = 0;
                            }

                            $newsletter = $wpdb->get_row( $wpdb->prepare( "SELECT newsletter_id FROM {$this->tb_prefix}enewsletter_send WHERE send_id = %d LIMIT 1",  $send_id ), "ARRAY_A");
                            if(isset($newsletter['newsletter_id']) && $newsletter['newsletter_id'])
                                $result = $this->set_send_email_status('bounced', $send_id, $member_id, $wp_only_user_id, $newsletter['newsletter_id']);
                            imap_delete( $mbox, $mail->msgno );
                            echo 'ok';
                        } else {
                            echo 'Error: hash';
                        }

                        $this->write_log('bounce: found bounce:'.$member_id.'/'.$wp_only_user_id);
                    }
                    else {
                        preg_match_all("/[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})/i", $body, $possible_emails);

                        foreach ($possible_emails as $possible_email) {
                            if($possible_email[0] != $this->settings['from_email'] && $possible_email[0] != $this->settings['bounce_email']) {
                                $member_id = $this->get_member_by_email( $possible_email[0] );
                                if($member_id ) {
                                    $this->write_log('bounce: found bounce:'.$member_id);
                                    $this->plus_one_member_stats($member_id, 'bounced');

                                    imap_delete( $mbox, $mail->msgno );
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            imap_expunge( $mbox );
            imap_close( $mbox );
        }
        die();
    }

    /**
     * Send Preview (Test) newsletter email
     **/
    function send_preview_ajax() {
        $newsletter_id = (isset($_REQUEST['newsletter_id']) ? $_REQUEST['newsletter_id'] : false);

        if(!$newsletter_id)
            die( __( 'Keine gültige Newsletter-ID angegeben.', 'email-newsletter' ) );

        $newsletter_data = $this->get_newsletter_data( $newsletter_id );
        $content = $this->make_email_body($newsletter_id);
        $links = array(
            '{VIEW_LINK}',
            '%7BVIEW_LINK%7D',
            '{UNSUBSCRIBE_URL}',
            '%7BUNSUBSCRIBE_URL%7D',
        );
        $content = str_replace( $links, '#', $content );
        $content = str_replace( array( '{OPENED_TRACKER}', '%7BOPENED_TRACKER%7D', ),
            '<div style="font-size: 0px; line-height:0px; display:none; visibility: hidden;"><img src="#" width="1" height="1"/></div>', $content );
        if($newsletter_data && $content) {
            $subject = '(PREVIEW) '.$newsletter_data['subject'];
            if( $this->settings['bounce_email'] ) {
                $options = array('bounce_email' => $this->settings['bounce_email']);
            }
            else
                $options = array();

            $sent_status = $this->send_email( $newsletter_data['from_name'], $newsletter_data['from_email'], $_REQUEST['preview_email'], $subject, $content, $options );
            if( $sent_status === true )
                die( __( 'Test-E-Mail wurde gesendet.', 'email-newsletter' ) );
            else
                die( __( 'Test-E-Mail konnte nicht gesendet werden! Stelle sicher, dass die eingegebene E-Mail korrekt ist, und überprüfe auch die Einstellungen für ausgehende E-Mails.', 'email-newsletter' ) );
        } else {
            die( __( 'E-Mail-Text konnte nicht generiert werden.', 'email-newsletter' ) );
        }
    }

    /**
     * Test smtp settings
     **/
    function test_smtp_ajax(){
        @set_time_limit( 0 );

        //Send test email on bounces address
        $email_id           = time();
        $email_to           = $_REQUEST['smtp_from'];
        $email_from         = $_REQUEST['smtp_from'];
        $email_subject      = "Test-Connection-Send-". $email_id;
        $email_contents     = 'Test';

        $server_host = $_REQUEST['smtp_host'];
        $server_username = $_REQUEST['smtp_username'];
        $server_password = $_REQUEST['smtp_password'];
        if($server_password == '********') {
            $settings = $this->get_settings();
            $server_password = $this->_decrypt($settings['smtp_pass']);
        }

        $server_port = $_REQUEST['smtp_port'];
        $server_security = $_REQUEST['smtp_security'];

        if ( !class_exists( 'ePHPMailer' ) )
            require_once( $this->plugin_dir . "email-newsletter-files/phpmailer/class.phpmailer.php" );

        $mail = new ePHPMailer();
        $mail->CharSet = 'UTF-8';

        $mail->IsSMTP();
        $mail->Host = $server_host;

        if($server_security == 'tls' || $server_security == 'ssl')
            $mail->SMTPSecure = $server_security;
        if(!empty($server_port))
            $mail->Port = $server_port;

        $mail->SMTPAuth = ( strlen( $server_username ) > 0 );

        if( $mail->SMTPAuth ){
            $mail->Username = esc_attr($server_username);
            $mail->Password = $server_password;
        }

        $mail->From = $email_from;
        $mail->Sender = $email_from;
        $mail->Subject = $email_subject;
        $mail->isHTML( true );
        $mail->MsgHTML( $email_contents );
        $mail->AddAddress( $email_to );

        $send_status = $mail->Send();
        if( $send_status != true ) {
            die( __( 'Test-E-Mail konnte nicht gesendet werden! - Bitte überprüfe Deine Einstellungen für ausgehende E-Mails und die Serverkonfiguration, um festzustellen, ob ausgewählte Ports geöffnet sind. Fehlerdetails: ', 'email-newsletter' ).strip_tags($mail->ErrorInfo) );
        }
        else
            die( __( 'Testnachricht erfolgreich gesendet! Vergiss bitte nicht Deine Einstellungen zu speichern. Beachte bitte, dass Dein Server möglicherweise die Anzahl der zulässigen Nachrichten begrenzt, welche pro Stunde/Tag/Woche gesendet werden dürfen.', 'email-newsletter' ) );
    }

    /**
     * Test bounces settings
     **/
    function test_bounces_ajax(){
        if(!function_exists('imap_open'))
            die( __( 'PHP Imap wird nicht unterstützt', 'email-newsletter' ) );

        @set_time_limit( 0 );

        //Send test email on bounces address
        $email_id           = time();
        $email_to           = $_REQUEST['bounce_email'];
        $email_from         = ( $this->settings['from_email'] ) ? $this->settings['from_email'] : $_REQUEST['bounce_email'];
        $email_from_name    = ( $this->settings['from_name'] ) ? $this->settings['from_name'] : $_REQUEST['bounce_email'];
        $email_subject      = "Test-Connection-Bounce-". $email_id;
        $email_contents     = 'Test';
        $options            = array ();

        $send_status = $this->send_email( $email_from_name, $email_from, $email_to, $email_subject, $email_contents, $options );
        if( $send_status !== true )
            die( __( 'Test-E-Mail konnte nicht gesendet werden! Bitte überprüfe Deine Einstellungen für ausgehende E-Mails.', 'email-newsletter' )  );

        sleep(10);

        //Set value for connect to email server
        $email_address  = $_REQUEST['bounce_email'];
        $email_username = $_REQUEST['bounce_username'];
        $email_password = $_REQUEST['bounce_password'];
        $email_host     = trim( $_REQUEST['bounce_host'] );
        $email_port     = ( $_REQUEST['bounce_port'] ) ? $_REQUEST['bounce_port'] : 110;

        if($email_password == '********') {
            $settings = $this->get_settings();
            $email_password = $this->_decrypt($settings['bounce_password']);
        }

        if( ! $email_host )
            return true;

        sleep( 20 );

        $email_security = isset($_REQUEST['bounce_security']) ? $_REQUEST['bounce_security'] : '';

        $mbox = $this->pop3_connet($email_host, $email_port, $email_security, $email_username, $email_password );

        if( ! $mbox ) {
            die( __( 'Verbindung beim Überprüfen der Bounces fehlgeschlagen! Bitte überprüfe Deine Bounce-Einstellungen und die Serverkonfiguration, um festzustellen, ob ausgewählte Ports geöffnet sind. Fehlerdetails: ', 'email-newsletter' ).strip_tags(imap_last_error()) );
        } else {
            $i = 1;
            while ($i <= 5) {
                $i++;
                $MC = imap_check( $mbox );

                $this->write_log('bounce_test: find bounce attempt: '.$i);

                //get all emails
                $mails = imap_fetch_overview( $mbox, "1:{$MC->Nmsgs}", 0 );

                foreach ( $mails as $mail ) {
                    $this->write_log('bounce_test: subject: '.$mail->subject);
                    //Search test email on server
                    if( $mail->subject == 'Test-Connection-Bounce-'. $email_id ) {
                        imap_delete( $mbox, $mail->uid, FT_UID );
                        imap_expunge( $mbox );
                        imap_close( $mbox );

                        $this->write_log('bounce_test: subject found!');
                        die( __( 'Erfolgreich verbunden! Vergiss bitte nicht Deine Einstellungen zu speichern.', 'email-newsletter' ) );
                    }
                }
                imap_expunge( $mbox );
                imap_close( $mbox );

                sleep( 5 );
            }
            die(  __( 'Bounce-Testnachricht nicht gefunden!', 'email-newsletter' ) );
        }
    }


    /**
     * Send Confirm email for subscribe
     **/
     function do_double_opt_in( $member_id ){
        $message = '';
        if( isset( $this->settings['double_opt_in'] ) && $this->settings['double_opt_in'] ) {
            $member_data = $this->get_member( $member_id );

            $email_to           = $member_data['member_email'];
            $email_from         = $this->settings['from_email'];
            $email_from_name    = $this->settings['from_name'];
            $email_subject      = ( isset($this->settings['double_opt_in_subject']) && !empty($this->settings['double_opt_in_subject']) ) ? $this->settings['double_opt_in_subject'] : __('Bestätige bitte Dein Newsletter-Abonnement','email-newsletter');

            // Determine our locale to check for a specific template
            $locale = get_locale();
            $template = apply_filters( 'email_newsletter_email_double_optin_template', $this->plugin_dir . 'email-newsletter-files/emails/double_optin-'.$locale.'.html', $locale );
            if( file_exists( $template ) ) {
                $email_contents     = file_get_contents( $template );
            } else {
                ob_start();
                include($this->plugin_dir . "email-newsletter-files/emails/double_optin.php");
                $email_contents = ob_get_clean();
            }

            $replace = array(
                "from_name"=>$email_from_name,
                "CONFIRM_SUBSCRIPTION"=> $this->get_confirmation_link($member_id),
                "first_name"=>$member_data['member_fname'],
                "last_name"=>$member_data['member_lname'],
                "email"=>$member_data['member_email'],
            );

            foreach( $replace as $key=>$val ) {
                if( is_array( $val ) )continue;
                $email_contents = preg_replace( '/\{'.strtoupper( preg_quote( $key,'/' ) ).'\}/', $val, $email_contents );
                $email_subject = preg_replace( '/\{'.strtoupper( preg_quote( $key,'/' ) ).'\}/', $val, $email_subject );
            }

            $sent_status = $this->send_email( $email_from_name, $email_from, $email_to, $email_subject, $email_contents );
            $this->write_log('double opt in send status:'.$sent_status);
            return $sent_status;
        }

    }

    /**
     * Send Confirm email for subscribe
     **/
    function get_confirmation_link($member_id) {
        return add_query_arg( array('subscribe_page' => '1', 'subscribe_code' => md5( "sometext123" . $member_id ), 'subscribe_member_id' => $member_id), home_url() );
    }

    /**
     * Creating admin menu
     **/
    function admin_page() {

        $mu_cap = ( $this->is_plugin_active_for_network(plugin_basename($this->plugin_main_file)) ) ? 'manage_network_options' : 'view_newsletter_dashboard';

        if ( $this->settings ) {
            global $email_builder, $submenu;
            $possible_menu_parent = array(
                'view_newsletter_dashboard' => 'newsletters-dashboard',
                'save_newsletter' => 'newsletters',
                'edit_newsletter_group' => 'newsletters-groups',
                'view_newsletter_members' => 'newsletters-members',
                'save_newsletter_settings' => 'newsletters-settings'
                );
            $capability = 'read';
            $slug = 'newsletters-subscribes';
            foreach ($possible_menu_parent as $possible_capability => $possible_slug)
                if(current_user_can($possible_capability)) {
                    $capability = $possible_capability;
                    $slug = $possible_slug;
                    break;
                }

            add_menu_page( __( 'PS-eNewsletter', 'email-newsletter' ), __( 'PS-eNewsletter', 'email-newsletter' ), $capability, $slug, '', $this->plugin_url . 'email-newsletter-files/images/icon.png');
            add_submenu_page( $slug, __( 'Berichte', 'email-newsletter' ), __( 'Berichte', 'email-newsletter' ), 'view_newsletter_dashboard', 'newsletters-dashboard', array( &$this, 'newsletters_dashboard_page' ) );
            add_submenu_page( $slug, __( 'Newsletters', 'email-newsletter' ), __( 'Newsletters', 'email-newsletter' ), 'save_newsletter', 'newsletters', array( &$this, 'newsletters_page' ) );
            add_submenu_page( $slug, __( 'Neuer Newsletter', 'email-newsletter' ), __( 'Neuer Newsletter', 'email-newsletter' ), 'create_newsletter', 'admin.php?newsletter_builder_action=create_newsletter' );
            add_submenu_page( $slug, __( 'Gruppen', 'email-newsletter' ), __( 'Gruppen', 'email-newsletter' ), 'edit_newsletter_group', 'newsletters-groups', array( &$this, 'member_groups_page' ) );
            add_submenu_page( $slug, __( 'Abonnenten', 'email-newsletter' ), __( 'Abonnenten', 'email-newsletter' ), 'view_newsletter_members', 'newsletters-members',  array( &$this, 'members_page' ) );
            add_submenu_page( $slug, __( 'Einstellungen', 'email-newsletter' ), __( 'Einstellungen', 'email-newsletter' ), 'save_newsletter_settings', 'newsletters-settings', array( &$this, 'settings_page' ) );

            //menu for lowest level users
            add_submenu_page( $slug, __( 'Meine Abonnements', 'email-newsletter' ), __( 'Meine Abonnements', 'email-newsletter' ), 'read', 'newsletters-subscribes', array( &$this, 'newsletters_subscribe_page' ) );
        } else {
            //first start of plugin
            add_menu_page( __( 'PS-eNewsletter', 'email-newsletter' ), __( 'PS-eNewsletter', 'email-newsletter' ), $mu_cap, 'newsletters-settings' );
            add_submenu_page( 'newsletters-settings', __( 'Basis-Einstellung', 'email-newsletter' ), __( 'Basis-Einstellung', 'email-newsletter' ), $mu_cap, 'newsletters-settings', array( &$this, 'settings_page' ) );
        }
    }

    /**
     *  Tempalate of the Newsletters Dashboard page
     **/
    function newsletters_dashboard_page() {
        //including file for send newsletter
        if ( isset( $_REQUEST['newsletter_action'] ) && "send_newsletter" == $_REQUEST['newsletter_action'] && ( $_REQUEST['newsletter_id'] ||  $_REQUEST['send_id'] ) ) {
            require_once( $this->plugin_dir . "email-newsletter-files/page-send-newsletter.php" );
            return;
        }
        require_once( $this->plugin_dir . "email-newsletter-files/page-newsletters-dashboard.php" );
    }

    /**
     *  Tempalate of the Newsletters page
     **/
    function newsletters_page() {
        //including file for send newsletter
        if ( isset( $_REQUEST['newsletter_action'] ) && "send_newsletter" == $_REQUEST['newsletter_action'] && ( $_REQUEST['newsletter_id'] ||  $_REQUEST['send_id'] ) ) {
            require_once( $this->plugin_dir . "email-newsletter-files/page-send-newsletter.php" );
            return;
        }

        require_once( $this->plugin_dir . "email-newsletter-files/page-newsletters.php" );
    }

    /**
     *  Tempalate of the Groups list
     **/
    function member_groups_page() {
        require_once( $this->plugin_dir . "email-newsletter-files/page-groups.php" );
    }

    /**
     *  Tempalate of the Memebers page
     **/
    function members_page() {
        require_once( $this->plugin_dir . "email-newsletter-files/page-members.php" );
    }

    /**
     * Change group on the Memebers page
     **/
    function change_groups_ajax() {
        $users_group = $this->get_memeber_groups( $_REQUEST['member_id'] );
        if ( ! is_array( $users_group ) )
            $users_group = array();

        $groups = $this->get_groups();
            $content = "<p>".__( 'Gruppe für diesen Benutzer wählen:', 'email-newsletter' ) . "</p>";

            $member_data = $this->get_member( $_REQUEST['member_id'] );
            $subscribed = (!empty($member_data['unsubscribe_code'])) ? 'checked="checked"' : '';

            $content .= '<p><label><strong><input type="checkbox" name="groups_id[]" value="subscribed" ' . $subscribed . ' /> ' .__( 'Abonniert', 'email-newsletter' ). '</strong></label></p>';
            if ( 0 < count( $groups ) ) {
                $content .= "<p>";
                foreach( $groups as $group ){
                    if ( false === array_search ( $group['group_id'], $users_group ) )
                        $checked = '';
                    else
                        $checked = 'checked="checked"';
                    $content .= '<label><input type="checkbox" name="groups_id[]" value="' . $group['group_id'] . '" ' . $checked . ' /> ' . $group['group_name'] . '</label><br />';
                }
                $content .= "</p>";
            }
            else
                $content = "<p>".__( 'Bitte erstelle einige Gruppen.', 'email-newsletter' ) . "</p>";

        die($content);
    }

    /**
     *  Tempalate of the Settings page
     **/
    function settings_page() {
        require_once( $this->plugin_dir . "email-newsletter-files/page-settings.php" );
    }

    /**
     *  Tempalate of the Settings page
     **/
    function newsletters_subscribe_page() {
        require_once( $this->plugin_dir . "email-newsletter-files/page-subscribe.php" );
    }

    function email_newsletter_widgets_scripts() {
        wp_register_script( 'email-newsletter-widget-scripts', plugins_url( '/email-newsletter-files/js/widget_script.js', __FILE__ ), array( 'jquery' ), 4 );
        wp_enqueue_script( 'email-newsletter-widget-scripts' );

        $protocol = isset( $_SERVER["HTTPS"] ) ? 'https://' : 'http://'; //This is used to set correct adress if secure protocol is used so ajax calls are working
        $params = array(
            'ajax_url' => admin_url( 'admin-ajax.php', $protocol ),
            'empty_email' => __( 'Bitte Deine Admin E-Mail!', 'email-newsletter' ),
            'saving' => __( 'Speichere...', 'email-newsletter' )
        );

        wp_localize_script( 'email-newsletter-widget-scripts', 'email_newsletter_widget_scripts', $params );
    }

    function subscribe_widget($show_name = false, $show_groups = true, $subscribe_to_groups = array()) {
        global $email_newsletter;

        $current_user = wp_get_current_user();
        $groups = $this->get_groups(1);

        if ( isset($current_user->data->ID) ) {
            $member_id      = $this->get_members_by_wp_user_id( $current_user->data->ID );
            $member_data    = $this->get_member( $member_id );
            $only_public = (isset($this->settings['non_public_group_access']) && $this->settings['non_public_group_access'] == 'nobody') ? 1 : 0;
            $groups = $this->get_groups();

            if ( "" != $member_data['unsubscribe_code'] )
                $member_groups = $this->get_memeber_groups( $member_id );

            if ( !isset($member_groups) || ! is_array( $member_groups ) )
                $member_groups = array();

            if(!$subscribe_to_groups)
                $show_groups = true;
        }
        else
            $groups = $this->get_groups(1);

        if ( !isset($current_user->data->ID) ) {
            $view = "add_member";
        } else if ( $current_user->data && $subscribe_to_groups && !$show_groups ) {
            if( $member_groups && !array_diff($subscribe_to_groups, $member_groups) )
                $view = "unsubscribe_from_groups";
            else
                $view = "subscribe_to_groups";
        } else if ( isset( $member_data['unsubscribe_code'] ) && "" != $member_data['unsubscribe_code'] && 0 < $current_user->data->ID ) {
            $view = "manage_subscriptions";
        } else if ( $current_user->data && 0 < $current_user->data->ID ) {
            $view = "subscribe";
        } else {
            $view = "";
        }
        $return = '
        <div class="e-newsletter-widget">
            <div id="message" style="color:#000000; display:none; background-color: #FFFFE0;border-color: #E6DB55;margin: 5px 0 15px;-moz-border-radius: 3px 3px 3px 3px;border-style: solid;border-width: 1px;padding: 5px;"></div>

            <form action="" method="post" name="subscribes_form" id="subscribes_form">
                <input type="hidden" name="newsletter_action" id="newsletter_action" value="" />';
        if(is_array($subscribe_to_groups))
            foreach($subscribe_to_groups as $group_id )
                if(is_numeric($group_id))
                    $return .= '<input type="hidden" name="e_newsletter_auto_groups_id[]" value="'.$group_id.'" />';

        if($view != 'add_member')
            $return .= '
                <div id="add_member" class="e-newsletter-widget-screen" style="display:none;">';
        else
            $return .=
                '<div id="add_member" class="e-newsletter-widget-screen">';
        $return .= '
                    <p>
                        <label for="e_newsletter_email">'.__( 'Deine Email:', 'email-newsletter' ).'</label>
                        <input type="text" name="e_newsletter_email" id="e_newsletter_email" value="" />';
        if( isset($show_name) && $show_name )
            $return .= '
                        <br/>

                        <label for="e_newsletter_name">'.__( 'Dein Name:', 'email-newsletter' ).'</label>
                        <input type="text" name="e_newsletter_name" id="e_newsletter_name" />';
        $return .= '
                    </p>';
        if( $show_groups && count($groups) > 0 ) {
            $return .='
                        <h3>'.__( 'Newsletter abonnieren:', 'email-newsletter' ).'</h3>
                        <p>
                            <ul class="subscribe_groups" style="list-style: none outside none;">';
            foreach( ( array ) $groups as $group ) {
                if( ! $group['public'] ) continue;
                    $return .= '
                                    <li>

                                        <input type="checkbox" name="e_newsletter_groups_id[]" value="'.$group['group_id'].'" id="e_newsletter_groups_id_'.$group['group_id'].'" class="e_newsletter_groups_id_'.$group['group_id'].'" />
                                        <label for="e_newsletter_groups_id_'.$group['group_id'].'">'.$group['group_name'].'</label>

                                    </li>';
            }
            $return .= '
                            </ul>
                        </p>';

        }
        $return .='
                    <p>
                        <input type="submit" id="new_subscribe" class="enewletter_widget_submit" value="'.__( 'Newsletter abonnieren', 'email-newsletter' ).'" />
                    </p>

                </div>';



        if($view != 'subscribe_to_groups')
            $return .= '
                <div id="subscribe_to_groups" class="e-newsletter-widget-screen" style="display:none;">';
        else
            $return .='
                <div id="subscribe_to_groups" class="e-newsletter-widget-screen">';

        if( count($groups) > 0 )
            foreach( (array) $subscribe_to_groups as $subscribe_to_group_id )
                $return .= '
                    <input type="hidden" name="e_newsletter_add_groups_id[]" value="'.$subscribe_to_group_id.'"/>';

        $return .= '
                    <p>
                        <input type="submit" id="subscribe_to_groups" class="enewletter_widget_submit" value="'.__( 'Newsletter abonnieren', 'email-newsletter' ).'" />
                    </p>';
        $return .= '
                </div>';

        if($view != 'unsubscribe_from_groups')
            $return .= '
                <div id="unsubscribe_from_groups" class="e-newsletter-widget-screen" style="display:none;">';
        else
            $return .='
                <div id="unsubscribe_from_groups" class="e-newsletter-widget-screen">';

        if( count($groups) > 0 )
            foreach( (array) $subscribe_to_groups as $subscribe_to_group_id )
                $return .= '
                    <input type="hidden" name="e_newsletter_remove_groups_id[]" value="'.$subscribe_to_group_id.'"/>';

        $return .= '
                    <p>
                        <input type="submit" id="unsubscribe_from_groups" class="enewletter_widget_submit" value="'.__( 'Abmelden', 'email-newsletter' ).'" />
                    </p>';
        $return .= '
                </div>';



        if($view != 'manage_subscriptions')
            $return .= '
                <div id="manage_subscriptions" class="e-newsletter-widget-screen" style="display:none;">';
        else
            $return .='
                <div id="manage_subscriptions" class="e-newsletter-widget-screen">';
        $unsubscribe_code = isset( $member_data['unsubscribe_code'] ) ? $member_data['unsubscribe_code'] : '';
        $return .='
                    <input type="hidden" name="unsubscribe_code" id="unsubscribe_code" value="'.$unsubscribe_code.'" />';

        if( $show_groups && count($groups) > 0 ) {
            if( isset($only_public) && $only_public == 1 )
                foreach( (array) $groups as $group )
                    if (!$group['public'] && in_array($group['group_id'], $member_groups) )
                        $return .= '
                        <input type="hidden" name="e_newsletter_groups_id[]" value="'.$group['group_id'].'"/>';

            $return .= '
                        <h3>'.__( 'Newsletter abonnieren:', 'email-newsletter' ).'</h3>
                        <p>
                            <ul class="subscribe_groups" style="list-style: none outside none;">';
            foreach( (array) $groups as $group ){
                if ( isset($member_groups) && in_array($group['group_id'], $member_groups) )
                    $checked = 'checked="checked"';
                else
                    $checked = '';
                if(!isset($only_public) || ($only_public && $group['public']) || !$only_public)
                    $return .= '
                                    <li>
                                        <input type="checkbox" name="e_newsletter_groups_id[]" value="'.$group['group_id'].'" '.$checked.' id="e_newsletter_groups_id_'.$group['group_id'].'" class="e_newsletter_groups_id_'.$group['group_id'].'" />
                                        <label for="e_newsletter_groups_id_'.$group['group_id'].'">'.$group['group_name'].'</label>
                                    </li>';
            }
            $return .= '
                            </ul>
                        </p>
                    <p>
                        <input type="submit" id="save_subscribes" class="enewletter_widget_submit" value="'.__( 'Abonnements speichern', 'email-newsletter' ).'" />
                    </p>';
        }
        $return .= '
                <p>
                    <a href="#" id="unsubscribe" class="enewletter_widget_submit" >'.__( 'Abmelden', 'email-newsletter' ).'</a>
                </p>';
        $return .= '
                </div>';
        if($view != 'subscribe')
            $return .= '
                <div id="subscribe" class="e-newsletter-widget-screen" style="display:none;">';
        else
            $return .= '
                <div id="subscribe" class="e-newsletter-widget-screen">';
        $return .= '
                    <input type="submit" id="subscribe" class="enewletter_widget_submit" value="'.__( 'Newsletter abonnieren', 'email-newsletter' ).'" />
                </div>
            </form>
        </div><!--//e-newsletter-widget  -->';

        return $return;
    }

    function subscribe_shortcode( $atts ) {
        extract( shortcode_atts( array(
            'show_name' => false,
            'show_groups' => true,
            'subscribe_to_groups' => array(),
        ), $atts ) );

        if(!empty($subscribe_to_groups))
            $subscribe_to_groups = explode(',',$subscribe_to_groups);
        if($show_groups == 'false')
            $show_groups = false;

        $subscribe = $this->subscribe_widget($show_name, $show_groups, $subscribe_to_groups);

        return $subscribe;
    }

    function unsubscribe_message_shortcode( $atts ) {
        if(isset($_REQUEST['enewsletter_unsubscribed']) && isset($_REQUEST['message']))
            return $_REQUEST['message'];
        else
            return '';
    }

    function subscribe_message_shortcode( $atts ) {
        if(isset($_REQUEST['enewsletter_subscribed']) && isset($_REQUEST['message']))
            return $_REQUEST['message'];
        else
            return '';
    }


    function register_plugin_exporter($exporters) {
		$exporters['e-newsletter'] = array(
			'exporter_friendly_name' => 'E-Newsletter',
			'callback' => array( $this, 'plugin_exporter' ),
		);
		return $exporters;
    }
    function plugin_exporter($email) {
        $member = $this->get_member_by_email($email);
        if($member) {
            $member_groups = array();
            $groups_id = $this->get_memeber_groups( $member['member_id'] );
            if($groups_id) {
                foreach ( $groups_id as $group_id) {
                    $group = $this->get_group_by_id( $group_id );
                    $member_groups[] = $group['group_name'];
                }
            }
            $export_items = array();
            $export_items[] = array(
                'group_id' => 'enewsletter',
                'group_label' => 'E-Newsletter Subscription',
                'item_id' => 'user',
                'data' => array(
                    array(
                        'name' => __( 'First Name', 'appointments' ),
                        'value' => $member['member_fname'],
                    ),
                    array(
                        'name' => __( 'Last Name', 'appointments' ),
                        'value' => $member['member_lname'],
                    ),
                    array(
                        'name' => __( 'Email', 'appointments' ),
                        'value' => $member['member_email'],
                    ),
                    array(
                        'name' => __( 'Join Date', 'appointments' ),
                        'value' => get_date_from_gmt(date('Y-m-d H:i:s', $member['join_date'])),
                    ),
                    array(
                        'name' => __( 'Groups', 'appointments' ),
                        'value' => implode(', ', $member_groups),
                    ),
                ),
            );

            return array(
                'data' => $export_items,
                'done' => true,
            );
        }
        else {
            return;
        }
    }

    function register_plugin_eraser($erasers) {
		$erasers['e-newsletter'] = array(
			'eraser_friendly_name' => 'E-Newsletter',
			'callback' => array( $this, 'plugin_eraser' ),
		);
		return $erasers;
    }
    function plugin_eraser($email) {
        $member = $this->get_member_by_email($email);
        if($member) {
            $result = $this->delete_members(array($member['member_id']));

            return array(
                'items_removed' => 1,
                'items_retained' => 0,
                'messages' => array(__('PS-eNewsletter Abonnentendaten entfernt.', 'email-newsletter')),
                'done' => true,
            );
        }
        else {
            return;
        }
    }


    /**
     * Deprecated
     **/
    function confirm_subscibe_ajax() {
        wp_redirect(add_query_arg( array('subscribe_page' => '1', 'subscribe_code' => $_REQUEST['hash'], 'subscribe_member_id' => $_REQUEST['member_id']), home_url() ));
        die();
    }
    function unsubscribe_ajax() {
        $this->unsubscribe_by_code( $_REQUEST['unsubscribe_code'] );
        die();
    }
}
global $email_newsletter, $email_builder;
$email_newsletter = new Email_Newsletter();
$email_builder = new Email_Newsletter_Builder();

