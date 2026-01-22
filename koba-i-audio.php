<?php
/**
 * Plugin Name: KOBA-I Audio
 * Version: 3.6.0 - Ironclad & Mini
 * Description: Tier-1 Audiobook & Video Player with Mini-Mode and Studio Suite.
 * Author: Kendall Aaron
 * Text Domain: koba-i-audio
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// 1. CONSTANTS
define( 'KOBA_IA_PATH', plugin_dir_path( __FILE__ ) );
define( 'KOBA_IA_URL', plugin_dir_url( __FILE__ ) );

// 2. LOAD DEPENDENCIES
if ( file_exists( KOBA_IA_PATH . 'vendor/autoload.php' ) ) {
    require_once KOBA_IA_PATH . 'vendor/autoload.php';
}

$modules = [
    'includes/safety-sentinel.php',
    'includes/ai-engine.php',
    'includes/ai-processor.php',
    'includes/streaming.php',
    'includes/ajax.php',
    'includes/admin.php',
    'includes/security.php'
];

foreach ($modules as $module) {
    if ( file_exists( KOBA_IA_PATH . $module ) ) require_once KOBA_IA_PATH . $module;
}

// 3. REGISTER POST TYPE
add_action('init', function() {
    register_post_type('koba_publication', [
        'labels'      => ['name' => 'Publications', 'singular_name' => 'Publication', 'add_new_item' => 'Add New Audiobook'],
        'public'      => true, 
        'show_ui'     => true, 
        'show_in_menu' => true,
        'menu_icon'   => 'dashicons-album',
        'supports'    => ['title'],
        'show_in_rest' => true
    ]);
});

// 4. STUDIO REDIRECT
add_filter('get_edit_post_link', function($link, $post_id) {
    if (get_post_type($post_id) === 'koba_publication') {
        return admin_url("edit.php?post_type=koba_publication&page=koba-studio&post=$post_id");
    }
    return $link;
}, 10, 2);

add_action('admin_menu', function() {
    add_submenu_page('edit.php?post_type=koba_publication', 'KOBA Studio', 'Studio', 'edit_posts', 'koba-studio', 'koba_render_production_suite');
});

// 5. THE MAIN PLAYER SHORTCODE [koba_player]
add_shortcode('koba_player', function($atts) {
    $post_id = !empty($atts['id']) ? intval($atts['id']) : get_the_ID();
    
    // Fetch Metadata
    $chapters_json = get_post_meta($post_id, '_koba_chapters_data', true);
    // If no chapters, don't render anything
    if (empty($chapters_json)) return '';

    $chapters = json_decode($chapters_json, true) ?: [];
    
    // Secure URLs (Ghost Protocol)
    foreach ($chapters as &$chapter) {
        $chapter['url'] = get_rest_url(null, "koba-ia/v2/stream/{$chapter['id']}");
    }

    // Enqueue Assets (Ironclad JS)
    wp_enqueue_script('koba-bloom-js', KOBA_IA_URL . 'assets/bloom-player.js', [], '3.6.0', true);
    wp_enqueue_style('koba-bloom-css', KOBA_IA_URL . 'assets/bloom-style.css', [], '3.6.0');

    // Pass Data to Window (Global Scope)
    wp_localize_script('koba-bloom-js', 'kobaData', [
        'title'       => get_the_title($post_id),
        'author'      => get_post_meta($post_id, '_koba_author_name', true) ?: 'Unknown Author',
        'coverUrl'    => get_post_meta($post_id, '_koba_cover_art_url', true),
        'bgImage'     => get_post_meta($post_id, '_koba_bg_image_url', true),
        'logoUrl'     => KOBA_IA_URL . 'assets/koba-logo-text.png',
        'chapters'    => $chapters
    ]);

    return "<div id='koba-bloom-root'></div>";
});

// 6. AUTO-INJECT PLAYER (For Preview Button)
add_filter('the_content', function($content) {
    // Automatically adds the player to the top of the Publication page
    if (is_singular('koba_publication') && in_the_loop() && is_main_query()) {
        return do_shortcode('[koba_player]') . $content;
    }
    return $content;
});

// 7. MINI PLAYER SHORTCODE [koba_mini]
add_shortcode('koba_mini', function($atts) {
    // 1. Determine Post ID
    if (!empty($atts['id'])) {
        $post_id = intval($atts['id']);
    } else {
        $post_id = get_the_ID();
    }
    
    // 2. Fetch Data
    $chapters_json = get_post_meta($post_id, '_koba_chapters_data', true);
    if (empty($chapters_json)) return '';

    $chapters = json_decode($chapters_json, true) ?: [];
    
    // 3. Process URLs
    foreach ($chapters as &$chapter) {
        $chapter['url'] = get_rest_url(null, "koba-ia/v2/stream/{$chapter['id']}");
    }

    // 4. Enqueue Assets (Same script handles both modes)
    wp_enqueue_script('koba-bloom-js', KOBA_IA_URL . 'assets/bloom-player.js', [], '3.6.0', true);
    wp_enqueue_style('koba-bloom-css', KOBA_IA_URL . 'assets/bloom-style.css', [], '3.6.0');

    // 5. Prepare Payload (Mini Config)
    $payload = [
        'mode'        => 'mini',
        'title'       => get_the_title($post_id),
        'author'      => get_post_meta($post_id, '_koba_author_name', true) ?: 'Unknown',
        'coverUrl'    => get_post_meta($post_id, '_koba_cover_art_url', true),
        'chapters'    => $chapters
    ];

    // 6. Render Container (Data attribute holds the config)
    $json_attr = htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8');
    return "<div class='koba-mini-root' data-config='$json_attr'></div>";
});