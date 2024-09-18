<?php declare(strict_types=1);

use Shopware\Core\Framework\Util\Hasher;

$uri = $_SERVER['REQUEST_URI'] ?? '/v1/release/update';
$fileName = __DIR__ . '/update.zip';

if (str_starts_with($uri, '/swplatform/autoupdate')) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

if (str_starts_with($uri, '/v1/release/update')) {
    header('Content-Type: application/json');
    echo json_encode([
        'version' => '6.6.0.0',
        'release_date' => false,
        'security_update' => false,
        'uri' => 'http://localhost:8060/update.zip',
        'size' => filesize($fileName),
        'sha1' => Hasher::hash((string) file_get_contents($fileName), 'sha1'),
        'sha256' => Hasher::hash((string) file_get_contents($fileName), 'sha256'),
        'checks' => [],
        'changelog' => [
            'de' => [
                'language' => 'de',
                'changelog' => 'Changelog',
            ],
            'en' => [
                'language' => 'en',
                'changelog' => 'Changelog',
            ],
        ],
        'isNewer' => true,
    ], \JSON_THROW_ON_ERROR);
    exit;
}

if (str_starts_with($uri, '/update.zip')) {
    header('Content-Type: application/zip');
    echo file_get_contents($fileName);
    exit;
}

http_response_code(404);
