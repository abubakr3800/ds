<?php
/**
 * SC Datasheet Generator — Main Root Entry Point
 *
 * Routes all requests through this single entry file at the project root.
 * Serves frontend HTML, static assets, images, and API endpoints.
 */

// Error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define base paths from root
define('BASE_DIR', __DIR__);
define('DATA_DIR', BASE_DIR . '/data');
define('APP_DIR', BASE_DIR . '/app');
define('ASSETS_DIR', BASE_DIR . '/assets');
define('IMAGES_DIR', APP_DIR . '/images');

// Load Composer autoloader if present
if (file_exists(BASE_DIR . '/vendor/autoload.php')) {
    require_once BASE_DIR . '/vendor/autoload.php';
}

// Simple router
$request_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Normalize path
$path = rtrim($request_uri, '/');

// Route handling
if ($path === '' || $path === '/' || $path === '/index.php') {
    // Serve the main HTML page
    serveHTML();
} elseif (strpos($path, '/api/') === 0) {
    // API routes
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Expose-Headers: Content-Disposition, Content-Length');
    if ($request_method === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
    handleAPI($path, $request_method);
} elseif (strpos($path, '/app/') === 0) {
    // Serve app assets
    $file = urldecode(substr($path, 5));
    serveFile(APP_DIR . '/' . $file);
} elseif (strpos($path, '/assets/') === 0) {
    // Serve assets (logos, fonts, etc.)
    $file = urldecode(substr($path, 8));
    serveFile(ASSETS_DIR . '/' . $file);
} elseif (strpos($path, '/images/') === 0) {
    // Serve fixture images
    $file = urldecode(substr($path, 8));
    serveFile(IMAGES_DIR . '/' . $file);
} elseif (strpos($path, '/data/') === 0) {
    // Serve data JSON if requested
    $file = urldecode(substr($path, 6));
    serveFile(DATA_DIR . '/' . $file);
} else {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<h1>404 Not Found</h1><p>The requested resource was not found on this server.</p>';
}

/**
 * Serve the main HTML application
 */
function serveHTML() {
    $html_file = APP_DIR . '/datasheet-generator.html';
    if (!file_exists($html_file)) {
        http_response_code(404);
        die('HTML application file not found at: ' . htmlspecialchars($html_file));
    }
    header('Content-Type: text/html; charset=utf-8');
    readfile($html_file);
}

/**
 * Handle API requests
 */
function handleAPI($path, $method) {
    $routesFile = BASE_DIR . '/api/routes.php';
    if (!file_exists($routesFile)) {
        http_response_code(500);
        die('API routes file not found at: ' . htmlspecialchars($routesFile));
    }
    require_once $routesFile;
    handleAPIRoute($path, $method);
}

/**
 * Serve static files with appropriate MIME types and urldecode handling
 */
function serveFile($filepath) {
    if (!file_exists($filepath)) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>404 Not Found</h1><p>File not found: ' . htmlspecialchars(basename($filepath)) . '</p>';
        return;
    }

    $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
    $mime_types = [
        'html' => 'text/html; charset=utf-8',
        'css'  => 'text/css; charset=utf-8',
        'js'   => 'application/javascript; charset=utf-8',
        'json' => 'application/json; charset=utf-8',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif'  => 'image/gif',
        'svg'  => 'image/svg+xml',
        'pdf'  => 'application/pdf',
        'ttf'  => 'font/ttf',
        'woff' => 'font/woff',
        'woff2'=> 'font/woff2',
    ];

    $mime = $mime_types[$extension] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: public, max-age=86400');
    readfile($filepath);
}
