<?php
/**
 * Vercel API Entry Point
 * Routes requests to the example directory
 *
 * Requires PHP 8.1+ (vercel-php@0.7.4 uses PHP 8.2)
 */

// Verify PHP version
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'PHP 8.1+ required. Current version: ' . PHP_VERSION
    ]);
    exit;
}

require dirname(__DIR__) . '/example/get.php';
