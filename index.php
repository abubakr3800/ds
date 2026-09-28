<?php
/**
 * SC Datasheet Generator — Main Root Entry Point
 *
 * Routes all requests through this single entry file at the project root.
 * Supports:
 *  1. PHP Built-in Development Server: php -S localhost:8000 index.php
 *  2. Production Subdirectory: https://example.com/ds/
 *  3. Production Domain Root:  https://example.com/
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

// Fast-path for PHP built-in web server (php -S localhost:8000 index.php):
// If the requested URI matches an existing file on disk, let PHP serve it natively.
if (php_sapi_name() === 'cli-server') {
    $urlPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $staticFile = BASE_DIR . $urlPath;
    if ($urlPath !== '/' && $urlPath !== '/index.php' && is_file($staticFile)) {
        return false; // Built-in server serves this file directly
    }
}

// Load Composer autoloader if present
if (file_exists(BASE_DIR . '/vendor/autoload.php')) {
    require_once BASE_DIR . '/vendor/autoload.php';
}

// Determine web base directory if hosted in a subdirectory (e.g., /ds or /sub/folder)
// Under cli-server, the app is always at the root of the server ('')
$baseDirUrl = '';
if (php_sapi_name() !== 'cli-server') {
    $cleanScriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    // Check if SCRIPT_NAME points to a php script (e.g. /ds/index.php)
    if (preg_match('/\.php$/i', $cleanScriptName)) {
        $dir = rtrim(str_replace('\\', '/', dirname($cleanScriptName)), '/');
        if ($dir !== '.' && $dir !== '/' && $dir !== '') {
            $baseDirUrl = $dir;
        }
    }
}

if (!defined('WEB_BASE_URL')) {
    define('WEB_BASE_URL', $baseDirUrl);
}

// Simple router
$request_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$request_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Strip base directory prefix if present
$path = $request_uri;
if ($baseDirUrl !== '' && strpos($path, $baseDirUrl) === 0) {
    $path = substr($path, strlen($baseDirUrl));
}

// Subdirectory installs: /ds -> /ds/ so relative asset URLs on the main page resolve correctly
if ($baseDirUrl !== '' && rtrim($request_uri, '/') === $baseDirUrl && substr($request_uri, -1) !== '/') {
    header('Location: ' . $baseDirUrl . '/', true, 301);
    exit;
}

// Normalize path (ensure leading slash, strip trailing slash except root)
$path = '/' . ltrim(rtrim($path, '/'), '/');
if ($path === '//') {
    $path = '/';
}

// Route handling
if ($path === '' || $path === '/' || $path === '/index.php') {
    // Serve the main HTML page
    serveHTML();
} elseif ($path === '/api' || strpos($path, '/api/') === 0) {
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
    // Never let browsers/CDNs keep an old copy of the app shell (stale JS caused blank PDFs)
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
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
 * Serve static files with appropriate MIME types and path validation
 */
function serveFile($filepath) {
    if (!file_exists($filepath)) {
        http_response_code(404);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>404 Not Found</h1><p>File not found: ' . htmlspecialchars(basename($filepath)) . '</p>';
        return;
    }

    // Security check: ensure path is within BASE_DIR
    $realBase = realpath(BASE_DIR);
    $realFile = realpath($filepath);
    if ($realFile === false || strpos($realFile, $realBase) !== 0) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<h1>403 Forbidden</h1>';
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
