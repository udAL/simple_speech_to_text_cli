<?php
/**
 * Copyright 2016 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * For instructions on how to run the full sample:
 *
 * @see https://github.com/GoogleCloudPlatform/php-docs-samples/tree/master/speech/README.md
 */

// Include Google Cloud dependendencies using Composer
require_once __DIR__ . '/../vendor/autoload.php';
require 'Utils.php';

if (count($argv) != 3) {
    return print("Usage: php transcribe_async_gcs.php URI_ORG URI_DEST\n");
}
list($_, $uri_org, $uri_dest) = $argv;

/*
Example:
if (!empty($_FILES['mp3'])) {
    $data = getMP3BitRateSampleRate($_FILES['mp3']['tmp_name']);
}
*/

# [START speech_transcribe_async_gcs]
use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\RecognitionConfig\AudioEncoding;
use Google\Cloud\Storage\StorageClient;

/** Uncomment and populate these variables in your code */
// $uri = 'The Cloud Storage object to transcribe (gs://your-bucket-name/your-object-name)';

// change these variables if necessary
$encoding = AudioEncoding::ENCODING_UNSPECIFIED;
$sampleRateHertz = 32000;
$languageCode = 'ca-ES';

$storage = new StorageClient();

// --- Origin ---
$uri_org_arr = explode('/', $uri_org);
if(!$uri_org_arr || sizeof($uri_org_arr) < 2 || $uri_org_arr[0] != 'gs:' || !$uri_org_arr[2] || !$uri_org_arr[3]) {
    return print("Invalid uri URI_ORG\n");
}
$bucket_org = $uri_org_arr[2];
$path_org = $uri_org_arr[3];

$bucket_org = $storage->bucket($bucket_org);
if(!$bucket_org->exists()) {
    return print("Invalid bucket URI_ORG\n");
}
$file_org = $bucket_org->object($path_org);
if(!$file_org->exists()) {
    return print("Invalid path URI_ORG\n");
}
$ext = pathinfo($path_org, PATHINFO_EXTENSION);
if($ext !== 'mp3') {
    return print("Invalid extension URI_ORG\n");
}

// --- Download ---
$tmp = sys_get_temp_dir().'/'.randomString(5).'.mp3';
$fh = fopen($tmp, 'w');
fclose($fh);
$file_org->downloadToFile($tmp);

// --- Test Sample Rate ---
$sample = getMP3BitRateSampleRate($tmp);
$sampleRateHertz = $sample['sampleRate'];
print("Detected SampleRate ".$sampleRateHertz." Hz".PHP_EOL);
unlink($tmp);

// --- Destination ---
$uri_dest = explode('/', $uri_dest);
if(!$uri_dest || sizeof($uri_dest) < 2 || $uri_dest[0] != 'gs:' || !$uri_dest[2]) {
    return print("Invalid uri URI_DEST\n");
}
$bucket_dest = $uri_dest[2];
$path_dest = isset($uri_dest[3]) && $uri_dest[3] ? $uri_dest[3] : randomString(5).'.txt';

$bucket_dest = $storage->bucket($bucket_dest);
if(!$bucket_dest->exists()) {
    return print("Invalid bucket URI_DEST\n");
}

// set string as audio content
$audio = (new RecognitionAudio())
    ->setUri($uri_org);

// set config
$config = (new RecognitionConfig())
    ->setEncoding($encoding)
    ->setSampleRateHertz($sampleRateHertz)
    ->setLanguageCode($languageCode)
    ->setEnableAutomaticPunctuation(true);

// create the speech client
$client = new SpeechClient();

// create the asyncronous recognize operation
$operation = $client->longRunningRecognize($config, $audio);
$operation->pollUntilComplete();

if ($operation->operationSucceeded()) {
    $response = $operation->getResult();

    $full_text = array();
    // each result is for a consecutive portion of the audio. iterate
    // through them to get the transcripts for the entire audio file.
    foreach ($response->getResults() as $result) {
        $alternatives = $result->getAlternatives();
        $mostLikely = $alternatives[0];
        $transcript = $mostLikely->getTranscript();
        $confidence = $mostLikely->getConfidence();
        $full_text[] = $transcript;
    }
    $full_text = implode(' ', $full_text);

    $bucket_dest->upload($full_text, [
        'name' => $path_dest
    ]);

    print("Success!".PHP_EOL);
} else {
    print_r($operation->getError());
}

$client->close();
# [END speech_transcribe_async_gcs]
