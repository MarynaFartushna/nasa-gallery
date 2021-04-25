<?php
/**
 * Nasa gallery
 *
 * Plugin Name: Nasa gallery
 * Plugin URI:  ''
 * Description: Upload daily a new image from Astronomy Picture of the Day.
 * Version:     1.0
 * Author: Maryna Fartushna
 * Author URI: https://t.me/Maryna_Far
 *
 */

if (!defined('ABSPATH')) {
    exit;
}

register_activation_hook(__FILE__, 'nasa_plugin_activation');

register_deactivation_hook(__FILE__, 'nasa_plugin_deactivation');

register_uninstall_hook(__FILE__, 'my_plugin_uninstall');

function nasa_plugin_activation()
{

    register_nasa_post_type();

    flush_rewrite_rules();

    create_old_nasa_galleries_posts();

    wp_clear_scheduled_hook('create_daily_nasa_gallery');

    wp_schedule_event(time(), 'daily', 'create_daily_nasa_gallery');

}

function nasa_plugin_deactivation()
{

    wp_clear_scheduled_hook('create_daily_nasa_gallery');

}

function my_plugin_uninstall()
{

    wp_clear_scheduled_hook('create_daily_nasa_gallery');

    unregister_post_type('nasa_gallery');

    //mb delete all 'nasa_gallery' posts
}


add_action('init', 'register_nasa_post_type');

function register_nasa_post_type()
{

    register_post_type('nasa_gallery', array(
        'label' => 'NASA Gallery',
        'labels' => array(
            'name' => 'NASA Gallery',
            'singular_name' => 'NASA Gallery',
            'menu_name' => 'NASA Gallery',
        ),
        'description' => '',
        'public' => true,
        'menu_icon' => 'dashicons-admin-home',
        'show_ui' => true,
        'rest_base' => '',
        'show_in_menu' => true,
        'exclude_from_search' => true,
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'hierarchical' => false,
        'has_archive' => false,
        'rewrite' => true,
        'query_var' => true,
        'supports' => array('title', 'thumbnail'),

    ));

}

if (defined('DOING_CRON') && DOING_CRON) {

    add_action('create_daily_nasa_gallery', 'create_daily_nasa_gallery');

    function create_daily_nasa_gallery()
    {


        // mb get key from plugin settings page
        $api_key = 'oTipI0fZ76VapDy65w6NZLcG92SPRKmHlGHyC8xX';

        $url = "https://api.nasa.gov/planetary/apod";

        $current_date = date('Y-m-d', current_time('timestamp', 0));

        $date = 'date=' . $current_date;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url . '?api_key=' . $api_key . '&' . $date . '&thumbs=true',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        $result = json_decode($response, true);

        create_nasa_gallery_post($result);

        exit();

    }
}


function create_old_nasa_galleries_posts()
{

    $api_key = 'oTipI0fZ76VapDy65w6NZLcG92SPRKmHlGHyC8xX';

    $url = "https://api.nasa.gov/planetary/apod";

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL => $url . '?api_key=' . $api_key . '&count=4&thumbs=true',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'GET',
    ));

    $response = curl_exec($curl);

    curl_close($curl);

    $result = json_decode($response, true);

    foreach ($result as $item) :

        create_nasa_gallery_post($item);

    endforeach;

}

function create_nasa_gallery_post($data)
{

    $post_name = 'apod-' . $data['date'];

    $post_title = $data['date'];

//     Create post object
    $new_nasa_post = array(
        'post_type' => 'nasa_gallery',
        'post_title' => $post_title,
        'post_name' => $post_name,
        'post_content' => '',
        'post_status' => 'publish',
        'post_author' => 1,

    );

    $new_post_id = wp_insert_post($new_nasa_post);

    $img_title = $data['title'];

    $image_url = '';

    // if return image
    if ($data['media_type'] == 'image') {

        $image_url = $data['url'];

    } elseif ($data['media_type'] == 'video') {

        $image_url = $data['thumbnail_url'];

    }

    // else ?

    if ($new_post_id && !is_wp_error($new_post_id) && ($image_url != "")) {

        $attachment_id = upload_image($image_url, $new_post_id, $img_title);

        update_post_meta($new_post_id, '_thumbnail_id', $attachment_id);

    }

}

function upload_image($image_url, $new_post_id, $img_title)
{

    //for fix error: Call to undefined function media_handle_sideload()
    include_once(ABSPATH . 'wp-admin/includes/admin.php');

    $attachment_id = media_sideload_image($image_url, $new_post_id, $img_title, $return = 'id');

    if (is_wp_error($attachment_id)) {
        echo $attachment_id->get_error_message();
    }

    return $attachment_id;

}


function nasa_gallery_top_5_func()
{

    $transient = get_transient('nasa_galleries_posts');

    if (!$transient):

        $args = array(
            'posts_per_page' => 5,
            'orderby' => 'date',
            'post_type' => 'nasa_gallery',
            'order' => 'DESC',
        );

        $nasa_gallery_sc_posts = new WP_Query($args);

        set_transient('nasa_galleries_posts', $nasa_gallery_sc_posts, DAY_IN_SECONDS * 1);

    else:

        $nasa_gallery_sc_posts = $transient;

    endif;

    ob_start(); ?>

    <div class="nasa_gallery">

        <?php if ($nasa_gallery_sc_posts->have_posts()) :

            while ($nasa_gallery_sc_posts->have_posts()): $nasa_gallery_sc_posts->the_post(); ?>

                <div class="item">

                    <?php the_post_thumbnail('full'); ?>

                </div>

            <?php endwhile;

        endif;

        ?>

    </div>

    <?php $return = ob_get_clean();

    wp_reset_query();

    return $return;

}

add_shortcode('nasa_gallery', 'nasa_gallery_top_5_func');

add_action('wp_enqueue_scripts', 'nasa_gallery_enqueue_scripts');

function nasa_gallery_enqueue_scripts()
{

    wp_enqueue_style('nasa_slick_css', plugin_dir_url(__FILE__) . 'assets/slick/slick.css');

    wp_enqueue_style('nasa_slick_theme_css', plugin_dir_url(__FILE__) . 'assets/slick/slick-theme.css');

    wp_enqueue_style('nasa_main_css', plugin_dir_url(__FILE__) . 'assets/css/main.css');

    wp_enqueue_script('nasa_main_js', plugin_dir_url(__FILE__) . 'assets/js/main.js', array('jquery'));

    wp_enqueue_script('nasa_slick_js', plugin_dir_url(__FILE__) . 'assets/slick/slick.min.js', array('jquery'));

}


