<?php
/**
 * Plugin Name: Plugin_Cookie
 * Plugin URI: https://www.linkedin.com/in/julien-lussiez-557a79209/
 * Description: Plugin Wordpress de cookie et de RGPD
 * Author: Deruelle Théo, Lussiez Julien, Sturaro Mathéo
 * Licence: GPL-2.0+
 */

if (!function_exists('tarteaucitron_init')) {
    function tarteaucitron_init()
    {
        wp_enqueue_script('cookie-script', plugin_dir_url(__FILE__) . 'tarteaucitron/tarteaucitron.js', array(), '1.0.0', true);

        // Enregistrez vos données de base de données et transmettez-les à votre script
        global $wpdb;
        $table_name = $wpdb->prefix . 'tarteaucitron_options';
        $options = $wpdb->get_row("SELECT * FROM $table_name");
        $script_data = array(
            'privacyUrl' => $options->privacyUrl,
            'bodyPosition' => $options->bodyPosition,
        );
        wp_localize_script('cookie-script', 'cookie_data', $script_data);

        wp_enqueue_script('cookie-load', plugin_dir_url(__FILE__) . '/assets/js/script.js', array('cookie-script'), '1.0.0', true);
    }

    add_action('wp_enqueue_scripts', 'tarteaucitron_init');
}

function my_custom_admin_styles()
{
    wp_enqueue_style('custom-admin-styles', get_stylesheet_directory_uri() . '../../twentytwentyfour/admin-custom.css'); // Assurez-vous que le chemin vers votre fichier CSS est correct
}
add_action('admin_enqueue_scripts', 'my_custom_admin_styles');

function menu_cookie()
{
    add_menu_page(
        'Tarteaucitron',
        'Gestion des paramètres du plugin',
        'manage_options',
        'cookie-settings',
        'display_cookie_settings_page',
        'dashicons-admin-generic',
        66
    );

    add_submenu_page(
        'cookie-settings',
        'Consentement Google Analytics',
        'Consentement Google Analytics',
        'manage_options',
        'consentement_google_analytics',
        'diplay_preferences_consentement_google_analytics'
    );
}

add_action('admin_menu', 'menu_cookie');

function display_cookie_settings_page()
{
    ?>
    <div class="wrap">
        <h1>Gestion des paramètres du plugin</h1>
        <?php afficher_formulaire_parametres(); ?>
    </div>
    <?php
}

function afficher_formulaire_parametres()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'tarteaucitron_options';
    $options = $wpdb->get_row("SELECT * FROM $table_name");

    if ($options === null) {
        $wpdb->insert($table_name, array('privacyUrl' => '', 'bodyPosition' => 'bottom'));
        $options = (object) array('privacyUrl' => '', 'bodyPosition' => 'bottom');
    }

    ?>
    <form method="post" action="admin-post.php">
        <?php
        wp_nonce_field('mettre_a_jour_parametres_tarteaucitron');
        foreach ($options as $key => $value) {
            if ($key === 'id' || strpos($key, '_hidden') !== false) {
                continue;
            }

            if (
                in_array(
                    $key,
                    array(
                        'groupServices',
                        'showDetailsOnClick',
                        'showAlertSmall',
                        'cookieslist',
                        'showIcon',
                        'adblocker',
                        'DenyAllCta',
                        'AcceptAllCta',
                        'highPrivacy',
                        'handleBrowserDNTRequest',
                        'removeCredit',
                        'moreInfoLink',
                        'useExternalCss',
                        'useExternalJs',
                        'mandatory',
                        'mandatoryCta',
                    )
                )
            ) {
                $input_type = 'checkbox';
                $checked = $value ? 'checked="checked"' : '';
                echo "<label><input type='hidden' name='{$key}_hidden' value='0'><input type='$input_type' name='$key' $checked> $key</label><br>";
            } else {
                echo "<label><input type='text' name='$key' value='$value'> $key</label><br>";
            }
        }
        ?>
        <input type="hidden" name="action" value="mettre_a_jour_parametres_tarteaucitron">
        <input type="submit" value="Enregistrer les paramètres" class="button button-primary">
    </form>
    <?php
}

function mettre_a_jour_cases_a_cocher()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'tarteaucitron_options';

    $boolean_keys = array(
        'groupServices',
        'showDetailsOnClick',
        'showAlertSmall',
        'cookieslist',
        'showIcon',
        'adblocker',
        'DenyAllCta',
        'AcceptAllCta',
        'highPrivacy',
        'handleBrowserDNTRequest',
        'removeCredit',
        'moreInfoLink',
        'useExternalCss',
        'useExternalJs',
        'mandatory',
        'mandatoryCta',
    );

    foreach ($boolean_keys as $key) {
        $updated_value = isset ($_POST[$key]) && $_POST[$key] === 'on' ? 1 : 0;
        $wpdb->update($table_name, array($key => $updated_value), array('id' => 1));
    }
}


function mettre_a_jour_champs_texte()
{
    global $wpdb;

    $table_name = $wpdb->prefix . 'tarteaucitron_options';

    $text_keys = array(
        'privacyUrl',
        'bodyPosition',
        'hashtag',
        'cookieName',
        'orientation',
        'serviceDefaultState',
        'iconPosition',
        'readmoreLink'
    );

    error_log('Valeurs soumises du formulaire : ' . print_r($_POST, true));

    foreach ($text_keys as $key) {
        if (isset ($_POST[$key])) {
            $updated_value = sanitize_text_field($_POST[$key]);
            $wpdb->query($wpdb->prepare("UPDATE $table_name SET $key = %s WHERE id = 1", $updated_value));
        }
    }

    if ($wpdb->last_error) {
        error_log('Erreur MySQL : ' . $wpdb->last_error);
    }
}

function mettre_a_jour_parametres_tarteaucitron()
{
    if (!isset ($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'mettre_a_jour_parametres_tarteaucitron')) {
        wp_die('Erreur de vérification du nonce');
    }

    mettre_a_jour_cases_a_cocher();

    mettre_a_jour_champs_texte();

    wp_redirect(admin_url('admin.php?page=cookie-settings'));
    exit;
}


add_action('admin_post_mettre_a_jour_parametres_tarteaucitron', 'mettre_a_jour_parametres_tarteaucitron');
function register_consentement_google_analytics()
{
    if (isset ($_POST['tarteaucitron']) && $_POST['tarteaucitron'] == 'googleanalytics') {
        global $wpdb;
        $table_name = 'wp_googleanalytics'; // Remplacez 'votre_table_custom_google_analytics' par le nom de votre table personnalisée
        $user_id = get_current_user_id();
        $autorisation_ga = 1;

        $wpdb->replace($table_name, array('user_id' => $user_id, 'autorisation_ga' => $autorisation_ga), array('%d', '%d'));
    }
}
add_action('wp', 'register_consentement_google_analytics');


function diplay_preferences_consentement_google_analytics()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'autorisation_google_analytics';

    $user_id = get_current_user_id();
    $autorisation_ga = $wpdb->get_var($wpdb->prepare("SELECT autorisation_ga FROM $table_name WHERE user_id = %d", $user_id));

    $consentement_texte = $autorisation_ga ? 'Autorisé' : 'Non autorisé';
    ?>
    <div class="wrap">
        <h2>Préférences de consentement pour Google Analytics</h2>
        <p>Consentement pour Google Analytics : '
            <?php echo $consentement_texte; ?>'
        </p>
    </div>
    <?php
}

function display_ga_id_input()
{
    $user_ga_id = '';
    ?>
    <div class="wrap">
        <h2>Paramètres Google Analytics</h2>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="save_ga_id">
            <label for="ga_id">Identifiant Google Analytics:</label>
            <input type="text" id="ga_id" name="ga_id" value="<?php echo esc_attr($user_ga_id); ?>" />
            <input type="submit" class="button-primary" value="Enregistrer">
        </form>
    </div>
    <?php
}


function add_ga_to_tarteaucitron()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wp_tarteaucitron_google_analytics'; // Assurez-vous que c'est le bon nom de table
    $user_ga_id = $wpdb->get_var("SELECT google_analytics_id FROM $table_name WHERE id = 1");

    if (!empty($user_ga_id)) {
        ?>
        <script>
            tarteaucitron.user.gajsUa = '<?php echo esc_js($user_ga_id); ?>';
            tarteaucitron.user.gajsMore = function () { /* add here your optional _ga.push() */ };
            (tarteaucitron.job = tarteaucitron.job || []).push('gajs');
        </script>
        <?php
    }
}
add_action('wp_footer', 'add_ga_to_tarteaucitron');

function save_ga_id()
{
    global $wpdb;

    // Récupérer l'identifiant Google Analytics depuis le formulaire
    $user_ga_id = isset($_POST['ga_id']) ? sanitize_text_field($_POST['ga_id']) : '';

    // Valider l'identifiant Google Analytics
    if (preg_match('/^GTM-\w+$/', $user_ga_id)) {
        // Si l'identifiant est valide, le nettoyer
        $user_ga_id = sanitize_text_field($user_ga_id);

        // Enregistrer l'identifiant Google Analytics dans la base de données
        $table_name = $wpdb->prefix . 'wp_tarteaucitron_google_analytics';
        $wpdb->update(
            $table_name,
            array('google_analytics_id' => $user_ga_id),
            array('id' => 1),
            array('%s'),
            array('%d')
        );
    } else {
        // Si l'identifiant n'est pas valide, afficher un message d'erreur
        wp_die('L\'identifiant Google Analytics n\'est pas valide.');
    }

    // Rediriger vers la page de consentement Google Analytics
    wp_redirect(admin_url('admin.php?page=consentement_google_analytics'));
    exit;
}

add_action('admin_post_save_ga_id', 'save_ga_id');
