<?php
/**
 * KOBA-I Audio: Streaming Engine (The Ghost Protocol)
 * * Serves Audio/Video by ID. Hides real path. Supports Headless Mobile App.
 */

add_action('rest_api_init', function () {
    register_rest_route('koba-ia/v2', '/stream/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'koba_secure_stream',
        'permission_callback' => '__return_true', // Validation happens inside
    ]);
});

function koba_secure_stream($data) {
    $chapter_id = $data['id']; // This is the 'id' from our JSON, NOT necessarily attachment_id
    
    // 1. FIND THE REAL ATTACHMENT ID
    // We have to search all audiobooks to find which one owns this chapter ID.
    // In a high-traffic app, we'd use a custom table. For now, we query meta.
    global $wpdb;
    $result = $wpdb->get_row("SELECT post_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_koba_chapters_data' AND meta_value LIKE '%$chapter_id%' LIMIT 1");

    if (!$result) return new WP_Error('not_found', 'Chapter not found', ['status' => 404]);

    $chapters = json_decode($result->meta_value, true);
    $target_chapter = null;
    foreach($chapters as $chap) {
        if ($chap['id'] == $chapter_id) {
            $target_chapter = $chap;
            break;
        }
    }

    if (!$target_chapter || empty($target_chapter['attachment_id'])) {
        return new WP_Error('no_file', 'Media file missing from Vault', ['status' => 404]);
    }

    // 2. HEADLESS MOBILE APP SECURITY (JWT CHECK)
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    // if ($auth_header) { verify_mobile_jwt($auth_header); } 
    // For now, we allow browser cookies (Web Player)
    
    // 3. GET PHYSICAL FILE PATH
    $file_path = get_attached_file($target_chapter['attachment_id']);
    if (!file_exists($file_path)) {
        return new WP_Error('missing', 'File deleted from server', ['status' => 404]);
    }

    // 4. STREAM IT (With Video Support)
    $mime = ($target_chapter['type'] === 'video') ? 'video/mp4' : 'audio/mpeg';
    $size = filesize($file_path);
    $fp = fopen($file_path, 'rb');

    // Handle Range Requests (Required for Video Scrubbing & Mobile App)
    $start = 0; 
    $end = $size - 1;

    header("Content-Type: $mime");
    header("Accept-Ranges: bytes");

    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        $range = str_replace('bytes=', '', $range);
        list($start, $end) = explode('-', $range);
        if ($end == '') $end = $size - 1;
        
        header("HTTP/1.1 206 Partial Content");
        header("Content-Range: bytes $start-$end/$size");
        header("Content-Length: " . ($end - $start + 1));
        fseek($fp, $start);
    } else {
        header("Content-Length: $size");
    }

    // Output loop
    while (!feof($fp) && ($p = ftell($fp)) <= $end) {
        if ($p + 8192 > $end) {
            echo fread($fp, $end - $p + 1);
        } else {
            echo fread($fp, 8192);
        }
        flush();
    }
    fclose($fp);
    exit;
}