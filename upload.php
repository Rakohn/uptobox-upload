<?php
// Quick and dirty POWAAAAAAAAAAAA

//// Paste you uptobox token here (you can get it from your uptobox account management page)
define('API_TOKEN', 'USER_TOKEN');
// Paste your base folder path here, don't forget to double all directory separator
define('BASE_PATH', 'DRIVE_LETTER:\\PATH\\TO\\BASE\\FOLDER');

$targetFolder      = realpath(BASE_PATH);
$folderName        = basename($targetFolder);

createUptoboxFolder('//', $folderName);
parseFolderAndManageUpload($targetFolder, '//' . $folderName);

/**
 * Parse folders recursively
 *
 * @param string $LocalTargetFolder
 * @param string $uptoboxTargetFolderPath
 */
function parseFolderAndManageUpload($LocalTargetFolder, $uptoboxTargetFolderPath)
{
    $baseFolderContent = array_diff(scandir($LocalTargetFolder), ['.', '..']);

    foreach ($baseFolderContent as $content) {
        $path = $LocalTargetFolder . '\\' . $content;

        if (is_dir($path)) {
            $uptoboxNewFolderData = createUptoboxFolder($uptoboxTargetFolderPath, $content);
            $uptoboxPath = $uptoboxTargetFolderPath . '/' . $content;

            echo 'Create uptobox folder ' . $uptoboxPath . '<br>';

            parseFolderAndManageUpload($path, $uptoboxPath, $uptoboxNewFolderData);
        } else {
            $mimeType = mime_content_type($path);
            $curlFile = curl_file_create($path, $mimeType, basename($path));

            uploadFile($curlFile);

            //Dumb code because of dumb API...
            $uptoboxFiles  = getUptoboxFilesData();
            $uptoboxFolder = getUptoboxFilesData($uptoboxTargetFolderPath);
            $filesIds      = [];

            foreach($uptoboxFiles->data->files as $file) {
                $filesIds[] = $file->file_code;
            }

            $uptoboxFolderId = $uptoboxFolder->data->currentFolder->fld_id;

            moveUptoboxFiles(implode(',', $filesIds), $uptoboxFolderId);

            echo $path . ' uploaded to ' . $uptoboxTargetFolderPath . ' uptobox folder<br>';
        }
    }
}

/**
 * Retrieve upload URL from uptobox API
 *
 * Note for myself, does it need url per file to upload or could it be reusable ?
 * Need to try...
 *
 * @return string The upload URL
 */
function getUploadUrl()
{
    $url  = 'https://uptobox.com/api/upload';
    $data = [
        'token' => API_TOKEN,
    ];

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_URL, $url);

    $uptoboxResponse = json_decode(curl_exec($curl));

    curl_close($curl);

    return 'https:' . $uptoboxResponse->data->uploadLink;
}

/**
 * Upload a file to uptobox
 *
 * @param CURLFile $curlFile
 */
function uploadFile($curlFile)
{
    $uploadLink = getUploadUrl();
    $post = array('file_contents'=> $curlFile);
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $uploadLink);
    curl_setopt($curl, CURLOPT_POST,1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

    curl_exec($curl);
    curl_close($curl);
}

/**
 * Move uptobox files to target uptobox folder
 *
 * @param string $filesIds Comma seperated uptobox file ID
 * @param integer $folderId Uptobox folder ID
 */
function moveUptoboxFiles($filesIds, $folderId)
{
    $url = 'https://uptobox.com/api/user/files';
    $data = [
        'token'              => API_TOKEN,
        'file_codes'         => $filesIds,
        'destination_fld_id' => $folderId,
        'action'             => 'move'
    ];

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_URL, $url);

    curl_exec($curl);
    curl_close($curl);
}

/**
 * Create an uptobox folder to target uptobox folder
 *
 * @param string $folderPath Target path
 * @param type $folderName New folder name
 *
 * @return stdClass Uptobox create folder service response
 */
function createUptoboxFolder($folderPath, $folderName)
{
    $url = 'https://uptobox.com/api/user/files';
    $data = [
        'token' => API_TOKEN,
        'path'  => $folderPath,
        'name'  => $folderName
    ];

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_URL, $url);

    $result = curl_exec($curl);

    curl_close($curl);

    return json_decode($result);
}

/**
 * Retrieve files and folder from uptobox target path
 *
 * @param string $targetPath Uptobox target path
 * @return stdClass Uptobox get files service response
 */
function getUptoboxFilesData($targetPath = '//')
{
    $url = "https://uptobox.com/api/user/files?token=" . API_TOKEN
        . "&limit=100&path=" . urlencode($targetPath);

    return json_decode(file_get_contents($url));
}

/**
 * Set file to private state. Meaning not referenced by any research motor
 *
 * @param string|integer $fileId Uptobox file identifier
 */
function setFileAsPrivate($fileId)
{
    $url = 'https://uptobox.com/api/user/files';
    $data = [
        'token'     => API_TOKEN,
        'file_code' => $fileId,
        'public'    => 0,
    ];

    $curl = curl_init();

    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_URL, $url);

    curl_exec($curl);
    curl_close($curl);
}