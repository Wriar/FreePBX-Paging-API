# FreePBX-Paging-API
A simple PHP API to dynamically send custom SIP Audio Pages/Broadcast to Asterisk without the need of FreePBX Page Pro.

# Description
Asterisk and FreePBX does not have a functionality to remotely trigger a page or announcement broadcast using an API. While this may be available in a paid later commercial upgrade in the Sangoma Page Pro module, developers have no way to dynamically broadcast messages on their FreePBX Servers by their own programs. This simple PHP file can be easily added within the FreePBX web server itself to provide a secure way to send dynamic pages from your own applications, such as VOIP emergency notification systems or bell systems. **This comes with the ability to play either a prerecorded message or to upload a custom audio file**

# Installation Instructions
1. Download the ``pbxPageAPI.php`` and set a secret key in the ``$SECRET_KEY`` variable.
2. SSH into your FreePBX server and upload the ``pbxPageAPI.php`` file to ``/var/www/html``. This API can now be accessed with the IP address or FQDN of your FreePBX instance on ``http://<SERVER_IP>/pbxPageAPI.php``. That's it.

# Usage Instructions
The API accepts POST requests only and has the ability to broadcast a *prerecorded* audio file already uploaded to FreePBX custom audios or broadcast a file *uploaded* within the POST request body. The following POST parameters MUST be supplied when making a POST request to ``pbxPageAPI.php``
<table>
  <tr>
    <td><b>POST Body Key/Field</b></td>
    <td><b>Value Description</b></td>
  </tr>
  <tr>
    <td>
      <code>key</code>
    </td>
    <td>
      The API Key set in the <code>$SECRET_KEY</code> variable in PHP file.<br/><br/>
      <b>Example:</b> <code>passw0rd</code>
    </td>
  </tr>

  <tr>
     <td>
      <code>pageType</code>
    </td>
    <td>
      The type of message that will be broadcasted. 'preset' broadcasts an audio file already uploaded to Asterisk by the FreePBX System Recordings GUI. 'upload' broadcasts an audio file that is uploaded in the POST request within the field <code>pageFile</code><br/><br/>
      <b>Acceptable Values: <code>upload</code> <code>preset</code></b><br/>
      <b>Example:</b> <code>preset</code>
    </td>
  </tr>

  <tr>
     <td>
      <code>pageGroup</code>
    </td>
    <td>
      The Asterisk page group extension the message should be broadcast to. For FreePBX, a page group can be created under Applications > Paging & Intercom.<br/><br/>
      <b>Acceptable Values:</b> An Paging Group Number<br/>
      <b>Example:</b> <code>3003</code>
    </td>
  </tr>

  <tr>
     <td>
      <code>pageOrigin</code>
    </td>
    <td>
      A caller ID that will be displayed on the phones being broadcasted to. This is also useful for displaying a message when the audio is being played. If string is not in callerID format, then message will display but caller may display as "unknown". This is a cavet with Asterisk callfiles<br/><br/>
      <b>Acceptable Values:</b> String or String in CallerID Format<br/>
      <b>Example:</b> <code>Hi There</code> or <code>Hi There &lt;the-administrator&gt;</code>
    </td>
  </tr>

  <tr>
     <td>
      <code>pageFile</code>
    </td>
    <td>
      If <code>pageType</code> is 'preset', this field contains text which is the custom System Recording that is to be broadcast. To add a system recording in FreePBX, navigate to Admin > System Recordings. The recording name to add is under "File List for [your language]" and usually starts with "custom/". <br/>
            If <code>pageType</code> is 'upload', this field MUST contain a <b>8000hz, Single-Channel MONO, 2-Sample Width .wav file </b></br>
       <br><br><b>Acceptable Values:</b> A Custom Recording name or POST file body<br/>
      <b>Example:</b> <code>custom/hi</code>
    </td>
    
  </tr>

  
</table>


# Advanced PHP Configuration Variables
These parameters are probably unneeded in a standard FreePBX installation but can help fix a few potential problems depending on your server config. **These values must be hardcoded in the pbxPageAPI.php File**
* ``$PAGE_MAX_WAITTIME``: (default=3) Maximum amount of time to wait for devices to answer the page before ignoring.
* ``$PAGE_MAX_RETRIES``: (default=1) Maximum Paging Retry Count.
* ``$PAGE_SOUNDDIR``: (default=en) The directory PBX Stores Soundfiles. Do not change this unless your Asterisk audio configuration is not in English. Custom audio is uploaded by FreePBX in /var/lib/asterisk/sounds/ + (this param) + /custom/FILENAME.wav
* ``$PBX_READ_DELAY``: (default=3) When ``pageType`` is set to ``upload``, Asterisk needs time to read the uploaded .wav file into cache before it is deleted by our API to save space. However, this results in a noticeable delay for the API request if the number is large. For most instances, 3 seconds should be enough.
* ``$ENABLE_OUTPUT_BUFFERING``: (default=true) When ``pageType`` is set to ``upload`` and the ``$PBX_READ_DELAY`` is set, buffer a 200 OK response to satisfy the client before the success JSON is sent over.

# API Responses
The API will respond in JSON with the following properties:
* ``code``: 0= Success. 1= Authentication/User Failure 2= Server Failure
* ``message``: The status message of the request.
* ``security > nonce``: The nonce attribute which is a randomly generated string that varies per request. The recording names are prepended with pageAPI and postpended with the nonce if custom recordings are uploaded and saved. This is useful for cleanup if your PHP instance has no deletion permissions to /var/lib. 
* ``security > timestamp``: Timestamp in ``Y-m-d H:i:s`` for when the request was made.
