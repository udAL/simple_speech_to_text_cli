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

if (count($argv) != 3) {
    return print("Usage: php transcribe_async_gcs.php URI_ORG URI_DEST\n");
}
list($_, $uri_org, $uri_dest) = $argv;

function randomString($length = 15)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randstring = '';
    for ($i = 0; $i < $length; $i++) {
        $randstring .= $characters[rand(0, strlen($characters)-1)];
    }
    return $randstring;
}

# [START speech_transcribe_async_gcs]
use Google\Cloud\Speech\V1\SpeechClient;
use Google\Cloud\Speech\V1\RecognitionAudio;
use Google\Cloud\Speech\V1\RecognitionConfig;
use Google\Cloud\Speech\V1\RecognitionConfig\AudioEncoding;
use Google\Cloud\Storage\StorageClient;

/** Uncomment and populate these variables in your code */
// $uri = 'The Cloud Storage object to transcribe (gs://your-bucket-name/your-object-name)';

// change these variables if necessary
$encoding = AudioEncoding::LINEAR16;
$sampleRateHertz = 32000;
$languageCode = 'ca-ES';

// set string as audio content
$audio = (new RecognitionAudio())
    ->setUri($uri_org);

$uri_dest = explode('/', $uri_dest);
if(!$uri_dest || sizeof($uri_dest) < 2 || $uri_dest[0] != 'gs:' || !$uri_dest[2]) {
    return print("Invalid URI_DEST\n");
}
$bucket_name = $uri_dest[2];
$path = isset($uri_dest[3]) && $uri_dest[3] ? $uri_dest[3] : randomString(5).'.txt';

$storage = new StorageClient();
$bucket = $storage->bucket($bucket_name);
if(!$bucket->exists()) {
    return print("Invalid URI_DEST\n");
}

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

sleep(1);
$operation->isDone();
print_r($operation->getMetadata());

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

    $bucket->upload($full_text, [
        'name' => $path
    ]);

} else {
    print_r($operation->getError());
}

$client->close();
# [END speech_transcribe_async_gcs]
