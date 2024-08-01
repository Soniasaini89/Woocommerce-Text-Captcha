<?php
/*
Plugin Name: Universal CAPTCHA
Plugin URI: http://sainisonia.com/
Description: Adds a CAPTCHA to WooCommerce registration, Forminator, and Contact Form 7 forms.
Version: 1.0
Author: Sonia
Author URI: http://sainisonia.com/
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Start session
add_action('init', 'universal_captcha_start_session', 1);
function universal_captcha_start_session() {
    if (!session_id()) {
        session_start();
    }
}

// Generate CAPTCHA image
function generate_captcha_image() {
    // Create an image
    $image = imagecreatetruecolor(150, 50);

    // Colors
    $bgColor = imagecolorallocate($image, 255, 255, 255);
    $textColor = imagecolorallocate($image, 0, 0, 0);
    $lineColor = imagecolorallocate($image, 64, 64, 64);
    $pixelColor = imagecolorallocate($image, 0, 0, 255);

    // Fill the background
    imagefilledrectangle($image, 0, 0, 150, 50, $bgColor);

    // Add random lines
    for ($i = 0; $i < 6; $i++) {
        imageline($image, 0, rand() % 50, 200, rand() % 50, $lineColor);
    }

    // Add random pixels
    for ($i = 0; $i < 1000; $i++) {
        imagesetpixel($image, rand() % 200, rand() % 50, $pixelColor);
    }

    // Add text
    $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
    $len = strlen($letters);
    $word = '';
    for ($i = 0; $i < 6; $i++) {
        $letter = $letters[rand(0, $len - 1)];
        imagettftext($image, 20, rand(-30, 30), 5 + ($i * 20), 35, $textColor, plugin_dir_path(__FILE__) . 'arial.ttf', $letter);
        $word .= $letter;
    }

    // Store the CAPTCHA word in session
    $_SESSION['captcha'] = $word;

    // Set the header for the image
    header("Content-type: image/png");
    imagepng($image);
    imagedestroy($image);
    exit;
}
add_action('wp_ajax_nopriv_generate_captcha_image', 'generate_captcha_image');
add_action('wp_ajax_generate_captcha_image', 'generate_captcha_image');

// Create shortcode for displaying CAPTCHA
function display_captcha() {
    ob_start();
    ?>
    <div class="captcha-container">
        <label for="captcha"><?php _e('Captcha', 'universal-captcha'); ?> <span>*</span></label>
        <a href="#" id="refresh-captcha"><?php _e('New Captcha', 'universal-captcha'); ?></a>
        <img src="<?php echo admin_url('admin-ajax.php?action=generate_captcha_image'); ?>" alt="CAPTCHA" id="captcha-image">
        <label for="captcha_input"><?php _e('Type the text displayed above:', 'universal-captcha'); ?></label>
        <input type="text" name="captcha" id="captcha_input" placeholder="Enter CAPTCHA" class="input-text" required>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $("#refresh-captcha").on("click", function(e) {
                e.preventDefault();
                $("#captcha-image").attr("src", "<?php echo admin_url('admin-ajax.php?action=generate_captcha_image'); ?>&rand=" + Math.random());
            });

            var captchaInput = $("#captcha_input");
            var hiddenInput = $("input[name=text-1]");

            if (captchaInput.length && hiddenInput.length) {
                captchaInput.on("input", function() {
                    hiddenInput.val(captchaInput.val());
                });
            }
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('image_captcha', 'display_captcha');

// Verify CAPTCHA
function verify_captcha($captcha_input) {
    if (!isset($captcha_input) || $captcha_input != $_SESSION['captcha']) {
        return false;
    }
    return true;
}

// WooCommerce Registration Form Integration
add_action('woocommerce_register_form', function() {
    echo do_shortcode('[image_captcha]');
});

add_action('woocommerce_register_post', function($username, $email, $validation_errors) {
    if (!verify_captcha($_POST['captcha'])) {
        $validation_errors->add('captcha_error', __('CAPTCHA verification failed. Please try again.', 'universal-captcha'));
    }
    return $validation_errors;
}, 10, 3);

// Forminator Form Integration
add_filter('forminator_custom_form_submit_errors', function($submit_errors, $form_id, $field_data_array) {
    foreach ($field_data_array as $field_data) {
        if ($field_data['name'] === 'text-1' && !verify_captcha($field_data['value'])) {
            $submit_errors[][$field_data['name']] = __('CAPTCHA verification failed. Please try again.', 'universal-captcha');
            break;
        }
    }
    return $submit_errors;
}, 10, 3);

// Contact Form 7 Integration
add_filter('wpcf7_validate', function($result, $tags) {
    $submission = WPCF7_Submission::get_instance();
    if ($submission) {
        $data = $submission->get_posted_data();
        if (isset($data['captcha']) && !verify_captcha($data['captcha'])) {
            $result->invalidate($tags['captcha'], __('CAPTCHA verification failed. Please try again.', 'universal-captcha'));
        }
    }
    return $result;
}, 20, 2);

// Add styles
add_action('wp_head', function() {
    ?>
    <style>
        .captcha-container {
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            align-items: start;
        }
        .captcha-container label {
            font-weight: bold;
        }
        #refresh-captcha {
            margin-left: 10px;
            color: #0073aa;
            cursor: pointer;
        }
        #captcha-image {
            border: 1px solid #ccc;
            margin: 10px 0;
            width: 150px;
            height: 50px;
        }
        #captcha_input {
            width: 100%;
        }
    </style>
    <?php
});
?>
