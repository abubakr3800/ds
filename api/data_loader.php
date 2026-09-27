<?php
/**
 * SC Datasheet Generator — Data Loader
 *
 * Loads and caches fixtures_app_data.json
 */

// Cache for fixtures data
$GLOBALS['fixtures_cache'] = null;

if (!defined('DATA_DIR')) {
    define('DATA_DIR', dirname(__DIR__) . '/data');
}

/**
 * Load fixtures from JSON file and add stable IDs
 *
 * @param bool $forceReload Force reload from disk
 * @return array Fixtures array with IDs
 * @throws Exception If data file not found
 */
function loadFixtures($forceReload = false) {
    // Return cached data if available
    if (!$forceReload && $GLOBALS['fixtures_cache'] !== null) {
        return $GLOBALS['fixtures_cache'];
    }

    $dataFile = DATA_DIR . '/fixtures_app_data.json';

    if (!file_exists($dataFile)) {
        throw new Exception(
            "Data file not found: $dataFile. " .
            "Run scripts/parse_datasheets.py and scripts/build_fixtures.py first."
        );
    }

    $jsonContent = file_get_contents($dataFile);
    if ($jsonContent === false) {
        throw new Exception("Failed to read data file: $dataFile");
    }

    $data = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception("JSON decode error: " . json_last_error_msg());
    }

    $fixtures = $data['fixtures'] ?? [];

    // Add stable IDs to fixtures and variants
    foreach ($fixtures as $i => &$fixture) {
        $fixture['id'] = $i;

        if (isset($fixture['v']) && is_array($fixture['v'])) {
            foreach ($fixture['v'] as $j => &$variant) {
                $variant['id'] = $j;
            }
        }
    }
    unset($fixture, $variant); // Break references

    // Cache the data
    $GLOBALS['fixtures_cache'] = $fixtures;

    return $fixtures;
}

/**
 * Get a single fixture by ID
 *
 * @param int $fixtureId
 * @return array|null Fixture data or null if not found
 */
function getFixtureById($fixtureId) {
    $fixtures = loadFixtures();
    return $fixtures[$fixtureId] ?? null;
}

/**
 * Get all categories from fixtures
 *
 * @return array Sorted array of unique categories
 */
function getCategories() {
    $fixtures = loadFixtures();
    $categories = [];

    foreach ($fixtures as $fixture) {
        if (!empty($fixture['cat'])) {
            $categories[$fixture['cat']] = true;
        }
    }

    $categories = array_keys($categories);
    sort($categories);

    return $categories;
}

/**
 * Filter fixtures by category and/or search query
 *
 * @param string $category Category filter
 * @param string $query Search query
 * @return array Filtered fixtures
 */
function filterFixtures($category = '', $query = '') {
    $fixtures = loadFixtures();

    // Filter by category
    if ($category) {
        $fixtures = array_filter($fixtures, function($fx) use ($category) {
            return isset($fx['cat']) && $fx['cat'] === $category;
        });
    }

    // Filter by search query
    if ($query) {
        $query = strtolower(trim($query));
        $fixtures = array_filter($fixtures, function($fx) use ($query) {
            return strpos(strtolower($fx['name'] ?? ''), $query) !== false;
        });
    }

    return array_values($fixtures); // Re-index array
}
