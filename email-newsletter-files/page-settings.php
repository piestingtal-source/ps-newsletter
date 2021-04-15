<?php
if ( ! defined( 'ABSPATH' ) ) {
	die();
}?>
<?php
    $page_title =  __( 'eNewsletter Einstellungen', 'email-newsletter' );

    if ( !$this->settings ) {
        $page_title =  __( 'eNewsletter Plugin Installation', 'email-newsletter' );
        $mode = "install";
    }

    $default_tab = isset($mode) ? 'tabs-2' : 'tabs-1';

	global $email_newsletter;
	if (!class_exists('WpmuDev_HelpTooltips')) require_once $email_newsletter->plugin_dir . '/email-newsletter-files/class.wd_help_tooltips.php';
	$tips = new WpmuDev_HelpTooltips();
	$tips->set_icon_url($email_newsletter->plugin_url.'/email-newsletter-files/images/information.png');


    //Display status message
    if ( isset( $_GET['updated'] ) ) {
        ?><div id="message" class="updated fade"><p><?php echo urldecode( $_GET['message'] ); ?></p></div><?php
    }
?>


    <div class="wrap">
        <h2><?php echo $page_title; ?></h2>

        <form method="post" name="settings_form" id="settings_form" action="<?php echo admin_url( 'admin.php?page=newsletters-settings'); ?>">
            <input type="hidden" name="newsletter_action" id="newsletter_action" value="" />
            <input type="hidden" name="newsletter_setting_page" id="newsletter_setting_page" value="#tabs-1" />
            <?php if(isset($mode)) echo '<input type="hidden" name="mode"  value="'.$mode.'" />'; ?>

            <div class="newsletter-settings-tabs">

					<h3 id="newsletter-tabs" class="nav-tab-wrapper">
						<a href="#tabs-1" class="nav-tab nav-tab-active"><?php _e( 'Allgemeine Einstellungen', 'email-newsletter' ) ?></a>
						<a href="#tabs-2" class="nav-tab"><?php _e( 'Einstellungen für ausgehende E-Mails', 'email-newsletter' ) ?></a>
						<a href="#tabs-3" class="nav-tab"><?php _e( 'Bounce-Einstellungen', 'email-newsletter' ) ?></a>
						<a href="#tabs-4" class="nav-tab"><?php _e( 'Benutzerberechtigungen', 'email-newsletter' ) ?></a>
						<a href="#tabs-5" class="nav-tab"><?php _e( 'Shortcodes', 'email-newsletter' ) ?></a>
                        <?php if ( ! isset( $mode ) || "install" != $mode ): ?>
                            <a class="nav-tab" href="#tabs-6"><?php _e( 'Deinstallieren', 'email-newsletter' ) ?></a>
						 <?php endif; ?>
					</h3>
                    <div id="tabs-1" class="tab">
						<h3><?php _e( 'Standardeinstellungen für Informationen', 'email-newsletter' ) ?></h3>

						<table class="settings-form form-table">
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e( 'Absendername:', 'email-newsletter' ) ?>
                                </th>
                                <td>
                                    <input type="text" class="regular-text" name="settings[from_name]" value="<?php echo isset($this->settings['from_name']) ? esc_attr($this->settings['from_name']) : get_option( 'blogname' );?>" />
                                    <span class="description"><?php _e( 'Standardname "von" beim Versenden von Newslettern.', 'email-newsletter' ) ?></span>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e( 'Branding:', 'email-newsletter' ) ?>
                                </th>
                                <td>
                                    <textarea name="settings[branding_html]" class="branding-html" ><?php echo isset($this->settings['branding_html']) ? esc_textarea($this->settings['branding_html']) : "";?></textarea>
                                    <br />
                                    <span class="description"><?php _e( 'Das Standard-Branding-HTML/-Text wird oben in jede E-Mail eingefügt.', 'email-newsletter' ) ?> <?php _e( 'Dies kann leicht für jeden Newsletter geändert werden', 'email-newsletter' ) ?></span>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e( 'Kontakt Informationen:', 'email-newsletter' ) ?>
                                </th>
                                <td>
                                    <textarea name="settings[contact_info]" class="contact-information" ><?php echo isset($this->settings['contact_info']) ? esc_textarea($this->settings['contact_info']) : "";?></textarea>
                                    <br />
                                    <span class="description"><?php _e( 'Standardkontaktinformationen werden am Ende jeder E-Mail hinzugefügt.', 'email-newsletter' ) ?> <?php _e( 'It can be easily changed for each newsletter', 'email-newsletter' ) ?></span>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e( 'E-Mail im Browser anzeigen:', 'email-newsletter' ) ?>
                                </th>
                                <td>
                                    <textarea name="settings[view_browser]" class="view-browser" ><?php echo isset($this->settings['view_browser']) ? esc_textarea($this->settings['view_browser']) : __( '<a href="{VIEW_LINK}" title="E-Mail im Browser anzeigen">E-Mail im Browser anzeigen</a>', 'email-newsletter' ); ?></textarea>
                                    <br />
                                    <span class="description"><?php _e( 'Diese HTML-Nachricht wird ganz oben im Header des Newsletters angezeigt, sodass Benutzer E-Mails im Browser anzeigen können. Verwende "{VIEW_LINK}" als Link. Zum Deaktivieren leer lassen.', 'email-newsletter' ) ?></span>
                                </td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e( 'Vorschaumail an:', 'email-newsletter' ) ?>
                                </th>
                                <td>
                                    <input type="text" class="regular-text" name="settings[preview_email]" value="<?php echo isset($this->settings['preview_email']) ? esc_attr($this->settings['preview_email']) : $this->settings['from_email'];?>" />
                                    <span class="description"><?php _e( 'Standard-E-Mail-Adresse, an die Vorschauen gesendet werden sollen.', 'email-newsletter' ) ?></span>
                                </td>
                            </tr>
                        </table>

                        <h3><?php _e( 'Standardeinstellungen für das Abonnieren/Abbestellen von Benutzern', 'email-newsletter' ) ?></h3>

                        <table class="settings-form form-table">
                            <tr valign="top">
                                <th scope="row">
                                    <?php _e( 'Doppel Opt In:', 'email-newsletter' ) ?>
                                </th>
                                <td>
                                    <label for="settings[double_opt_in]"><?php _e( 'Aktivieren:', 'email-newsletter' ) ?></label>
                                    <input type="checkbox" name="settings[double_opt_in]" value="1" <?php checked('1',$this->settings['double_opt_in']); ?> />
                                    <label for="settings[double_opt_in]"><?php _e( 'Betreff:', 'email-newsletter' ) ?></label>
                                    <input type="text" class="regular-text" name="settings[double_opt_in_subject]" value="<?php echo (isset($this->settings['double_opt_in_subject']) && !empty($this->settings['double_opt_in_subject'])) ? esc_attr($this->settings['double_opt_in_subject']) : __( 'Bitte bestätige deine Email', 'email-newsletter' ).' ('.get_bloginfo('name').')'; ?>" />
                                    <span class="description"><?php _e( 'Wenn diese Option aktiviert ist, erhalten Abonnenten eine Bestätigungs-E-Mail mit dem konfigurierten Betreff, um Newsletter zu abonnieren (nur für nicht registrierte Benutzer). RECHTLICH DRINGEND EMPFOHLEN!', 'email-newsletter' ) ?>. <?php _e( 'Betreff angeben.', 'email-newsletter' ) ?></span>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">
                                    <?php _e( 'Standardgruppen:', 'email-newsletter' ) ?>
                                </th>
                                <td>
                                    <?php
                                    $groups = !isset($mode) ? $this->get_groups() : 0;

                                    if ( $groups ) {
                                        $this->settings['subscribe_groups'] = isset($this->settings['subscribe_groups']) ? explode(',', $this->settings['subscribe_groups']) : array();
                                    ?>
                                        <?php foreach( $groups as $group ) : ?>
                                            <label for="member[groups_id][]">
                                                <input type="checkbox" name="settings[subscribe_groups][<?php echo $group['group_id'];?>]" value="<?php echo $group['group_id'];?>" <?php if(in_array($group['group_id'], $this->settings['subscribe_groups'])) echo 'checked'; ?>/>
                                                <?php echo ( $group['public'] ) ? $group['group_name'] .' (public)' : $group['group_name']; ?>
                                            </label>
                                            <br />
                                        <?php endforeach; ?>
                                    <?php
                                    }
                                    else {
                                    ?>
                                        <p><?php _e( 'Du hast noch keine Abonnentengruppen erstellt.', 'email-newsletter' ); ?></p>
                                    <?php
                                    }
                                    ?>
                                    <span class="description"><?php _e( 'Standardgruppen, zu denen Benutzer nach dem Abonnement hinzugefügt werden sollen (auch wenn im Abonnement-Widget nichts ausgewählt ist).', 'email-newsletter' ) ?></span>
                                </td>
                            </tr>

                            <tr valign="top">
                                <th scope="row">
                                    <?php _e( 'Willkommens Newsletter:', 'email-newsletter' ) ?>
                                </th>
                                <td>
                                    <select name="settings[subscribe_newsletter]">
                                        <option value=""><?php _e( 'Deaktivieren', 'email-newsletter' ) ?></option>
                                        <?php
                                        $newsletters = ($mode != 'install') ? $this->get_newsletters() : 0;

                                        if($newsletters)
                                            foreach( $newsletters as $key => $newsletter ) {
                                                if (strlen($newsletter['subject']) > 30)
                                                $newsletter['subject'] = substr($newsletter['subject'], 0, 27) . '...';
                                                echo '<option value="'.$newsletter['newsletter_id'].'" '.selected( $this->settings['subscribe_newsletter'], $newsletter['newsletter_id'], false).'>'.$newsletter['newsletter_id'].': '.$newsletter['subject'].'</option>';
                                            }
                                        ?>
                                    </select>
                                    <span class="description"><?php _e( 'Standard-Newsletter, der im Benutzerabonnement gesendet wird.', 'email-newsletter' ) ?></span>
                                </td>
                            </tr>

                           <tr valign="top">
                                <th scope="row">
                                    <?php _e( 'WordPress-Benutzerregistrierung:', 'email-newsletter' ) ?>
                                </th>
                                <td>
                                    <?php
                                    if(!isset($this->settings['wp_user_register_subscribe']))
                                        $this->settings['wp_user_register_subscribe'] = 1;
                                    ?>
                                    <select name="settings[wp_user_register_subscribe]">
                                        <option value="1"<?php selected( $this->settings['wp_user_register_subscribe'], 1); ?>><?php _e( 'Subscribe', 'email-newsletter' ) ?></option>
                                        <option value="0"<?php selected( $this->settings['wp_user_register_subscribe'], 0); ?>><?php _e( 'Disable', 'email-newsletter' ) ?></option>
                                    </select>
                                    <span class="description"><?php _e( 'Wähle ob Benutzer, die sich (mit WordPress) auf Deiner Website registrieren, automatisch den Newsletter abonnieren.', 'email-newsletter' ) ?></span>
                                </td>
                            </tr>

                           <tr valign="top">
                                <th scope="row">
                                    <?php _e( 'Abonnieren Seiten-ID:', 'email-newsletter' ) ?>
                                </th>
                                <td>
                                    <input class="small-text" type="number" name="settings[subscribe_page_id]" value="<?php echo isset($this->settings['subscribe_page_id']) ? esc_attr($this->settings['subscribe_page_id']) : '';?>" />
                                    <span class="description"><?php _e( 'Füge die ID der Seite hinzu, welche Du nach dem Abonnieren des Benutzers anzeigen möchtest. Du kannst den Shortcode [enewsletter_subscribe_message] auf dieser Seite verwenden, um die Abonnementstatusmeldung anzuzeigen. Zum Deaktivieren leer lassen.', 'email-newsletter' ) ?></span>
                                </td>
                            </tr>

                           <tr valign="top">
                                <th scope="row">
                                    <?php _e( 'Abbestellen Seiten ID:', 'email-newsletter' ) ?>
                                </th>
                                <td>
                                    <input class="small-text" type="number" name="settings[unsubscribe_page_id]" value="<?php echo isset($this->settings['unsubscribe_page_id']) ? esc_attr($this->settings['unsubscribe_page_id']) : '';?>" />
                                    <span class="description"><?php _e( 'ID der Seite hinzufügen, die angezeigt werden soll, nachdem der Benutzer sich abgemeldet hat. Du kannst den Shortcode [enewsletter_unsubscribe_message] verwenden, um die Statusmeldung zum Abbestellen anzuzeigen. Zum Deaktivieren leer lassen.', 'email-newsletter' ) ?></span>
                                </td>
                            </tr>
                        </table>

                    </div>

                    <div id="tabs-2" class="tab">
                        <h3><?php _e( 'Einstellungen für ausgehende SMTP-E-Mails', 'email-newsletter' ) ?></h3>
                        <table class="settings-form form-table">
                            <tbody>
                                <tr valign="top">
                                    <th scope="row">
                                        <?php echo _e( 'E-Mail-Versandmethode:', 'email-newsletter' ); ?>
                                    </th>
                                    <td>
                                        <label id="tip_smtp">
                                            <input type="radio" name="settings[outbound_type]" id="smtp_method" value="smtp" class="email_out_type" <?php echo (!isset($this->settings['outbound_type']) || $this->settings['outbound_type'] == 'smtp') ? 'checked="checked"' : '';?> /><?php echo _e( 'SMTP (empfohlen)', 'email-newsletter' );?>
                                        </label>

										<?php $tips->bind_tip(__("Mit der SMTP-Methode kannst Du Deinen SMTP-Server (oder Google Mail, Yahoo, Hotmail usw.) zum Senden von Newslettern und E-Mails verwenden. Dies ist normalerweise die beste Wahl, insbesondere wenn Dein Host Einschränkungen beim Senden von E-Mails hat und Du vermeiden möchtest, als SPAM-Absender auf die schwarze Liste gesetzt zu werden",'email-newsletter'), '#tip_smtp'); ?>

                                        <label id="tip_php">
                                            <input type="radio" name="settings[outbound_type]" value="mail" class="email_out_type" <?php echo (isset($this->settings['outbound_type']) && $this->settings['outbound_type'] == 'mail') ? 'checked="checked"' : '';?> /><?php echo _e( 'PHP Mail', 'email-newsletter' );?>
                                        </label>
										<?php $tips->bind_tip(__( "Diese Methode verwendet PHP-Funktionen zum Versenden von Newslettern und E-Mails. Sei vorsichtig, da einige Hosts möglicherweise Einschränkungen für die Verwendung dieser Methode festlegen. Wenn Du die Einstellungen Deines Servers nicht bearbeiten kannst, empfehlen wir die Verwendung der SMTP-Methode, um optimale Ergebnisse zu erzielen!", 'email-newsletter' ), '#tip_php'); ?>

                                        <label id="tip_wpmail">
                                            <input type="radio" name="settings[outbound_type]" value="wpmail" class="email_out_type" <?php echo (isset($this->settings['outbound_type']) && $this->settings['outbound_type'] == 'wpmail') ? 'checked="checked"' : '';?> /><?php echo _e( 'WP Mail', 'email-newsletter' );?>
                                        </label>
                                        <?php $tips->bind_tip(__( "Diese Methode verwendet Standardfunktionen für WordPress-E-Mails zum Senden von Newslettern und E-Mails. Du kannst andere Plugins zum Senden von E-Mails verwenden, aber möglicherweise funktioniert die Absprungprüfung nicht mehr.", 'email-newsletter' ), '#tip_wpmail'); ?>
 
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        <?php _e( 'Von der Email Adresse:', 'email-newsletter' ) ?>
                                    </th>
                                    <td>
                                        <input type="text" id="smtp_from" class="regular-text" name="settings[from_email]" value="<?php $default_domain = parse_url(home_url()); echo esc_attr( (isset($this->settings['from_email']) && !empty($this->settings['from_email'])) ? $this->settings['from_email'] : 'newsletter@'.$default_domain['host'] );?>" />
                                        <span class="description"><?php _e( 'Standard-E-Mail-Adresse "von" beim Versenden von Newslettern.', 'email-newsletter' ) ?></span><br/>
                                        <span class="red description"><?php _e( 'Hinweis: Für die SMTP-Methode solltest Du unter "Von E-Mail" nur E-Mails verwenden, die sich auf Deinen SMTP-Server beziehen!', 'email-newsletter' ) ?></span><br/>
                                        <span class="red description"><?php _e( 'Hinweis 2: Für die PHP-Mail-Methode solltest Du unter "Von E-Mail" nur E-Mails mit einer für Deinen Server konfigurierten Domäne verwenden (z.B: newsletter@MEINE.DOMAIN)!', 'email-newsletter' ) ?></span>
                                    </td>
                                </tr>
                            </tbody>

                            <tbody class="email_out email_out_smtp">
                                <tr valign="top">
                                    <th scope="row"><?php _e( 'SMTP-Ausgangsserver', 'email-newsletter' ) ?>:</th>
                                    <td>
                                        <input type="text" id="smtp_host" class="regular-text" name="settings[smtp_host]" value="<?php echo isset($this->settings['smtp_host']) ? esc_attr($this->settings['smtp_host']) : '';?>" />
                                        <span class="description"><?php _e( 'Der Hostname für das SMTP-Konto, z. B.: Mail.', 'email-newsletter' ) ?><?php echo $_SERVER['HTTP_HOST'];?></span>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e( 'SMTP-Benutzername:', 'email-newsletter' ) ?></th>
                                    <td>
                                        <input type="text" id="smtp_username" class="regular-text" name="settings[smtp_user]" value="<?php echo isset($this->settings['smtp_user']) ? esc_attr($this->settings['smtp_user']) : '';?>" />
                                        <span class="description"><?php _e( '(leer lassen für keinen)', 'email-newsletter' ) ?></span>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e( 'SMTP Passwort:', 'email-newsletter' ) ?></th>
                                    <td>
                                        <input type="password" id="smtp_password" class="regular-text" name="settings[smtp_pass]" value="<?php echo ( isset( $this->settings['smtp_pass'] ) && '' != $this->settings['smtp_pass'] ) ? '********' : ''; ?>" />
                                        <span class="description"><?php _e( '(leer lassen für keines)', 'email-newsletter' ); if(isset( $this->settings['smtp_pass'] ) && '' != $this->settings['smtp_pass']) _e( ' (Aus Sicherheitsgründen stimmt die gespeicherte Kennwortlänge nicht mit der Vorschau überein)', 'email-newsletter' ); ?></span>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e( 'SMTP Port', 'email-newsletter' ) ?>:</th>
                                    <td>
                                        <input type="text" id="smtp_port" name="settings[smtp_port]" value="<?php echo isset($this->settings['smtp_port']) ? esc_attr($this->settings['smtp_port']) : '';?>" />
                                        <span class="description"><?php _e( 'Standard ist 25.  Gmail benutzt 465 oder 587', 'email-newsletter' ) ?></span>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e( 'Sicheres SMTP?', 'email-newsletter' ) ?>:</th>
                                    <td>
                                        <?php
                                        if(!isset($this->settings['smtp_secure_method']))
                                            $this->settings['smtp_secure_method'] = 0;
                                        ?>
                                        <select id="smtp_security" name="settings[smtp_secure_method]" >
                                            <option value="0" <?php selected('0',$this->settings['smtp_secure_method']); ?>><?php _e( 'Nein', 'email-newsletter' ) ?></option>
                                            <option value="ssl" <?php selected('ssl',$this->settings['smtp_secure_method']); ?>><?php _e( 'SSL', 'email-newsletter' ) ?></option>
                                            <option value="tls" <?php selected('tls',$this->settings['smtp_secure_method']); ?>><?php _e( 'TLS', 'email-newsletter' ) ?></option>
                                        </select>
                                        <span class="description"><?php _e( 'Wähle den optionalen Verbindungstyp', 'email-newsletter' ) ?></span>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><div id="test_smtp_loading"></div></th>
                                    <td>
                                        <input class="button button-secondary" type="button" name="" id="test_smtp_conn" value="<?php _e( 'Test Verbindung', 'email-newsletter' ) ?>" />
                                        <span class="description"><?php _e( 'Wir senden Test-E-Mails an die konfigurierte E-Mail-Adresse.', 'email-newsletter' ) ?></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                        <table class="settings-form form-table">
                            <h3><?php _e( 'CRON Email Versandeinstellungen', 'email-newsletter' ) ?></h3>
                            <tbody>
                                <tr valign="top">
                                    <th scope="row">
                                        <?php _e( 'CRON Email Versand:', 'email-newsletter' ) ?>
                                    </th>
                                    <td>
                                        <?php
                                        if(!isset($this->settings['cron_enable']))
                                            $this->settings['cron_enable'] = 1;
                                        ?>
                                        <select name="settings[cron_enable]" >
                                            <option value="1" <?php selected('1',esc_attr($this->settings['cron_enable'])); ?>><?php _e( 'Aktiviert', 'email-newsletter' ) ?></option>
                                            <option value="2" <?php selected('2',esc_attr($this->settings['cron_enable'])); ?>><?php _e( 'Deaktiviert', 'email-newsletter' ) ?></option>
                                        </select>
                                        <span class="description"><?php _e( "('Deaktiviert' - Verwende CRON nicht zum Senden von E-Mails)", 'email-newsletter' ) ?></span>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        <?php _e( 'Einschränkungen:', 'email-newsletter' ) ?>
                                    </th>
                                    <td>
                                        <?php _e( 'Senden', 'email-newsletter' ) ?>
                                        <input class="small-text" type="number" name="settings[send_limit]" value="<?php echo isset($this->settings['send_limit']) ? esc_attr($this->settings['send_limit']) : '';?>" />
                                        <small class="description"><?php _e( '(0 oder leer für unlimitiert)', 'email-newsletter' ) ?></small>
                                        <?php _e( 'Emails pro', 'email-newsletter' ) ?>
                                        <?php
                                        if(!isset($this->settings['cron_time']))
                                            $this->settings['cron_time'] = 1;
                                        ?>
                                        <select name="settings[cron_time]" >
                                            <option value="1" <?php echo ( 1 == $this->settings['cron_time'] ) ? 'selected="selected"' : ''; ?> ><?php _e( 'Stunfr', 'email-newsletter' ) ?></option>
                                            <option value="2" <?php echo ( 2 == $this->settings['cron_time'] ) ? 'selected="selected"' : ''; ?> ><?php _e( 'Tag', 'email-newsletter' ) ?></option>
                                            <option value="3" <?php echo ( 3 == $this->settings['cron_time'] ) ? 'selected="selected"' : ''; ?> ><?php _e( 'Monat', 'email-newsletter' ) ?></option>
                                        </select>
                                        <?php _e( 'und warte', 'email-newsletter' ) ?>
                                        <input class="small-text" type="number" name="settings[cron_wait]" value="<?php echo isset($this->settings['cron_wait']) ? esc_attr($this->settings['cron_wait']) : 1;?>" />
                                        <?php _e( 'Sekunde(n) zwischen jeder E-Mail', 'email-newsletter' ) ?>.
                                    </td>
                                </tr>
							</tbody>
                        </table>
                    </div>

                    <div id="tabs-3" class="tab">
                        <h3><?php _e( 'Bounce Einstellungen', 'email-newsletter' ) ?></h3>
						<?php
						if(!function_exists('imap_open')) {
						?>

	                    <p><?php _e( 'Bitte aktiviere die PHP-Erweiterung "IMAP", damit Bounce funktioniert.', 'email-newsletter' ) ?></p>

						<?php
						}
						else {
						?>
                        <p><?php _e( 'Hiermit wird gesteuert, wie Bounce-E-Mails vom System verarbeitet werden. Bitte erstelle ein neues separates POP3-E-Mail-Konto, um Bounce-E-Mails zu verarbeiten. Gib diese POP3-E-Mail-Details unten ein.', 'email-newsletter' ) ?></p>
                        <table class="settings-form form-table">
                            <tbody>
                                <tr valign="top">
                                    <th scope="row"><?php _e( 'Email Addresse:', 'email-newsletter' ) ?></td>
                                    <td>
                                        <input type="text" name="settings[bounce_email]" id="bounce_email" class="regular-text" value="<?php echo isset($this->settings['bounce_email']) ? esc_attr($this->settings['bounce_email']) : '';?>" />
                                        <span class="description"><?php _e( 'E-Mail-Adresse, an die standardmäßig Bounce-E-Mails gesendet werden (möglicherweise vom Server überschrieben)', 'email-newsletter' ) ?></span>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e( 'POP3 Host:', 'email-newsletter' ) ?></th>
                                    <td>
                                        <input type="text" name="settings[bounce_host]" id="bounce_host" class="regular-text" value="<?php echo isset($this->settings['bounce_host']) ? esc_attr($this->settings['bounce_host']) : '';?>" />
                                        <span class="description"><?php _e( 'Der Hostname für das POP3-Konto, z. B.: mail.', 'email-newsletter' ) ?><?php echo $_SERVER['HTTP_HOST'];?></span>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e( 'POP3 Port', 'email-newsletter' ) ?>:</th>
                                    <td>
                                        <input type="text" name="settings[bounce_port]" id="bounce_port" value="<?php echo isset($this->settings['bounce_port']) ? esc_attr($this->settings['bounce_port']) : '110';?>" size="2" />
                                        <span class="description"><?php _e( 'Der Standardwert ist 110 oder 995 bei aktiviertem SSL', 'email-newsletter' ) ?></span>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e( 'POP3 Benutzername:', 'email-newsletter' ) ?></th>
                                    <td>
                                        <input type="text" name="settings[bounce_username]" id="bounce_username" class="regular-text" value="<?php echo isset($this->settings['bounce_username']) ? esc_attr($this->settings['bounce_username']) : '';?>" />
                                        <span class="description"><?php _e( 'Benutzername für dieses Bounce-E-Mail-Konto (normalerweise identisch mit der oben angegebenen E-Mail-Adresse) ', 'email-newsletter' ) ?></span>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><?php _e( 'POP3 Passwort:', 'email-newsletter' ) ?></th>
                                    <td>
                                        <input type="password" name="settings[bounce_password]" id="bounce_password" class="regular-text" value="<?php echo ( isset( $this->settings['bounce_password'] ) && '' != $this->settings['bounce_password'] ) ? '********' : ''; ?>" />
                                        <span class="description"><?php _e( 'Passwort für den Zugriff auf dieses Bounce-E-Mail-Konto', 'email-newsletter' ); if(isset( $this->settings['bounce_password'] ) && '' != $this->settings['bounce_password']) _e( ' (Aus Sicherheitsgründen stimmt die gespeicherte Kennwortlänge nicht mit der Vorschau überein.)', 'email-newsletter' ); ?></span>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        <?php _e( 'Sicheres POP3?:', 'email-newsletter' );?>
                                    </th>
                                    <td>
                                        <?php
                                        if(!isset($this->settings['bounce_security']))
                                            $this->settings['bounce_security'] = '';
                                        ?>
                                        <select name="settings[bounce_security]" id="bounce_security" >
                                            <option value="" <?php echo ( '' == $this->settings['bounce_security'] ) ? 'selected="selected"' : ''; ?> ><?php _e( 'Nein', 'email-newsletter' ) ?></option>
                                            <option value="/ssl" <?php echo ( '/ssl' == $this->settings['bounce_security'] ) ? 'selected="selected"' : ''; ?> ><?php _e( 'SSL', 'email-newsletter' ) ?></option>
                                        </select>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row"><div id="test_bounce_loading"></div></th>
                                    <td>
                                        <input class="button button-secondary" type="button" name="" id="test_bounce_conn" value="<?php _e( 'Test Verbindung', 'email-newsletter' ) ?>" />
                                        <span class="description"><?php _e( 'Wir senden eine Test-E-Mail an die Bounce-Adresse und versuchen, diese E-Mail zu lesen und danach zu löschen (dieser Teil ist möglicherweise nicht möglich).', 'email-newsletter' ) ?></span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
						<?php
						}
						?>
                    </div>
					<div id="tabs-4" class="tab">
						<?php global $wp_roles; ?>
						<h3><?php _e('Benutzerberechtigungen','email-newsletter'); ?></h3>
						<p><?php _e('Hier kannst Du die gewünschten Berechtigungen für jede Benutzerrolle auf Deiner Webseite festlegen','email-newsletter'); ?></p>
						<div class="metabox-holder" id="newsletter_user_permissions">
							<?php foreach($wp_roles->get_names() as $name => $label) : ?>
								<?php if($name == 'administrator') continue; ?>
								<?php $role_obj = get_role($name); ?>
								<div class="postbox">
									<h3 class="hndle"><span><?php echo $label; ?></span></h3>
									<div class="inside">
										<table class="widefat permissionTable">
											<thead>
												<tr valign="top">
													<th style="" class="manage-column column-cb check-column" scope="col"><input type="checkbox"></th>
													<th><?php _e('Berechtigung','email-newsletter'); ?></th>
												</tr>
											</thead>
											<tbody>
												<?php foreach($this->capabilities as $key => $label) : ?>
													<tr valign="top">
														<th class="check-column" scope="row">
															<input id="<?php echo $name.'_'.$key; ?>" type="checkbox" value="1" name="settings[email_caps][<?php echo $key; ?>][<?php echo $name; ?>]" <?php checked(isset($wp_roles->roles[$name]['capabilities'][$key]) ? $wp_roles->roles[$name]['capabilities'][$key] : '',true); ?> />
														</th>
														<th style="" class="manage-column column-<?php echo $key; ?>" id="<?php echo $key; ?>" scope="col">
															<label for="<?php echo $name.'_'.$key; ?>"><?php echo $label; ?></label>
														</th>
													</tr>
												<?php endforeach; ?>
											</tbody>
										</table>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
                        <h3><?php _e( 'Gruppenberechtigungen', 'email-newsletter' ) ?></h3>
                        <table class="settings-form form-table">
                            <tbody>
                                <tr valign="top">
                                    <th scope="row"><?php _e( 'Öffentlicher Gruppenzugriff:', 'email-newsletter' ) ?></td>
                                    <td>
                                        <?php
                                        if(!isset($this->settings['non_public_group_access']))
                                            $this->settings['non_public_group_access'] = 'registered';
                                        ?>
                                        <select id="non_public_group_access" name="settings[non_public_group_access]" >
                                            <option value="registered" <?php selected('registered',$this->settings['non_public_group_access']); ?>><?php _e( 'Registrierte Benutzer', 'email-newsletter' ) ?></option>
                                            <option value="nobody" <?php selected('nobody',$this->settings['non_public_group_access']); ?>><?php _e( 'Niemand', 'email-newsletter' ) ?></option>
                                        </select>
                                        <span class="description"><?php _e( 'Wähle aus, welcher Benutzertyp nicht öffentliche Gruppen abonnieren kann. <small>Beachte dass Benutzer weiterhin zu allen Arten von Gruppen auf der Administrationsseite für eNewsletter-Abonnenten hinzugefügt werden können.</small>', 'email-newsletter' ) ?></span>
                                   </td>
                                </tr>
                            </tbody>
                        </table>
					</div>
                    <div id="tabs-5" class="tab">
                    <h3><?php _e( 'Shortcode Benutzung', 'email-newsletter' ) ?></h3>
                    <p><?php _e('Hier erfährst Du, wie Du Deinn Posts, Seiten und Themenvorlagen E-Newsletter-Shortcodes hinzufügst.','email-newsletter'); ?></p>
                    <div class="shortcode-help">
                        <p><?php _e('Mit dem folgenden Shortcode kannst Du das Anmeldeformular überall dort einfügen, wo Du es benötigst.'); ?></p>
                        <p><code>[enewsletter_subscribe]</code></p>
                        <p><?php _e('Der Shortcode enthält 3 Parameter, die Du anpassen kannst.'); ?></p>
                        <ul>
                            <li><strong>show_name</strong> <?php _e('Aktiviert/Deaktiviert das Feld "Name" im Formular für Webseiten-Besucher.'); ?></li>
                            <li><strong>show_groups</strong> <?php _e('Aktiviert/Deaktiviert die Gruppenauswahl für Webseiten-Besucher.'); ?></li>
                            <li><strong>subscribe_to_groups</strong> <?php _e('Abonniert Benutzer automatisch für die durch die ID angegebenen Gruppen.'); ?></li>
                        </ul>
                        <p><?php _e('Der wie folgt konfigurierte Shortcode würde beispielsweise die Kontrollkästchen für die Gruppenauswahl ausblenden, den Benutzer automatisch Gruppen mit den angegebenen IDs abonnieren und nach dem Namen des Besuchers fragen.'); ?>
                            <p><code>[enewsletter_subscribe show_name="1" show_groups="0" subscribe_to_groups="1,5"]</code></p>
                        <p><?php _e('Verwende den Shortcode, um das Abonnementformular zu einem Post- oder Seiteninhalt hinzuzufügen oder es sogar in benutzerdefinierte Seitenvorlagen mit zu integrieren'); ?> <a href="https://developer.wordpress.org/reference/functions/do_shortcode/" target="_blank">do_shortcode function</a>.</p>
                        <p><?php _e('Verwende den folgenden Shortcode, um die Bestätigungsmeldung <em>abonniert</em> auf der Seite anzuzeigen, die unter <strong>Allgemeine Einstellungen -> ID für abonnierte Seiten</strong> definiert ist.'); ?></p>
                        <p><code>[enewsletter_subscribe_message]</code></p>
                        <p><?php _e('Verwende den folgenden Shortcode, um die Bestätigungsmeldung <em>abgemeldet</em> auf der Seite anzuzeigen, die unter <strong>Allgemeine Einstellungen -> Seiten-ID abbestellen</strong> definiert ist.'); ?></p>
                        <p><code>[enewsletter_unsubscribe_message]</code></p>
                    </div>
                    </div>
                    <?php if ( ! isset( $mode ) || "install" != $mode ): ?>
                    <div id="tabs-6" class="tab">
                        <h3><?php _e( 'Deinstallieren', 'email-newsletter' ) ?></h3>
                        <p><?php _e( 'Hier kannst Du alle mit dem Plugin verknüpften Daten aus der Datenbank löschen, betrifft auch Deine Maillisten und Adressen.', 'email-newsletter' ) ?></p>
                        <p>
                            <input class="button button-secondary" type="button" name="uninstall" id="uninstall" value="<?php _e( 'Daten löschen', 'email-newsletter' ) ?>" />
                            <span class="description" style="color: red;"><?php _e( "Lösche alle Plugin-Daten aus der Datenbank.", 'email-newsletter' ) ?></span>
                            <div id="uninstall_confirm" style="display: none;">
								<p>
									<span class="description"><?php _e( 'Bitte bestätige das endgültige Löschen all Deiner Newsletter-Daten', 'email-newsletter' ) ?></span>
									<br />
									<input class="button button-secondary" type="button" name="uninstall" id="uninstall_no" value="<?php _e( 'ABBRECHEN', 'email-newsletter' ) ?>" />
									<input class="button button-secondary" type="button" name="uninstall" id="uninstall_yes" value="<?php _e( 'ICH BESTÄTIGE', 'email-newsletter' ) ?>" />
								</p>
                            </div>
                        </p>
                    </div>
                    <?php endif; ?>

            </div><!--/.newsletter-tabs-settings-->

            <p class="submit">
            <?php if ( isset( $mode ) && "install" == $mode ) { ?>
                <input class="button button-primary" type="button" name="install" id="install" value="<?php _e( 'Installieren', 'email-newsletter' ) ?>" />
            <?php } else { ?>
                <input class="button button-primary" type="button" name="save" value="<?php _e( 'Alle Einstellungen speichern', 'email-newsletter' ) ?>" />
            <?php } ?>
			</p>

        </form>

    </div><!--/wrap-->