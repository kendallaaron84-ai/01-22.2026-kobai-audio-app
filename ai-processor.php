<?php
/**
 * KOBA-I Audio: AI Processor (AJAX Logic)
 * * v3.9.0 Fix: Catches Fatal Errors (Throwable) to prevent 500 crashes.
 */
if (!defined('ABSPATH')) exit;

class Koba_AI_Processor {

    private $vault_dir;
    private $vault_url;

    public function __construct() {
        $this->vault_dir = KOBA_IA_PATH . 'koba-ai-processing-vault/';
        $this->vault_url = KOBA_IA_URL . 'koba-ai-processing-vault/';

        add_action('wp_ajax_koba_transcribe_chapter', [$this, 'handle_transcribe']);
        add_action('wp_ajax_koba_check_chapter', [$this, 'handle_check']);
    }

    public function handle_transcribe() {
        check_ajax_referer('k_studio_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $chapter_index = intval($_POST['chapter_index']);
        
        $chapters = json_decode(get_post_meta($post_id, '_koba_chapters_data', true), true);
        if (!isset($chapters[$chapter_index])) wp_send_json_error('Save required.');

        $chapter = $chapters[$chapter_index];
        $attachment_id = $chapter['attachment_id'] ?? 0;

        if (!$attachment_id) wp_send_json_error('No Audio File attached.');

        try {
            $engine = new Koba_AI_Engine();
            $gcs_uri = $engine->upload_to_vault($attachment_id);
            $op_name = $engine->start_chirp_job($gcs_uri);

            $chapters[$chapter_index]['ai_status'] = 'processing';
            $chapters[$chapter_index]['ai_op_name'] = $op_name;
            
            update_post_meta($post_id, '_koba_chapters_data', json_encode($chapters));
            wp_send_json_success(['status' => 'processing']);

        } catch (Throwable $e) { // CHANGED: Catch Fatal Errors too
            wp_send_json_error('Error: ' . $e->getMessage());
        }
    }

    public function handle_check() {
        check_ajax_referer('k_studio_nonce', 'nonce');
        
        $post_id = intval($_POST['post_id']);
        $chapter_index = intval($_POST['chapter_index']);

        $chapters = json_decode(get_post_meta($post_id, '_koba_chapters_data', true), true);
        $chapter = $chapters[$chapter_index];

        if (($chapter['ai_status'] ?? '') !== 'processing') {
            wp_send_json_error('No active job.');
        }

        try {
            $engine = new Koba_AI_Engine();
            $status_data = $engine->check_job_status($chapter['ai_op_name']);

            if ($status_data['status'] === 'completed') {
                
                $result_uri = $status_data['result_uri'];
                $full_json_data = null;

                if ($result_uri) {
                    $full_json_data = $engine->fetch_transcript_json($result_uri);
                }

                if ($full_json_data) {
                    if (!file_exists($this->vault_dir)) {
                        mkdir($this->vault_dir, 0755, true);
                        file_put_contents($this->vault_dir . 'index.php', '<?php // Silence');
                    }

                    // Save as .json (without .mp3 inside filename for cleaner URLs if desired, but keeping standard for now)
                    $filename = 'transcript_' . $chapter['id'] . '.json';
                    file_put_contents($this->vault_dir . $filename, json_encode($full_json_data));

                    $chapters[$chapter_index]['ai_status'] = 'completed';
                    $chapters[$chapter_index]['transcript_file_url'] = $this->vault_url . $filename;
                    unset($chapters[$chapter_index]['transcript_json']); 
                    
                    update_post_meta($post_id, '_koba_chapters_data', json_encode($chapters));
                    
                    wp_send_json_success([
                        'status' => 'completed', 
                        'message' => 'Transcript Saved: ' . $filename
                    ]);
                } else {
                    wp_send_json_error('Job complete, but result file missing.');
                }
            } else {
                wp_send_json_success(['status' => 'processing']);
            }

        } catch (Throwable $e) { // CHANGED: Catch Fatal Errors too
            // If it crashes, we send the error back to the UI instead of a 500 page
            wp_send_json_error('Polling Error: ' . $e->getMessage());
        }
    }
}
new Koba_AI_Processor();