<?php
/**
 * KOBA-I Audio: AI Engine (Google Chirp v2)
 * * v4.0.0 Stable: Implements safe Pre-loading and Response Unwrapping.
 */
if (!defined('ABSPATH')) exit;

use Google\Cloud\Speech\V2\Client\SpeechClient;
use Google\Cloud\Speech\V2\BatchRecognizeRequest;
use Google\Cloud\Speech\V2\BatchRecognizeFileMetadata;
use Google\Cloud\Speech\V2\RecognitionConfig;
use Google\Cloud\Speech\V2\RecognitionFeatures;
use Google\Cloud\Speech\V2\AutoDetectDecodingConfig;
use Google\Cloud\Speech\V2\RecognitionOutputConfig;
use Google\Cloud\Speech\V2\GcsOutputConfig;
use Google\Cloud\Storage\StorageClient;

// Import the specific classes we need to handle manually
use Google\Cloud\Speech\V2\BatchRecognizeResponse;
use Google\Cloud\Speech\V2\OperationMetadata;

class Koba_AI_Engine {
    private $key_file;
    private $project_id;
    private $bucket_name = 'koba-ai-processing-vault'; 

    public function __construct() {
        $this->key_file = KOBA_IA_PATH . 'includes/google-creds.json';
        if (file_exists($this->key_file)) {
            $creds = json_decode(file_get_contents($this->key_file), true);
            $this->project_id = $creds['project_id'] ?? '';
        }
    }

    // ... (Your upload_to_vault and start_chirp_job functions remain unchanged) ...
    public function upload_to_vault($attachment_id) {
        $file_path = get_attached_file($attachment_id);
        if (!$file_path) throw new Exception("Local file not found.");
        $storage = new StorageClient(['keyFilePath' => $this->key_file]);
        $bucket = $storage->bucket($this->bucket_name);
        $object_name = 'audio-sources/' . basename($file_path);
        $bucket->upload(fopen($file_path, 'r'), ['name' => $object_name]);
        return "gs://{$this->bucket_name}/{$object_name}";
    }

    public function start_chirp_job($gcs_uri) {
        $speech = new SpeechClient(['credentials' => $this->key_file, 'apiEndpoint' => 'us-central1-speech.googleapis.com']);
        $parent = "projects/{$this->project_id}/locations/us-central1";
        $features = (new RecognitionFeatures())->setEnableAutomaticPunctuation(true)->setEnableWordTimeOffsets(true); 
        $config = (new RecognitionConfig())->setModel('chirp')->setLanguageCodes(['en-US'])->setFeatures($features)->setAutoDecodingConfig(new AutoDetectDecodingConfig());
        $output_uri = "gs://{$this->bucket_name}/transcripts/";
        $output_config = (new RecognitionOutputConfig())->setGcsOutputConfig((new GcsOutputConfig())->setUri($output_uri));
        $files = [ (new BatchRecognizeFileMetadata())->setUri($gcs_uri) ];
        $request = (new BatchRecognizeRequest())->setRecognizer("{$parent}/recognizers/_")->setConfig($config)->setFiles($files)->setRecognitionOutputConfig($output_config);
        return $speech->batchRecognize($request)->getName();
    }
    // ... (End of unchanged functions) ...

    /**
     * CHECK STATUS & GET RESULT URI
     */
    public function check_job_status($operation_name) {
        
        // --- 1. SAFE PRE-LOAD (The "Instruction Manual") ---
        // Instead of brute force, we look for the specific Metadata class provided by Google 
        // and run its standard initialization method.
        if (class_exists('\GPBMetadata\Google\Cloud\Speech\V2\CloudSpeech')) {
            \GPBMetadata\Google\Cloud\Speech\V2\CloudSpeech::initOnce();
        }
        // ---------------------------------------------------
        
        $speech = new SpeechClient([
            'credentials' => $this->key_file,
            'apiEndpoint' => 'us-central1-speech.googleapis.com',
        ]);
        
        $operation = $speech->resumeOperation($operation_name);

        if ($operation->isDone()) {
            $result = $operation->getResult(); 

            // --- 2. THE UNWRAPPER (The "Sealed Box") ---
            // If the result is wrapped in an "Any" object, we gently unpack it.
            if ($result instanceof \Google\Protobuf\Any) {
                $realResponse = new BatchRecognizeResponse();
                $result->unpackTo($realResponse);
                $result = $realResponse;
            }
            // -------------------------------------------
            
            // Now we can safely read the results
            // Note: We check if getResults exists to be extra safe
            if (method_exists($result, 'getResults')) {
                $results = $result->getResults(); 
                foreach ($results as $res) {
                    $uri = $res->getCloudStorageResult()->getUri();
                    return ['status' => 'completed', 'result_uri' => $uri];
                }
            }
            
            return ['status' => 'completed', 'result_uri' => null];
        }
        
        return ['status' => 'processing'];
    }

    public function fetch_transcript_json($target_uri) {
        $matches = [];
        preg_match('/gs:\/\/([^\/]+)\/(.+)/', $target_uri, $matches);
        if (count($matches) < 3) return null;
        $storage = new StorageClient(['keyFilePath' => $this->key_file]);
        $bucket = $storage->bucket($matches[1]);
        $object = $bucket->object($matches[2]);
        if ($object->exists()) {
            return json_decode($object->downloadAsString(), true);
        }
        return null;
    }
}