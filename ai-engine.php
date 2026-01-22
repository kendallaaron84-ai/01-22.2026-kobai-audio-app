<?php
/**
 * KOBA-I Audio: AI Engine (Google Chirp v2)
 * * v3.9.1 Fix: Manually unpacks 'Any' objects to bypass Descriptor Pool issues.
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

// Import the Response class so we can unpack into it
use Google\Cloud\Speech\V2\BatchRecognizeResponse;

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
        $speech = new SpeechClient([
            'credentials' => $this->key_file,
            'apiEndpoint' => 'us-central1-speech.googleapis.com',
        ]);

        $parent = "projects/{$this->project_id}/locations/us-central1";
        $recognizer = "{$parent}/recognizers/_";

        $features = (new RecognitionFeatures())
            ->setEnableAutomaticPunctuation(true)
            ->setEnableWordTimeOffsets(true); 

        $config = (new RecognitionConfig())
            ->setModel('chirp')
            ->setLanguageCodes(['en-US'])
            ->setFeatures($features)
            ->setAutoDecodingConfig(new AutoDetectDecodingConfig());

        $output_uri = "gs://{$this->bucket_name}/transcripts/";
        $output_config = (new RecognitionOutputConfig())
            ->setGcsOutputConfig((new GcsOutputConfig())->setUri($output_uri));

        $files = [ (new BatchRecognizeFileMetadata())->setUri($gcs_uri) ];
        
        $request = (new BatchRecognizeRequest())
            ->setRecognizer($recognizer)
            ->setConfig($config)
            ->setFiles($files)
            ->setRecognitionOutputConfig($output_config);

        return $speech->batchRecognize($request)->getName();
    }

    /**
     * CHECK STATUS & GET RESULT URI
     */
    public function check_job_status($operation_name) {
        
        $speech = new SpeechClient([
            'credentials' => $this->key_file,
            'apiEndpoint' => 'us-central1-speech.googleapis.com',
        ]);
        
        $operation = $speech->resumeOperation($operation_name);

        if ($operation->isDone()) {
            $result = $operation->getResult(); 

            // --- FIX v3.9.1: MANUAL UNPACK ---
            // If Google returns a sealed "Any" object because it couldn't auto-detect the type,
            // we manually unpack it into a BatchRecognizeResponse object.
            if ($result instanceof \Google\Protobuf\Any) {
                $realResponse = new BatchRecognizeResponse();
                $result->unpackTo($realResponse);
                $result = $realResponse;
            }
            // ---------------------------------
            
            // Now $result is guaranteed to be the correct object type
            $results = $result->getResults(); 
            
            foreach ($results as $res) {
                $uri = $res->getCloudStorageResult()->getUri();
                return ['status' => 'completed', 'result_uri' => $uri];
            }
            return ['status' => 'completed', 'result_uri' => null];
        }
        
        return ['status' => 'processing'];
    }

    public function fetch_transcript_json($target_uri) {
        $matches = [];
        preg_match('/gs:\/\/([^\/]+)\/(.+)/', $target_uri, $matches);
        
        if (count($matches) < 3) return null;
        
        $bucket_name = $matches[1];
        $object_name = $matches[2];

        $storage = new StorageClient(['keyFilePath' => $this->key_file]);
        $bucket = $storage->bucket($bucket_name);
        $object = $bucket->object($object_name);

        if ($object->exists()) {
            return json_decode($object->downloadAsString(), true);
        }
        return null;
    }
}