<?php
function is_remote_image_url(string $value): bool {
    return $value !== '' && preg_match('/^https?:\\/\\//i', $value);
}

function save_profile_image_from_url(string $url, string $folder, string $basename): ?string {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    $context = stream_context_create([
        'http' => [
            'timeout' => 6,
            'follow_location' => 1,
        ],
    ]);
    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
        return null;
    }

    $imageInfo = @getimagesizefromstring($data);
    $mime = is_array($imageInfo) && !empty($imageInfo['mime']) ? $imageInfo['mime'] : 'image/jpeg';
    $extMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];
    $ext = $extMap[$mime] ?? 'jpg';

    $safeBase = preg_replace('/[^A-Za-z0-9_-]/', '', $basename);
    if ($safeBase === '') {
        $safeBase = uniqid('img_', true);
    }

    $dir = __DIR__ . "/../profilepictures/$folder";
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $filename = $safeBase . '.' . $ext;
    $path = $dir . '/' . $filename;
    if (@file_put_contents($path, $data) === false) {
        return null;
    }

    return $filename;
}
