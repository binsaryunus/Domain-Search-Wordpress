<?php
/**
 * Plugin Name: Domain Search
 * Description: Domain Search for billing is a lightweight yet powerful WordPress plugin integrating domain availability checks via RDAP with seamless billing checkout support, configurable via admin panel.
 * Version: 1.3
 * Author: APIKU.ID
 * Plugin URI: https://apiku.id/
 * Author URI: https://apiku.id
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.x
 * Requires PHP: 7.4
 */

// Exit if accessed directly
if (! defined('ABSPATH')) {
    exit;
}

// Option name for settings
if (! defined('DC_OPTION_LAYOUT')) {
    define('DC_OPTION_LAYOUT', 'dc_layout_settings');
}

/**
 * Retrieve layout options (with defaults)
 *
 * @return array
 */
function dc_get_layout_options() {
    $defaults = [
        'shortcode_tag'        => 'domain_checker',
        'placeholder'          => 'example.com',
        'wrapper_before'       => '',
        'wrapper_after'        => '',
        'custom_css'           => '',
        'default_checkout_url' => '',
    ];
    $opts = get_option(DC_OPTION_LAYOUT, []);
    return wp_parse_args($opts, $defaults);
}

/**
 * Save layout options
 *
 * @param array $data
 */
function dc_save_layout_options($data) {
    update_option(DC_OPTION_LAYOUT, $data);
}

/**
 * Add settings page under Settings menu
 */
add_action('admin_menu', function() {
    add_options_page(
        'Domain Search Settings',
        'Domain Search',
        'manage_options',
        'dc-settings',
        'dc_settings_page'
    );
});

/**
 * Render the settings page
 */
function dc_settings_page() {
    if (! current_user_can('manage_options')) {
        wp_die('Access denied');
    }
    $opts = dc_get_layout_options();
    // Handle form submission
    if (isset($_POST['dc_submit'])) {
        check_admin_referer('dc_settings_save', 'dc_settings_nonce');
        $opts['shortcode_tag']        = sanitize_key($_POST['dc_shortcode_tag'] ?? $opts['shortcode_tag']);
        $opts['placeholder']          = sanitize_text_field($_POST['dc_placeholder'] ?? $opts['placeholder']);
        $opts['wrapper_before']       = wp_kses_post($_POST['dc_wrapper_before'] ?? $opts['wrapper_before']);
        $opts['wrapper_after']        = wp_kses_post($_POST['dc_wrapper_after'] ?? $opts['wrapper_after']);
        $opts['custom_css']           = wp_strip_all_tags($_POST['dc_custom_css'] ?? $opts['custom_css']);
        $opts['default_checkout_url'] = esc_url_raw($_POST['dc_default_checkout_url'] ?? $opts['default_checkout_url']);
        dc_save_layout_options($opts);
        echo '<div class="updated"><p>Settings saved.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>Domain Search Settings</h1>
        <form method="post">
            <?php wp_nonce_field('dc_settings_save','dc_settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="dc_shortcode_tag">Shortcode Tag</label></th>
                    <td>
                        <input name="dc_shortcode_tag" id="dc_shortcode_tag" type="text" class="regular-text" value="<?php echo esc_attr($opts['shortcode_tag']); ?>" />
                        <p class="description">Enter the shortcode tag to use, e.g. <code>my_domain_check</code>. Use letters, numbers, and underscores.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="dc_placeholder">Placeholder Text</label></th>
                    <td><input name="dc_placeholder" id="dc_placeholder" type="text" class="regular-text" value="<?php echo esc_attr($opts['placeholder']); ?>" /></td>
                </tr>
                <tr>
                    <th><label for="dc_default_checkout_url">Default Checkout URL</label></th>
                    <td><input name="dc_default_checkout_url" id="dc_default_checkout_url" type="url" class="regular-text" value="<?php echo esc_attr($opts['default_checkout_url']); ?>" />
                        <p class="description">Default billing system URL for checkout (appended ?domain=... if not provided in shortcode).</p></td>
                </tr>
                <tr>
                    <th><label for="dc_wrapper_before">Wrapper Before</label></th>
                    <td>
                        <textarea name="dc_wrapper_before" id="dc_wrapper_before" rows="3" class="large-text"><?php echo esc_textarea($opts['wrapper_before']); ?></textarea>
                        <p class="description">HTML output before the form (e.g., container div).</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="dc_wrapper_after">Wrapper After</label></th>
                    <td>
                        <textarea name="dc_wrapper_after" id="dc_wrapper_after" rows="3" class="large-text"><?php echo esc_textarea($opts['wrapper_after']); ?></textarea>
                        <p class="description">HTML output after the form.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="dc_custom_css">Custom CSS</label></th>
                    <td>
                        <textarea name="dc_custom_css" id="dc_custom_css" rows="5" class="large-text"><?php echo esc_textarea($opts['custom_css']); ?></textarea>
                        <p class="description">Additional CSS to style the form and result.</p>
                    </td>
                </tr>
            </table>
            <?php submit_button('Save Settings', 'primary', 'dc_submit'); ?>
        </form>
        <h2>Usage</h2>
        <p>Use the shortcode <code>[<strong><?php echo esc_html($opts['shortcode_tag']); ?></strong> checkout_url="https://your-billing.com/register"]</code> if you want to override default, or omit <code>checkout_url</code> to use the default set above.</p>
    </div>
    <?php
}

/**
 * Register the shortcode based on admin setting
 */
add_action('init', 'dc_register_shortcode');
function dc_register_shortcode() {
    $opts = dc_get_layout_options();
    $tag = sanitize_key($opts['shortcode_tag']);
    if ($tag) {
        add_shortcode($tag, 'dc_render_domain_search');
    }
}

/**
 * Render the domain search form
 */
function dc_render_domain_search($atts) {
    $opts = dc_get_layout_options();
    $atts = shortcode_atts([
        'checkout_url' => $opts['default_checkout_url'],
    ], $atts, $opts['shortcode_tag']);

    $placeholder = esc_attr($opts['placeholder']);
    $before      = $opts['wrapper_before'];
    $after       = $opts['wrapper_after'];
    $checkout    = esc_url($atts['checkout_url']);
    $uid         = uniqid('dc_');

    ob_start();
    echo $before;
    ?>
    <form id="<?php echo esc_attr($uid); ?>_form" class="dc-form" style="margin-bottom:1em;">
        <?php wp_nonce_field('dc_search','dc_nonce'); ?>
        <input type="text" id="<?php echo esc_attr($uid); ?>_domain" name="domain" placeholder="<?php echo $placeholder; ?>" required />
        <button type="submit">Check Domain</button>
    </form>
    <div id="<?php echo esc_attr($uid); ?>_result"></div>
    <?php
echo "<style>
.dc-checkout-button {
    display: inline-block;
    background-color: #007bff;
    color: #fff;
    padding: 0.75em 1.25em;
    border-radius: 4px;
    border: none;
    text-decoration: none;
    transition: background-color 0.3s ease;
}
.dc-checkout-button:hover {
    background-color: #0056b3;
}
</style>";
    ?>
    <script>
    (function(){
        var form = document.getElementById('<?php echo esc_js($uid); ?>_form');
        var input = document.getElementById('<?php echo esc_js($uid); ?>_domain');
        var resultEl = document.getElementById('<?php echo esc_js($uid); ?>_result');
        var checkoutUrl = '<?php echo esc_js($checkout); ?>';
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            resultEl.textContent = 'Checking...';
            var data = new URLSearchParams({
                action: 'dc_check_domain',
                domain: input.value.trim(),
                dc_nonce: form.querySelector('#dc_nonce').value,
                checkout_url: checkoutUrl
            });
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: data
            }).then(res=>res.json()).then(json=>{
                if(json.success) resultEl.innerHTML = json.data.html;
                else resultEl.innerHTML = '<span class="dc-error">Error: '+json.data.message+'</span>';
            }).catch(_=>{ resultEl.innerHTML = '<span class="dc-error">An error occurred.</span>'; });
        });
    })();
    </script>
    <?php
    echo $after;
    return ob_get_clean();
}

/**
 * AJAX handler for domain check
 */
add_action('wp_ajax_dc_check_domain','dc_check_domain');
add_action('wp_ajax_nopriv_dc_check_domain','dc_check_domain');
function dc_check_domain() {
    if(!isset($_POST['dc_nonce']) || !check_ajax_referer('dc_search','dc_nonce',false)) {
        wp_send_json_error(['message'=>'Security check failed.']);
    }
    $domain = sanitize_text_field($_POST['domain'] ?? '');
    if(!preg_match('/^[A-Za-z0-9\-]+\.[A-Za-z]{2,}$/',$domain)) {
        wp_send_json_error(['message'=>'Invalid domain format.']);
    }
    $rdap_url = 'https://rdap.org/domain/'.rawurlencode($domain);
    $response = wp_remote_get($rdap_url,['timeout'=>10]);
    if(is_wp_error($response)) {
        wp_send_json_error(['message'=>$response->get_error_message()]);
    }
    $code = wp_remote_retrieve_response_code($response);
    $available = ($code===404);
    if($available) {
        $html = "<span class='dc-available'>{$domain} is <strong>available</strong>!</span>";
        if(!empty($_POST['checkout_url'])) {
            $url = esc_url_raw($_POST['checkout_url'].(strpos($_POST['checkout_url'],'?')===false?'?':'&').'domain='.rawurlencode($domain));
            $html .= " <a class='dc-checkout-button' href='{$url}'>Checkout</a>";
        }
    } else {
        $html = "<span class='dc-unavailable'>{$domain} is <strong>already registered</strong>.</span>";
    }
    wp_send_json_success(['html'=>$html]);
}
