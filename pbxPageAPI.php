<?php
/**
 * FreePBX Page API
 * A simple API to send custom pages to FreePBX Paging Groups without Page Pro.
 * 
 * Author: Wriar <https://github.com/wriar>
 * Repository: https://github.com/Wriar/FreePBX-Paging-API
 * License: MIT
 * Copyright: 2024 Wriar Technology
 * 
 * Usage Sample for Custom File:
 * curl --location '<SERVER FQDN>/pbxPageAPI.php' --form 'key="MyAPIKEY"' --form 'pageType="upload"' --form 'pageGroup="<PAGE GROUP EXTENSION>"' --form 'pageOrigin="Important Message"' --form 'pageFile=@"hi.wav"'
 * 
 * Usage Sample for Preset File:
 * curl --location '<SERVER FQDN>/pbxPageAPI.php' --form 'key="MyAPIKEY"' --form 'pageType="preset"' --form 'pageGroup="<PAGE GROUP EXTENSION>"' --form 'pageOrigin="Important Message"' --form 'pageFile="custom/hi.wav"'
 * 
 */

//Set a secret key to prevent unauthorized access to the API.
$SECRET_KEY = "change_me";

/**
 * Optional Advanced Parameters
 * For most situations, these do NOT need to be changed!
 */

// Refer to docs.asterisk.org/Asterisk_21_Documentation/API_Documentation/Dialplan_Applications/Wait/
$PAGE_MAX_WAITTIME = 3; 

// Refer to https://github.com/asterisk/asterisk/blob/master/sample.call
$PAGE_MAX_RETRIES = 1; 

//Do NOT change unless FreePBX is not in English. 
//This is the location where custom audio is uploaded by FreePBX in /var/lib/asterisk/sounds/ + (this param) + /custom/FILENAME.wav
$PAGE_SOUNDDIR = "en"; 

// (DON'T CHANGE THIS UNLESS YOU KNOW WHAT YOU'RE DOING) 
//The delay in seconds to wait for the PBX to read the uploaded audio file before deleting it.
$PBX_READ_DELAY = 3; 

// DON'T CHANGE THIS UNLESS YOUR API POST PROVIDER CANNOT HANDLE OUTPUT BUFFERING
//Uses buffering to send a 200 OK response before deleting the audio file.
$ENABLE_OUTPUT_BUFFERING = true; 




header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

function getNonce() {
    return bin2hex(random_bytes(16));
}

if(!isset($_POST['key'])) {
    http_response_code(400);
    die(json_encode(array(
        "code" => 1, 
        "message" => "The specified key is not provided.",
        "security" => array(
            "nonce" => getNonce(),
            "timestamp" => date("Y-m-d H:i:s")
        )
    )));
}

if($_POST['key'] != $SECRET_KEY) {
    http_response_code(401);
    die(json_encode(array(
        "code" => 1, 
        "message" => "The specified key is invalid.",
        "security" => array(
            "nonce" => getNonce(),
            "timestamp" => date("Y-m-d H:i:s")
        )
    )));
}

//If any of the required fields are not set, return an error and an array of the required fields.
$requiredFields = array('pageType', 'pageOrigin', 'pageGroup', 'pageFile');
$unprovideFields = array();
foreach($requiredFields as $field) {
    if(!isset($_POST[$field]) && !isset($_FILES[$field])) {
        array_push($unprovideFields, $field);
    }
}
if(count($unprovideFields) > 0) {
    http_response_code(400);
    die(json_encode(array(
        "code" => 1, 
        "message" => "The required keys are not posted: " . implode(", ", $unprovideFields),
        "security" => array(
            "nonce" => getNonce(),
            "timestamp" => date("Y-m-d H:i:s")
        )
    )));
}

$pageType = strtolower($_POST['pageType']); //Either 'preset' or 'upload'
$pageOrigin = $_POST['pageOrigin']; //The message to display on the page
$pageGroup = $_POST['pageGroup']; //The group to display the page to

if($pageType == "preset") {
    $pageFile = $_POST['pageFile']; //The custom audio name to play.
    $nonce = getNonce();
    $CALL_FILE = "Channel: LOCAL/" . $pageGroup . "@ext-paging\nApplication: Playback\nData: " . $pageFile . "\ncallerID: \"" . $pageOrigin . "\"\nmaxretries:" . $PAGE_MAX_RETRIES . "\nWaittime:" . $PAGE_MAX_WAITTIME;

    //Save call file to /var/spool/asterisk/outgoing/
    try {
        $CALL_FILE_PATH = "/var/spool/asterisk/outgoing/" . $nonce . ".call";
        file_put_contents($CALL_FILE_PATH, $CALL_FILE);

        http_response_code(200);
        die(json_encode(array(
            "code" => 0, 
            "message" => "Pre-Recorded Page successfully sent.",
            "security" => array(
                "nonce" => $nonce,
                "timestamp" => date("Y-m-d H:i:s")
            )
        )));
    } catch (Exception $e) {
        http_response_code(500);
        die(json_encode(array(
            "code" => 1, 
            "message" => "Failed writing to Asterisk outgoing spool. Please verify PHP has the correct permissions to /var/spool/asterisk/outgoing.",
            "security" => array(
                "nonce" => $nonce,
                "timestamp" => date("Y-m-d H:i:s")
            )
        )));
    }
    

    

    return;
}

if($pageType == "upload") {
    $nonce = getNonce();

    try {
        if(!isset($_FILES['pageFile'])) {
            http_response_code(400);
            die(json_encode(array(
                "code" => 1, 
                "message" => "The specified file is supplied in the pageFile parameter while pageType is 'upload'.",
                "security" => array(
                    "nonce" => $nonce,
                    "timestamp" => date("Y-m-d H:i:s")
                )
            )));
        }
        //Get the uploaded file
        $file = $_FILES['pageFile'];

        //Save the file to custom audio directory.
        $file_path = "/var/lib/asterisk/sounds/" . $PAGE_SOUNDDIR . "/custom/pbxPageAPI-" . $nonce . ".wav";
        move_uploaded_file($file['tmp_name'], $file_path);

        //Generate callfile
        $CALL_FILE = "Channel: LOCAL/" . $pageGroup . "@ext-paging\nApplication: Playback\nData: " . "custom/pbxPageAPI-" . $nonce . "\ncallerID: \"" . $pageOrigin . "\"\nmaxretries:" . $PAGE_MAX_RETRIES . "\nWaittime:" . $PAGE_MAX_WAITTIME;
        $CALL_FILE_PATH = "/var/spool/asterisk/outgoing/" . $nonce . ".call";

        file_put_contents($CALL_FILE_PATH, $CALL_FILE);

        
        ob_start();
        http_response_code(200);
        
        echo(json_encode(array(
            "code" => 0, 
            "message" => "Uploaded Page successfully sent.",
            "security" => array(
                "nonce" => $nonce,
                "timestamp" => date("Y-m-d H:i:s")
            )
        )));

        //Flush output buffer before sleeping to prevent delaying.
        ob_end_flush();
        flush();

        //Safely Delete the audio file
        sleep($PBX_READ_DELAY); //Wait for the call to be made and audio cached by Asterisk Context (3 seconds)
        unlink($file_path);

        return;

    
        } catch (Exception $e) {
            http_response_code(500);
            die(json_encode(array(
                "code" => 1, 
                "message" => "Failed writing to Asterisk sound directory. Please verify PHP has the correct permissions to /var/lib/asterisk/sounds/",
                "security" => array(
                    "nonce" => $nonce,
                    "timestamp" => date("Y-m-d H:i:s")
                )
            )));
        }


    return;
}

http_response_code(400);
die(json_encode(array(
    "code" => 1, 
    "message" => "The specified page type '" . $pageType . "' is invalid. Acceptable page types: 'preset', 'upload'",
    "security" => array(
        "nonce" => getNonce(),
        "timestamp" => date("Y-m-d H:i:s")
    )
)));


?>
