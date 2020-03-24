<?php

function randomString($length = 15)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randstring = '';
    for ($i = 0; $i < $length; $i++) {
        $randstring .= $characters[rand(0, strlen($characters)-1)];
    }
    return $randstring;
}

//returns an assoc array with bitRate (kbps) and sampleRate (hz)
function getMP3BitRateSampleRate($filename)
{
    if (!file_exists($filename)) {
        return false;
    }

    $bitRates = array(
        array(0,0,0,0,0),
        array(32,32,32,32,8),
        array(64,48,40,48,16),
        array(96,56,48,56,24),
        array(128,64,56,64,32),
        array(160,80,64,80,40),
        array(192,96,80,96,48),
        array(224,112,96,112,56),
        array(256,128,112,128,64),
        array(288,160,128,144,80),
        array(320,192,160,160,96),
        array(352,224,192,176,112),
        array(384,256,224,192,128),
        array(416,320,256,224,144),
        array(448,384,320,256,160),
        array(-1,-1,-1,-1,-1),
    );
    $sampleRates = array(
        array(11025,12000,8000), //mpeg 2.5
        array(0,0,0),
        array(22050,24000,16000), //mpeg 2
        array(44100,48000,32000), //mpeg 1
    );
    $bToRead = 1024 * 12;

    $fileData = array('bitRate' => 0, 'sampleRate' => 0);
    $fp = fopen($filename, 'r');
    if (!$fp) {
        return false;
    }
    //seek to 8kb before the end of the file
    fseek($fp, -1 * $bToRead, SEEK_END);
    $data = fread($fp, $bToRead);

    $bytes = unpack('C*', $data);
    $frames = array();
    $lastFrameVerify = null;

    for ($o = 1; $o < count($bytes) - 4; $o++) {

        //http://mpgedit.org/mpgedit/mpeg_format/MP3Format.html
        //header is AAAAAAAA AAABBCCD EEEEFFGH IIJJKLMM
        if (($bytes[$o] & 255) == 255 && ($bytes[$o+1] & 224) == 224) {
            $frame = array();
            $frame['version'] = ($bytes[$o+1] & 24) >> 3; //get BB (0 -> 3)
            $frame['layer'] = abs((($bytes[$o+1] & 6) >> 1) - 4); //get CC (1 -> 3), then invert
            $srIndex = ($bytes[$o+2] & 12) >> 2; //get FF (0 -> 3)
            $brRow = ($bytes[$o+2] & 240) >> 4; //get EEEE (0 -> 15)
            $frame['padding'] = ($bytes[$o+2] & 2) >> 1; //get G
            if ($frame['version'] != 1 && $frame['layer'] > 0 && $srIndex < 3 && $brRow != 15 && $brRow != 0 &&
                (!$lastFrameVerify || $lastFrameVerify === $bytes[$o+1])) {
                //valid frame header

                //calculate how much to skip to get to the next header
                $frame['sampleRate'] = $sampleRates[$frame['version']][$srIndex];
                if ($frame['version'] & 1 == 1) {
                    $frame['bitRate'] = $bitRates[$brRow][$frame['layer']-1]; //v1 and l1,l2,l3
                } else {
                    $frame['bitRate'] = $bitRates[$brRow][($frame['layer'] & 2 >> 1)+3]; //v2 and l1 or l2/l3 (3 is the offset in the arrays)
                }

                if ($frame['layer'] == 1) {
                    $frame['frameLength'] = (12 * $frame['bitRate'] * 1000 / $frame['sampleRate'] + $frame['padding']) * 4;
                } else {
                    $frame['frameLength'] = 144 * $frame['bitRate'] * 1000 / $frame['sampleRate'] + $frame['padding'];
                }

                $frames[] = $frame;
                $lastFrameVerify = $bytes[$o+1];
                $o += floor($frame['frameLength'] - 1);
            } else {
                $frames = array();
                $lastFrameVerify = null;
            }
        }
        if (count($frames) < 3) { //verify at least 3 frames to make sure its an mp3
            continue;
        }

        $header = array_pop($frames);
        $fileData['sampleRate'] = $header['sampleRate'];
        $fileData['bitRate'] = $header['bitRate'];

        break;
    }

    return $fileData;
}