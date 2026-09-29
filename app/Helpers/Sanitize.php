<?php
// app/Helpers/Sanitize.php - Input validation & sanitization, Output Escaping, File Upload Security

function e($string) {
    return htmlspecialchars((string)($string ?? ''), ENT_QUOTES, 'UTF-8');
}

function sanitizeInput($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = sanitizeInput($value);
        }
        return $data;
    }
    return trim((string)$data);
}

function validateUploadedImage($file) {
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new Exception('Invalid parameters.');
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return null;
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            throw new Exception('Exceeded file size limit.');
        default:
            throw new Exception('Unknown error uploading file.');
    }

    if ($file['size'] > MAX_UPLOAD_SIZE) {
        throw new Exception('File size exceeds maximum allowed (5MB).');
    }

    $allowedMimes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp'
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!array_key_exists($ext, $allowedMimes) || !in_array($mimeType, $allowedMimes, true)) {
        throw new Exception('Invalid file format. Only JPG, PNG, and WEBP images are allowed.');
    }

    if (!getimagesize($file['tmp_name'])) {
        throw new Exception('Uploaded file is not a valid image.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0755, true);
    }

    $randomFilename = sprintf('%s.%s', bin2hex(random_bytes(16)), $ext);
    $destination = UPLOAD_DIR . $randomFilename;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new Exception('Failed to save uploaded image.');
    }

    return UPLOAD_URL . $randomFilename;
}
