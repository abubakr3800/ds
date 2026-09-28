<?php
/**
 * SC Datasheet Generator — API Routes
 *
 * Handles all /api/* endpoints
 */

require_once __DIR__ . '/data_loader.php';
require_once __DIR__ . '/pdf_generator.php';

function handleAPIRoute($path, $method) {
    // Parse the path - don't use array_filter as it removes zeros
    $segments = explode('/', trim($path, '/'));

    // Debug log
    error_log("API Route called: " . $path);
    error_log("Segments: " . print_r($segments, true));

    // Remove 'api' prefix
    if (isset($segments[0]) && $segments[0] === 'api') {
        array_shift($segments);
    }

    error_log("After removing 'api': " . print_r($segments, true));

    // Route to appropriate handler
    if (empty($segments)) {
        jsonResponse(['error' => 'invalid api endpoint'], 400);
        return;
    }

    $endpoint = $segments[0];

    switch ($endpoint) {
        case 'health':
            handleHealth();
            break;

        case 'reload':
            handleReload();
            break;

        case 'categories':
            handleCategories();
            break;

        case 'fixtures':
            if (count($segments) === 1) {
                handleListFixtures();
            } elseif (count($segments) === 2) {
                // /api/fixtures/{id}
                handleGetFixture((int)$segments[1]);
            } elseif (count($segments) === 4 && $segments[2] === 'variants') {
                // /api/fixtures/{id}/variants/{vid}
                handleGetVariant((int)$segments[1], (int)$segments[3]);
            } elseif (count($segments) === 5 && $segments[2] === 'variants' && $segments[4] === 'pdf') {
                // /api/fixtures/{id}/variants/{vid}/pdf
                handleGetVariantPDF((int)$segments[1], (int)$segments[3]);
            } elseif (count($segments) === 5 && $segments[2] === 'variants' && $segments[4] === 'html') {
                // /api/fixtures/{id}/variants/{vid}/html
                handleGetVariantHTML((int)$segments[1], (int)$segments[3]);
            } else {
                jsonResponse(['error' => 'invalid fixtures endpoint'], 404);
            }
            break;

        default:
            jsonResponse(['error' => 'unknown api endpoint'], 404);
    }
}

/**
 * GET /api/health
 */
function handleHealth() {
    $fixtures = loadFixtures();
    jsonResponse([
        'status' => 'ok',
        'fixture_count' => count($fixtures)
    ]);
}

/**
 * GET /api/reload
 */
function handleReload() {
    try {
        $fixtures = loadFixtures(true); // Force reload
        jsonResponse([
            'status' => 'reloaded',
            'fixture_count' => count($fixtures)
        ]);
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 500);
    }
}

/**
 * GET /api/categories
 */
function handleCategories() {
    $fixtures = loadFixtures();
    $categories = [];

    foreach ($fixtures as $fixture) {
        if (!empty($fixture['cat'])) {
            $categories[$fixture['cat']] = true;
        }
    }

    $categories = array_keys($categories);
    sort($categories);

    jsonResponse(['categories' => $categories]);
}

/**
 * GET /api/fixtures
 * Query params: ?category=... &q=...
 */
function handleListFixtures() {
    $fixtures = loadFixtures();

    // Filter by category
    $category = $_GET['category'] ?? '';
    if ($category) {
        $fixtures = array_filter($fixtures, function($fx) use ($category) {
            return isset($fx['cat']) && $fx['cat'] === $category;
        });
        $fixtures = array_values($fixtures); // Re-index
    }

    // Filter by search query
    $query = $_GET['q'] ?? '';
    if ($query) {
        $query = strtolower(trim($query));
        $fixtures = array_filter($fixtures, function($fx) use ($query) {
            return strpos(strtolower($fx['name'] ?? ''), $query) !== false;
        });
        $fixtures = array_values($fixtures); // Re-index
    }

    jsonResponse([
        'fixtures' => $fixtures,
        'count' => count($fixtures)
    ]);
}

/**
 * GET /api/fixtures/{id}
 */
function handleGetFixture($fixtureId) {
    $fixtures = loadFixtures();

    if (!isset($fixtures[$fixtureId])) {
        jsonResponse(['error' => 'fixture not found'], 404);
        return;
    }

    jsonResponse($fixtures[$fixtureId]);
}

/**
 * GET /api/fixtures/{id}/variants/{vid}
 */
function handleGetVariant($fixtureId, $variantId) {
    $fixtures = loadFixtures();

    if (!isset($fixtures[$fixtureId])) {
        jsonResponse(['error' => 'fixture not found'], 404);
        return;
    }

    $fixture = $fixtures[$fixtureId];
    $variants = $fixture['v'] ?? [];

    if (!isset($variants[$variantId])) {
        jsonResponse(['error' => 'variant not found'], 404);
        return;
    }

    jsonResponse([
        'fixture' => $fixture,
        'variant' => $variants[$variantId]
    ]);
}

/**
 * GET /api/fixtures/{id}/variants/{vid}/pdf
 */
function handleGetVariantPDF($fixtureId, $variantId) {
    $fixtures = loadFixtures();

    if (!isset($fixtures[$fixtureId])) {
        jsonResponse(['error' => 'fixture not found'], 404);
        return;
    }

    $fixture = $fixtures[$fixtureId];
    $variants = $fixture['v'] ?? [];

    if (!isset($variants[$variantId])) {
        jsonResponse(['error' => 'variant not found'], 404);
        return;
    }

    $variant = $variants[$variantId];

    try {
        $pdf = generatePDF($fixture, $variant);
        $filename = generatePDFFilename($fixture, $variant);

        if (ob_get_length()) {
            ob_clean();
        }

        // Send PDF response with full CORS expose headers
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Expose-Headers: Content-Disposition, Content-Length, X-PDF-Engine');
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
        header('Content-Length: ' . strlen($pdf));
        header('Cache-Control: no-store');
        echo $pdf;
        exit;
    } catch (\Throwable $e) {
        error_log("PDF generation error: " . $e->getMessage());
        jsonResponse(['error' => 'pdf generation failed: ' . $e->getMessage()], 500);
    }
}

/**
 * GET /api/fixtures/{id}/variants/{vid}/html
 */
function handleGetVariantHTML($fixtureId, $variantId) {
    require_once __DIR__ . '/template_renderer.php';
    $fixtures = loadFixtures();

    if (!isset($fixtures[$fixtureId])) {
        jsonResponse(['error' => 'fixture not found'], 404);
        return;
    }

    $fixture = $fixtures[$fixtureId];
    $variants = $fixture['v'] ?? [];

    if (!isset($variants[$variantId])) {
        jsonResponse(['error' => 'variant not found'], 404);
        return;
    }

    $variant = $variants[$variantId];

    try {
        $html = renderDatasheetTemplate($fixture, $variant, false);
        if (ob_get_length()) {
            ob_clean();
        }
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo $html;
        exit;
    } catch (\Throwable $e) {
        error_log("HTML datasheet render error: " . $e->getMessage());
        jsonResponse(['error' => 'html render failed: ' . $e->getMessage()], 500);
    }
}

/**
 * Send JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}
