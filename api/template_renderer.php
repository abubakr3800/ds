<?php
/**
 * SC Datasheet Generator — Modular Template Renderer
 *
 * Assembles datasheet pages based on detected device profile:
 *  - industrial_sc: 11-page SC lighting datasheet (Flood, Street, Highbay, Arena)
 *  - battery_emergency: 5-page Emergency Power Kit & Inverter datasheet
 *  - commercial_landscape: 2-page Commercial / Landscape luminaire datasheet
 *  - solar_fixture: 2-page Standalone Solar luminaire datasheet
 */

if (!defined('BASE_DIR')) {
    define('BASE_DIR', dirname(__DIR__));
}

/**
 * Detect the device profile based on fixture category, naming, and technical data.
 *
 * @param array $fixture Fixture data
 * @param array $variant Variant data
 * @return string One of 'battery_emergency', 'solar_fixture', 'industrial_sc', 'commercial_landscape'
 */
function detectDeviceType($fixture, $variant) {
    $cat = $fixture['cat'] ?? '';
    $name = $fixture['name'] ?? '';
    $vName = $variant['name'] ?? '';
    $raw = $variant['raw'] ?? [];

    // 1. Battery / Emergency Kit
    if ($cat === 'Battery datasheet' 
        || stripos($name, 'battery') !== false 
        || stripos($vName, 'battery') !== false 
        || stripos($raw['product name'] ?? '', 'emergency') !== false) {
        return 'battery_emergency';
    }

    // 2. Solar
    if ($cat === 'Solar' 
        || stripos($name, 'solar') !== false 
        || stripos($vName, 'solar') !== false 
        || isset($raw['solar panel']) 
        || isset($raw['solar panel power'])) {
        return 'solar_fixture';
    }

    // 3. Industrial SC Lighting
    $scIndustrialCats = ['Flood Light', 'Street Light', 'Highbay', 'Arena Sport'];
    if (in_array($cat, $scIndustrialCats)) {
        return 'industrial_sc';
    }
    if (stripos($name, 'flood light') !== false 
        || stripos($name, 'street light') !== false 
        || stripos($name, 'highbay') !== false 
        || stripos($name, 'arena sport') !== false 
        || (isset($raw['thickness of fin']) && isset($variant['x']['chip_count']))) {
        return 'industrial_sc';
    }

    // 4. Commercial / Landscape Lighting
    return 'commercial_landscape';
}

/**
 * Find the hero product image for a fixture variant
 */
function findHeroImage($fixture, $variant) {
    $cat = $fixture['cat'] ?? '';
    $catDir = BASE_DIR . '/app/images/' . $cat;
    if (!is_dir($catDir)) return null;

    $candidates = [
        $variant['name'] ?? '',
        $fixture['name'] ?? '',
    ];

    foreach ($candidates as $name) {
        if (!$name) continue;
        $clean = preg_replace('/[^a-zA-Z0-9]+/', '*', trim($name));
        $matches = glob($catDir . '/*' . $clean . '*p1_img1.png');
        if (!empty($matches)) {
            return $matches[0];
        }
        $words = preg_split('/\s+/', trim($name));
        if (count($words) >= 2) {
            $matches = glob($catDir . '/*' . $words[0] . '*' . $words[1] . '*p1_img1.png');
            if (!empty($matches)) {
                return $matches[0];
            }
        }
    }

    $any = glob($catDir . '/*p1_img1.png');
    return !empty($any) ? $any[0] : null;
}

/**
 * Find the body/heat-sink image for a fixture variant
 */
function findHeatSinkImage($fixture, $variant) {
    $cat = $fixture['cat'] ?? '';
    $catDir = BASE_DIR . '/app/images/' . $cat;
    if (!is_dir($catDir)) return null;

    $words = preg_split('/\s+/', trim($fixture['name'] ?? ''));
    if (count($words) >= 2) {
        $matches = glob($catDir . '/*' . $words[0] . '*' . $words[1] . '*p2_img1.png');
        if (!empty($matches)) return $matches[0];
        $matches = glob($catDir . '/*' . $words[0] . '*' . $words[1] . '*p1_img2.png');
        if (!empty($matches)) return $matches[0];
    }
    $any = glob($catDir . '/*p2_img1.png');
    return !empty($any) ? $any[0] : null;
}

/**
 * Convert a file to a base64 Data URI
 */
function imageToDataUri($path) {
    if (!$path || !file_exists($path)) return null;
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = ($ext === 'svg') ? 'image/svg+xml' : 'image/png';
    $data = file_get_contents($path);
    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

/**
 * Clean and normalize text strings from OCR
 */
function cleanOcrText($str) {
    if (!$str) return '';
    $s = trim(preg_replace('/^[•\s\*\-]+/', '', $str));
    return $s;
}

/**
 * Render the datasheet HTML using modular assembly.
 *
 * @param array $fixture Fixture data
 * @param array $variant Variant data
 * @param bool $forPdf True if rendering for PDF conversion (uses embedded data URIs)
 * @return string Rendered HTML
 */
function renderDatasheetTemplate($fixture, $variant, $forPdf = false) {
    $baseTemplatePath = BASE_DIR . '/templates/datasheet-base.html';
    if (!file_exists($baseTemplatePath)) {
        throw new Exception("Base datasheet template not found: $baseTemplatePath");
    }

    $baseHtml = file_get_contents($baseTemplatePath);

    // Identify profile
    $deviceType = detectDeviceType($fixture, $variant);

    // Determine basic product metadata
    $cat = $fixture['cat'] ?? 'Lighting Fixture';
    $name = $fixture['name'] ?? 'Lighting Fixture';
    $power = $variant['power'] ?? '';
    if ($power && stripos($name, (string)$power) === false && $deviceType !== 'battery_emergency') {
        $name .= ' ' . $power . 'W';
    }

    // Model number
    if ($deviceType === 'battery_emergency') {
        $modelNumber = $variant['raw']['model number'] ?? 'SC-HY-04C';
        if (stripos($modelNumber, 'SC-') !== 0) {
            $modelNumber = 'SC-' . $modelNumber;
        }
    } else {
        $catCode = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $cat), 0, 3));
        $modelNumber = 'SC-' . ($power ? $power . 'W-' : '') . ($catCode ?: 'GEN');
    }

    // Resolve images
    $imgData = $variant['images'] ?? [];
    $resolveImage = function($key, $defaultRel = null) use ($imgData, $fixture, $variant) {
        $relPath = $imgData[$key] ?? null;
        if (!$relPath && $key === 'hero') {
            $f = findHeroImage($fixture, $variant);
            if ($f) return imageToDataUri($f);
        }
        if (!$relPath && $key === 'heatsink') {
            $f = findHeatSinkImage($fixture, $variant);
            if ($f) return imageToDataUri($f);
        }
        if (!$relPath && $defaultRel) {
            $relPath = $defaultRel;
        }
        if ($relPath) {
            $fullPath = BASE_DIR . '/app/images/' . $relPath;
            if (file_exists($fullPath)) {
                return imageToDataUri($fullPath);
            }
            $base = basename($relPath);
            $found = glob(BASE_DIR . '/app/images/**/' . $base, GLOB_NOSORT);
            if (!empty($found) && file_exists($found[0])) {
                return imageToDataUri($found[0]);
            }
        }
        if ($defaultRel) {
            $fullDefault = BASE_DIR . '/app/images/' . $defaultRel;
            if (file_exists($fullDefault)) {
                return imageToDataUri($fullDefault);
            }
        }
        return null;
    };

    $heroUrl = $resolveImage('hero');
    $heatSinkUrl = $resolveImage('heatsink');
    $chipUrl = $resolveImage('chip', 'template/chip.png');
    $driverUrl = $resolveImage('driver', 'template/driver.png');
    $driverBodyUrl = $resolveImage('driver_body', 'template/driver_body.png');

    // Logo & Watermark
    $logoFile = BASE_DIR . '/assets/images/logo.svg';
    $logoDataUri = imageToDataUri($logoFile);
    $logoSrc = $logoDataUri ?: '/assets/images/logo.svg';

    $baseHtml = str_replace('__FONTS_BASE__', '', $baseHtml);
    $baseHtml = str_replace('__LOGO_URL__', $logoDataUri ?: '', $baseHtml);
    $baseHtml = str_replace('src="logo.png"', 'src="' . $logoSrc . '"', $baseHtml);

    // Assemble modules based on profile
    $contentPages = '';
    $modulesDir = BASE_DIR . '/templates/modules';

    if ($deviceType === 'battery_emergency') {
        $coverTpl = file_exists($modulesDir . '/cover_hero.html') ? file_get_contents($modulesDir . '/cover_hero.html') : '';
        $battTpl = file_exists($modulesDir . '/battery_emergency.html') ? file_get_contents($modulesDir . '/battery_emergency.html') : '';
        $contentPages = $coverTpl . "\n" . $battTpl;
    } elseif ($deviceType === 'commercial_landscape') {
        $landTpl = file_exists($modulesDir . '/commercial_landscape.html') ? file_get_contents($modulesDir . '/commercial_landscape.html') : '';
        $contentPages = $landTpl;
    } elseif ($deviceType === 'solar_fixture') {
        $solarTpl = file_exists($modulesDir . '/solar_system.html') ? file_get_contents($modulesDir . '/solar_system.html') : '';
        $contentPages = $solarTpl;
    } else {
        // 'industrial_sc' (Full 11 pages)
        $mFiles = [
            'cover_hero.html',
            'toc.html',
            'general_specs.html',
            'body_heatsink.html',
            'led_chips_curves.html',
            'driver_specs.html'
        ];
        foreach ($mFiles as $mf) {
            $p = $modulesDir . '/' . $mf;
            if (file_exists($p)) {
                $contentPages .= file_get_contents($p) . "\n";
            }
        }
    }

    // Abstract text
    $abstractHtml = '';
    if ($deviceType === 'battery_emergency') {
        $abstractHtml = '<p class="body-p" style="margin-bottom:3.5mm;line-height:1.6;">Short Circuit Emergency Power Kit (Integrated Type) engineered for automatic emergency backup operation.</p>'
            . '<p class="body-p" style="margin-bottom:3.5mm;line-height:1.6;">Operates across 220-240V AC 50/60Hz with central battery system (CBS) compatibility up to DC 254V.</p>'
            . '<p class="body-p" style="margin-bottom:3.5mm;line-height:1.6;">Provides reliable emergency power for up to 3 hours with automatic push-button diagnostics and manual test facility.</p>'
            . '<p class="body-p" style="margin-bottom:3.5mm;line-height:1.6;">Certified in full accordance with European CE, RoHS 2.0, EMC Directive 2014/30/EU, and LVD Directive 2014/35/EU.</p>';
        $productSubtitle = 'Automatic Emergency Power Supply System for Commercial & Industrial Luminaires';
    } else {
        $abstractHtml = '<p class="body-p" style="margin-bottom:3.5mm;line-height:1.6;">Short circuit ' . htmlspecialchars($cat) . ' for industrial use.</p>'
            . '<p class="body-p" style="margin-bottom:3.5mm;line-height:1.6;">High performance of 98% of led efficiency within more than 50,000 operating hours.</p>'
            . '<p class="body-p" style="margin-bottom:3.5mm;line-height:1.6;">' . htmlspecialchars($cat) . ' is protected against the electricity instability with over voltage, current, and temperature protection.</p>'
            . '<p class="body-p" style="margin-bottom:3.5mm;line-height:1.6;">Consists of Philips LED chips and Short Circuit isolated driver with 5 years warranty.</p>';
        $productSubtitle = 'High lux, High performance, High protection with long life-span for industrial use.';
    }

    // Replace product metadata in content
    $contentPages = str_replace('{{{ product.abstractHtml }}}', $abstractHtml, $contentPages);
    $contentPages = str_replace('{{ product.name }}', htmlspecialchars($name), $contentPages);
    $contentPages = str_replace('{{ product.typeName }}', htmlspecialchars($cat), $contentPages);
    $contentPages = str_replace('{{ product.modelNumber }}', htmlspecialchars($modelNumber), $contentPages);
    $contentPages = str_replace('{{ product.subtitle }}', htmlspecialchars($productSubtitle), $contentPages);
    $contentPages = str_replace('src="logo.png"', 'src="' . $logoSrc . '"', $contentPages);

    // Hero image conditional
    if ($heroUrl) {
        $contentPages = preg_replace('/\{\{#if product\.heroImageUrl\}\}(.*?)\{\{else\}\}(.*?)\{\{\/if\}\}/s', '$1', $contentPages);
        $contentPages = preg_replace('/\{\{#if product\.heroImageUrl\}\}(.*?)\{\{\/if\}\}/s', '$1', $contentPages);
        $contentPages = str_replace('{{ product.heroImageUrl }}', $heroUrl, $contentPages);
    } else {
        $contentPages = preg_replace('/\{\{#if product\.heroImageUrl\}\}(.*?)\{\{else\}\}(.*?)\{\{\/if\}\}/s', '$2', $contentPages);
        $contentPages = preg_replace('/\{\{#if product\.heroImageUrl\}\}(.*?)\{\{\/if\}\}/s', '', $contentPages);
    }

    // Heatsink image conditional
    if ($heatSinkUrl) {
        $contentPages = preg_replace('/\{\{#if product\.heatSinkImageUrl\}\}(.*?)\{\{\/if\}\}/s', '$1', $contentPages);
        $contentPages = str_replace('{{ product.heatSinkImageUrl }}', $heatSinkUrl, $contentPages);
    } else {
        $contentPages = preg_replace('/\{\{#if product\.heatSinkImageUrl\}\}(.*?)\{\{\/if\}\}/s', '', $contentPages);
    }

    // Chip image conditional
    if ($chipUrl) {
        $contentPages = preg_replace('/\{\{#if product\.chipImageUrl\}\}(.*?)\{\{\/if\}\}/s', '$1', $contentPages);
        $contentPages = str_replace('{{ product.chipImageUrl }}', $chipUrl, $contentPages);
    } else {
        $contentPages = preg_replace('/\{\{#if product\.chipImageUrl\}\}(.*?)\{\{\/if\}\}/s', '', $contentPages);
    }

    // Driver image conditional
    if ($driverUrl) {
        $contentPages = preg_replace('/\{\{#if product\.driverImageUrl\}\}(.*?)\{\{\/if\}\}/s', '$1', $contentPages);
        $contentPages = str_replace('{{ product.driverImageUrl }}', $driverUrl, $contentPages);
    } else {
        $contentPages = preg_replace('/\{\{#if product\.driverImageUrl\}\}(.*?)\{\{\/if\}\}/s', '', $contentPages);
    }

    // Driver body image conditional
    if ($driverBodyUrl) {
        $contentPages = preg_replace('/\{\{#if product\.driverBodyImageUrl\}\}(.*?)\{\{else\}\}(.*?)\{\{\/if\}\}/s', '$1', $contentPages);
        $contentPages = str_replace('{{ product.driverBodyImageUrl }}', $driverBodyUrl, $contentPages);
    } else {
        $contentPages = preg_replace('/\{\{#if product\.driverBodyImageUrl\}\}(.*?)\{\{else\}\}(.*?)\{\{\/if\}\}/s', '$2', $contentPages);
    }

    // Clean value formatting
    $formatVal = function($val) {
        if ($val === null || $val === '') return 'N/A';
        return trim((string)$val);
    };

    $raw = $variant['raw'] ?? [];
    $x = $variant['x'] ?? [];

    // General Specs (Industrial Table 1)
    $fluxVal = $x['total_luminous_lm'] ?? '';
    if ($fluxVal && stripos($fluxVal, 'lm') !== false) {
        $fluxVal = trim(preg_replace('/lm/i', '', $fluxVal));
    }

    $generalSpecs = [
        ['k' => 'LED Chip', 'v' => $formatVal($variant['chip'] ?? 'Philips 3030'), 'unit' => ''],
        ['k' => 'Driver', 'v' => $formatVal($variant['driver'] ?? 'Short Circuit'), 'unit' => ''],
        ['k' => 'LED efficiency', 'v' => $formatVal($variant['efficacy'] ?? '145'), 'unit' => 'lm/W'],
        ['k' => 'Wattage', 'v' => $formatVal($variant['power'] ?? 'N/A'), 'unit' => 'W'],
        ['k' => 'Total Luminous', 'v' => $formatVal($fluxVal ?: 'N/A'), 'unit' => 'lm'],
        ['k' => 'Beam angle', 'v' => $formatVal($x['beam_angle_deg'] ?? '120'), 'unit' => '°'],
        ['k' => 'Color temperature', 'v' => $formatVal($x['cct_k'] ?? '6500'), 'unit' => 'K'],
        ['k' => 'CRI', 'v' => isset($variant['cri']) ? '>' . $variant['cri'] : '>80', 'unit' => ''],
        ['k' => 'Power factor', 'v' => $formatVal($variant['pf'] ?? '>0.95'), 'unit' => ''],
        ['k' => 'Input voltage', 'v' => $formatVal($x['input_voltage'] ?? 'AC 90-275'), 'unit' => 'V'],
        ['k' => 'Frequency', 'v' => $formatVal($x['frequency_hz'] ?? '50/60'), 'unit' => 'Hz'],
        ['k' => 'Operating Temperature', 'v' => $formatVal($x['operating_temperature'] ?? '-40 ~ +50'), 'unit' => '°C'],
        ['k' => 'Humidity', 'v' => $formatVal($x['humidity'] ?? 'Up to 90%'), 'unit' => ''],
        ['k' => 'IP Rating', 'v' => $formatVal($x['ip_rating'] ?? '66'), 'unit' => ''],
        ['k' => 'Driver Protection', 'v' => 'OVP, OCP, OTP', 'unit' => ''],
        ['k' => 'Life time', 'v' => $formatVal($x['lifetime_hours'] ?? '50,000'), 'unit' => 'Hours'],
        ['k' => 'Warranty', 'v' => $formatVal($x['warranty_years'] ?? '5'), 'unit' => 'Years'],
    ];

    if (preg_match('/\{\{#each generalSpecs\}\}(.*?)\{\{\/each\}\}/s', $contentPages, $m)) {
        $rowTemplate = $m[1];
        $rowsHtml = '';
        foreach ($generalSpecs as $spec) {
            $r = $rowTemplate;
            $r = str_replace('{{ k }}', htmlspecialchars($spec['k']), $r);
            $r = str_replace('{{ v }}', htmlspecialchars($spec['v']), $r);
            if (!empty($spec['unit'])) {
                $r = preg_replace('/\{\{#if unit\}\}(.*?)\{\{\/if\}\}/s', '$1', $r);
                $r = str_replace('{{ unit }}', htmlspecialchars($spec['unit']), $r);
            } else {
                $r = preg_replace('/\{\{#if unit\}\}(.*?)\{\{\/if\}\}/s', '', $r);
            }
            $rowsHtml .= $r;
        }
        $contentPages = str_replace($m[0], $rowsHtml, $contentPages);
    }

    // Body Specs (Industrial Table 2)
    $bodySpecs = [
        ['k' => 'Body', 'v' => $raw['body'] ?? 'Anti-corrosion aluminum alloy heat sink (electrostatic painted)', 'v2' => null],
        ['k' => 'Aluminum Purity', 'v' => $raw['aluminum purity'] ?? '96-98 % (ADC12 / 6063)', 'v2' => null],
        ['k' => 'Number Of Fins', 'v' => $raw['number of fins'] ?? 'Integrated high-efficiency heat sink fins', 'v2' => null],
        ['k' => 'Thickness Of Fin', 'v' => $raw['thickness of fin'] ?? '2.5mm', 'v2' => '1.5mm'],
        ['k' => 'Lenses', 'v' => $raw['lenses'] ?? 'UV Poly-carbonate heat resistance optical lenses', 'v2' => null],
        ['k' => 'Beam Angle', 'v' => ($x['beam_angle_deg'] ?? '120') . '°', 'v2' => null],
        ['k' => 'Gasket', 'v' => $raw['gasket'] ?? 'Silicone rubber weather-seal insulation', 'v2' => null],
        ['k' => 'Glands', 'v' => $raw['glands'] ?? 'Waterproof cable glands (silicone insulated IP68)', 'v2' => null],
        ['k' => 'IP Rating', 'v' => 'IP' . ($x['ip_rating'] ?? '66'), 'v2' => null],
        ['k' => 'Weight', 'v' => (!empty($x['weight_kg']) ? $x['weight_kg'] . ' kg' : '12 kg'), 'v2' => null],
        ['k' => 'Wattage', 'v' => (!empty($variant['power']) ? $variant['power'] . ' W' : 'N/A'), 'v2' => null],
    ];

    if (preg_match('/\{\{#each bodySpecs\}\}(.*?)\{\{\/each\}\}/s', $contentPages, $m)) {
        $rowTemplate = $m[1];
        $rowsHtml = '';
        foreach ($bodySpecs as $spec) {
            $r = $rowTemplate;
            $r = str_replace('{{ k }}', htmlspecialchars($spec['k']), $r);
            $r = str_replace('{{ v }}', htmlspecialchars($spec['v']), $r);
            if (!empty($spec['v2'])) {
                $r = preg_replace('/\{\{#if v2\}\}(.*?)\{\{else\}\}(.*?)\{\{\/if\}\}/s', '$1', $r);
                $r = str_replace('{{ v2 }}', htmlspecialchars($spec['v2']), $r);
            } else {
                $r = preg_replace('/\{\{#if v2\}\}(.*?)\{\{else\}\}(.*?)\{\{\/if\}\}/s', '$2', $r);
            }
            $rowsHtml .= $r;
        }
        $contentPages = str_replace($m[0], $rowsHtml, $contentPages);
    }

    // Battery & Emergency Kit replacements
    if ($deviceType === 'battery_emergency') {
        $batterySpecs = [
            ['k' => 'Rated Power Supply', 'v' => $raw['rated power supply'] ?? '220–240 VAC, 50/60 Hz', 'note' => 'AC Mains Normal'],
            ['k' => 'Central Battery System (CBS)', 'v' => $raw['central battery system (cbs) version available'] ?? 'DC 176–254 V input', 'note' => 'CBS Compatible'],
            ['k' => 'Power Consumption', 'v' => $raw['power consumption'] ?? 'Max. 50 W', 'note' => 'Full Load'],
            ['k' => 'Recharge Time', 'v' => $raw['charge time'] ?? '24 Hours', 'note' => 'Standard Float'],
            ['k' => 'Discharge Duration', 'v' => $raw['discharge duration'] ?? '1 / 2 / 3 Hours selectable', 'note' => 'Emergency Mode'],
            ['k' => 'Changeover Voltage', 'v' => $raw['changeover voltage'] ?? '144–187 V AC', 'note' => 'Automatic Transfer'],
            ['k' => 'Test Facility Viewing', 'v' => $raw['test facility viewing'] ?? 'Manual test and AUTO test', 'note' => 'Dual Diagnostic'],
            ['k' => 'Inverter Efficiency', 'v' => '≥ 90%', 'note' => 'High Efficiency'],
            ['k' => 'Battery Protection', 'v' => 'Over-charge, Deep-discharge, Short-circuit protection', 'note' => 'Protection IC'],
            ['k' => 'Operating Temperature', 'v' => '-25°C ~ +45°C (Ta)', 'note' => 'Operating Range'],
        ];

        if (preg_match('/\{\{#each batterySpecs\}\}(.*?)\{\{\/each\}\}/s', $contentPages, $m)) {
            $rowTemplate = $m[1];
            $rowsHtml = '';
            foreach ($batterySpecs as $spec) {
                $r = $rowTemplate;
                $r = str_replace('{{ k }}', htmlspecialchars($spec['k']), $r);
                $r = str_replace('{{ v }}', htmlspecialchars($spec['v']), $r);
                $r = str_replace('{{ note }}', htmlspecialchars($spec['note']), $r);
                $rowsHtml .= $r;
            }
            $contentPages = str_replace($m[0], $rowsHtml, $contentPages);
        }

        $rawFeatures = $variant['features'] ?? [
            'Compact and decorative slimline enclosure design.',
            'Connects to LED luminaires with manual test and auto-test optional.',
            'High-capacity Lithium / Ni-Cd battery pack optional.',
            'Suitable for LED lamps up to 50W (external driver or internal driver).',
            'Emergency output power: 100% full brightness emergency illumination.',
            'High luminance emergency output model optional.',
            'Low operational standby cost via high-efficiency low power consumption.',
            'Flame-retardant housing materials compliant with high operating temperatures.',
            'Full CE, RoHS, EMC, and LVD compliance certification.'
        ];
        $cleanFeatures = [];
        $featureMap = [
            'Compactanddecorative design.' => 'Compact and decorative slimline enclosure design.',
            'ConnectLEDluminairewith manual testandAUTOtest optional.' => 'Connects to LED luminaires with manual test and auto-test optional.',
            'Lithium battery optional.' => 'High-capacity Lithium / Ni-Cd battery pack optional.',
            'Suitableforlessthan50Wexternaldriverlampandinternaldriverlamp' => 'Suitable for LED lamps up to 50W (external driver or internal driver).',
            'Outputpower :100%bright' => 'Emergency output power: 100% full brightness emergency illumination.',
            'Highluminancemodel optional.' => 'High luminance emergency output model optional.',
            'Lowoperationcost vialowpower consumption.' => 'Low operational standby cost via high-efficiency low power consumption.',
            'Materialcompliantathightemperature' => 'Flame-retardant housing materials compliant with high operating temperatures.',
            'CEROHScompliance.' => 'Full CE, RoHS, EMC, and LVD compliance certification.'
        ];
        foreach ($rawFeatures as $f) {
            $tf = trim($f);
            $cleanFeatures[] = $featureMap[$tf] ?? cleanOcrText($tf);
        }

        if (preg_match('/\{\{#each batteryFeatures\}\}(.*?)\{\{\/each\}\}/s', $contentPages, $m)) {
            $rowTemplate = $m[1];
            $rowsHtml = '';
            foreach ($cleanFeatures as $feat) {
                $r = $rowTemplate;
                $r = str_replace('{{ this }}', htmlspecialchars($feat), $r);
                $rowsHtml .= $r;
            }
            $contentPages = str_replace($m[0], $rowsHtml, $contentPages);
        }

        // Battery wiring diagram image
        $battWiringImg = BASE_DIR . '/app/images/Battery datasheet/Battery_datasheet_p2_img1.png';
        if (file_exists($battWiringImg)) {
            $battWiringUri = imageToDataUri($battWiringImg);
            $contentPages = preg_replace('/\{\{#if batteryWiringImageUrl\}\}(.*?)\{\{\/if\}\}/s', '$1', $contentPages);
            $contentPages = str_replace('{{ batteryWiringImageUrl }}', $battWiringUri, $contentPages);
        } else {
            $contentPages = preg_replace('/\{\{#if batteryWiringImageUrl\}\}(.*?)\{\{\/if\}\}/s', '', $contentPages);
        }
    }

    // Commercial & Landscape Lighting replacements
    if ($deviceType === 'commercial_landscape') {
        $rawApps = $variant['apps'] ?? [];
        $cleanApps = [];
        foreach ($rawApps as $a) {
            $ca = cleanOcrText($a);
            if (strlen($ca) > 2 && strlen($ca) < 45 && !preg_match('/(AARREEAASS|IOnftfeicreio|OOFF)/i', $ca)) {
                $cleanApps[] = $ca;
            }
        }
        if (empty($cleanApps)) {
            $nl = strtolower($name);
            if (strpos($nl, 'bollard') !== false) {
                $cleanApps = ['Garden Pathways', 'Pedestrian Walkways', 'Public Parks', 'Hotel Landscapes', 'Residential Entrances'];
            } elseif (strpos($nl, 'spotlight') !== false || strpos($nl, 'spike') !== false) {
                $cleanApps = ['Architectural Accents', 'Tree & Shrub Uplighting', 'Statues & Sculptures', 'Building Facades', 'Outdoor Signs'];
            } elseif (strpos($nl, 'wall') !== false || strpos($nl, 'applique') !== false) {
                $cleanApps = ['Building Perimeters', 'Corridors & Balconies', 'Perimeter Walls', 'Villa Entrances', 'Terraces'];
            } elseif (strpos($nl, 'backlight') !== false || strpos($nl, 'panel') !== false) {
                $cleanApps = ['Offices & Workspaces', 'Commercial Centers', 'Educational Facilities', 'Hospitals & Clinics', 'Retail Stores'];
            } else {
                $cleanApps = ['Commercial Landscaping', 'Architectural Facades', 'Walkways & Promenades', 'Parks & Plazas', 'Hospitality'];
            }
        }

        if (preg_match('/\{\{#each landscapeApps\}\}(.*?)\{\{\/each\}\}/s', $contentPages, $m)) {
            $rowTemplate = $m[1];
            $rowsHtml = '';
            foreach ($cleanApps as $app) {
                $r = $rowTemplate;
                $r = str_replace('{{ this }}', htmlspecialchars($app), $r);
                $rowsHtml .= $r;
            }
            $contentPages = str_replace($m[0], $rowsHtml, $contentPages);
        }

        $rawBenefits = $variant['benefits'] ?? [];
        $cleanBenefits = [];
        foreach ($rawBenefits as $b) {
            $cb = cleanOcrText($b);
            if (strlen($cb) > 5 && !preg_match('/(AARREEAASS|BENEFITS|FEATURES)/i', $cb)) {
                $cleanBenefits[] = $cb;
            }
        }
        if (empty($cleanBenefits)) {
            $cleanBenefits = [
                'Energy savings of up to 65% compared to conventional luminaires.',
                'Easy and fast installation with robust electrical connections.',
                'Durable corrosion-resistant housing engineered for harsh outdoor environments.',
                'Precision optical lenses delivering uniform light distribution with minimal glare.'
            ];
        }

        if (preg_match('/\{\{#each landscapeBenefits\}\}(.*?)\{\{\/each\}\}/s', $contentPages, $m)) {
            $rowTemplate = $m[1];
            $rowsHtml = '';
            foreach ($cleanBenefits as $ben) {
                $r = $rowTemplate;
                $r = str_replace('{{ this }}', htmlspecialchars($ben), $r);
                $rowsHtml .= $r;
            }
            $contentPages = str_replace($m[0], $rowsHtml, $contentPages);
        }

        // Clean values for landscape tables
        $effVal = $variant['efficacy'] ?? ($raw['led efficacy'] ?? '110 lm/W');
        if (is_numeric($effVal)) $effVal .= ' lm/W';
        $wattVal = $variant['power'] ? $variant['power'] . ' W' : ($raw['power'] ?? ($raw['nominal wattage'] ?? 'N/A'));
        $voltVal = $x['input_voltage'] ?? ($raw['input voltage'] ?? ($raw['nominal voltage'] ?? '220–240 V AC'));
        $freqVal = $x['frequency_hz'] ?? ($raw['frequency'] ?? ($raw['main frequency'] ?? '50/60 Hz'));
        $chipVal = $variant['chip'] ?? 'Philips SMD 3030';
        $fluxVal = $x['total_luminous_lm'] ?? ($raw['total luminous flux'] ?? ($raw['total luminous'] ?? ''));
        if (!$fluxVal && $variant['power'] && $variant['efficacy']) {
            $fluxVal = number_format($variant['power'] * $variant['efficacy']) . ' lm';
        } elseif (!$fluxVal) {
            $fluxVal = 'N/A';
        }
        $cctVal = $x['cct_k'] ?? ($raw['color temperature'] ?? 'Warm 3000K / 6500K');
        $criVal = isset($variant['cri']) ? '>' . $variant['cri'] : '> 80';
        $beamVal = ($x['beam_angle_deg'] ?? ($raw['beam angle'] ?? '120')) . '°';

        $contentPages = str_replace('{{ electricalSpecs.driver }}', htmlspecialchars($variant['driver'] ?? 'Short Circuit Constant Current Driver'), $contentPages);
        $contentPages = str_replace('{{ electricalSpecs.wattage }}', htmlspecialchars($wattVal), $contentPages);
        $contentPages = str_replace('{{ electricalSpecs.voltage }}', htmlspecialchars($voltVal), $contentPages);
        $contentPages = str_replace('{{ electricalSpecs.frequency }}', htmlspecialchars($freqVal), $contentPages);
        $contentPages = str_replace('{{ electricalSpecs.pf }}', htmlspecialchars($variant['pf'] ?? '> 0.90'), $contentPages);

        $contentPages = str_replace('{{ photometricSpecs.chip }}', htmlspecialchars($chipVal), $contentPages);
        $contentPages = str_replace('{{ photometricSpecs.efficacy }}', htmlspecialchars($effVal), $contentPages);
        $contentPages = str_replace('{{ photometricSpecs.luminousFlux }}', htmlspecialchars($fluxVal), $contentPages);
        $contentPages = str_replace('{{ photometricSpecs.cct }}', htmlspecialchars($cctVal), $contentPages);
        $contentPages = str_replace('{{ photometricSpecs.cri }}', htmlspecialchars($criVal), $contentPages);
        $contentPages = str_replace('{{ photometricSpecs.beamAngle }}', htmlspecialchars($beamVal), $contentPages);

        $bodyMat = $x['body_material'] ?? ($raw['housing material'] ?? ($raw['body material'] ?? 'Die-cast Aluminum alloy / ABS'));
        $bodyCol = $raw['color'] ?? ($raw['body color'] ?? 'Dark Grey / Black');
        $diffMat = $raw['diffuser'] ?? ($raw['diffuser material'] ?? 'UV-stabilized Polycarbonate');
        $contentPages = str_replace('{{ mechanicalSpecs.bodyMaterial }}', htmlspecialchars($bodyMat), $contentPages);
        $contentPages = str_replace('{{ mechanicalSpecs.bodyColor }}', htmlspecialchars($bodyCol), $contentPages);
        $contentPages = str_replace('{{ mechanicalSpecs.diffuserMaterial }}', htmlspecialchars($diffMat), $contentPages);

        $lifetime = $x['lifetime_hours'] ?? ($raw['life time'] ?? ($raw['lifespan'] ?? '30,000–50,000 hours'));
        $warranty = $x['warranty_years'] ?? ($raw['warranty'] ?? '2–3 Years');
        if (is_numeric($warranty)) $warranty .= ' Years';
        $contentPages = str_replace('{{ lifespanSpecs.lifetime }}', htmlspecialchars($lifetime), $contentPages);
        $contentPages = str_replace('{{ lifespanSpecs.warranty }}', htmlspecialchars($warranty), $contentPages);

        $ipRaw = $x['ip_rating'] ?? preg_replace('/[^0-9]/', '', $raw['ingress protection ip'] ?? ($raw['ingess protection ip'] ?? '65')) ?: '65';
        $contentPages = str_replace('{{ protectionSpecs.ipRating }}', 'IP' . htmlspecialchars($ipRaw), $contentPages);
        $contentPages = str_replace('{{ protectionSpecs.ipRatingRaw }}', htmlspecialchars($ipRaw), $contentPages);
        $contentPages = str_replace('{{ protectionSpecs.ikRating }}', 'IK08 Impact Resistant', $contentPages);

        $mType = $raw['mounting type'] ?? 'Surface / Ground Spike / Recessed';
        $mLoc = $raw['mounting location'] ?? 'Outdoor Garden / Facade / Wall';
        $mAdj = (stripos($name, 'adjustable') !== false || stripos($name, 'spot') !== false) ? '0° ~ 90° Tilt Adjustable' : 'Fixed';
        $contentPages = str_replace('{{ mountingSpecs.mountingType }}', htmlspecialchars($mType), $contentPages);
        $contentPages = str_replace('{{ mountingSpecs.mountingLocation }}', htmlspecialchars($mLoc), $contentPages);
        $contentPages = str_replace('{{ mountingSpecs.adjustability }}', htmlspecialchars($mAdj), $contentPages);
    }

    // Solar Lighting replacements
    if ($deviceType === 'solar_fixture') {
        $rawApps = $variant['apps'] ?? [];
        $cleanApps = [];
        foreach ($rawApps as $a) {
            $ca = cleanOcrText($a);
            if (strlen($ca) > 2) $cleanApps[] = $ca;
        }
        if (empty($cleanApps)) {
            $cleanApps = ['Compounds', 'Highways', 'Streets', 'Rural Roadways', 'Perimeter & Parking'];
        }
        if (preg_match('/\{\{#each solarApps\}\}(.*?)\{\{\/each\}\}/s', $contentPages, $m)) {
            $rowTemplate = $m[1];
            $rowsHtml = '';
            foreach ($cleanApps as $app) {
                $r = $rowTemplate;
                $r = str_replace('{{ this }}', htmlspecialchars($app), $r);
                $rowsHtml .= $r;
            }
            $contentPages = str_replace($m[0], $rowsHtml, $contentPages);
        }

        $rawBenefits = $variant['benefits'] ?? [];
        $cleanBenefits = [];
        foreach ($rawBenefits as $b) {
            $cb = cleanOcrText($b);
            if (strlen($cb) > 5) $cleanBenefits[] = $cb;
        }
        if (empty($cleanBenefits)) {
            $cleanBenefits = [
                '100% solar powered — zero grid electrical dependency and zero electricity costs.',
                'Easy wireless installation with no trenching, cabling, or switchgear required.',
                'High efficacy LED optical system delivering maximum lux per watt under battery operation.',
                'Intelligent dusk-to-dawn automated controller with adaptive power management.'
            ];
        }
        if (preg_match('/\{\{#each solarBenefits\}\}(.*?)\{\{\/each\}\}/s', $contentPages, $m)) {
            $rowTemplate = $m[1];
            $rowsHtml = '';
            foreach ($cleanBenefits as $ben) {
                $r = $rowTemplate;
                $r = str_replace('{{ this }}', htmlspecialchars($ben), $r);
                $rowsHtml .= $r;
            }
            $contentPages = str_replace($m[0], $rowsHtml, $contentPages);
        }

        $pwr = $variant['power'] ? $variant['power'] . ' W' : ($raw['nominal wattage'] ?? '60 W');
        $eff = $variant['efficacy'] ? $variant['efficacy'] . ' lm/W' : ($raw['led efficacy'] ?? '160 lm/W');
        $flux = $x['total_luminous_lm'] ?? ($raw['total luminous'] ?? '9,600 lm');
        $cct = $x['cct_k'] ?? ($raw['color temperature'] ?? '6000K');
        $cri = isset($variant['cri']) ? '>' . $variant['cri'] : '> 80';

        $contentPages = str_replace('{{ solarSpecs.power }}', htmlspecialchars($pwr), $contentPages);
        $contentPages = str_replace('{{ solarSpecs.systemVoltage }}', '12V / 24V DC Isolated System', $contentPages);
        $contentPages = str_replace('{{ solarSpecs.efficacy }}', htmlspecialchars($eff), $contentPages);
        $contentPages = str_replace('{{ solarSpecs.luminousFlux }}', htmlspecialchars($flux), $contentPages);
        $contentPages = str_replace('{{ solarSpecs.cct }}', htmlspecialchars($cct), $contentPages);
        $contentPages = str_replace('{{ solarSpecs.cri }}', htmlspecialchars($cri), $contentPages);
        $contentPages = str_replace('{{ solarSpecs.beamAngle }}', '120° x 60° Batwing Roadway', $contentPages);

        $pvType = $raw['solar panel'] ?? 'High-efficiency Monocrystalline Silicon';
        $pvPower = $raw['solar panel power'] ?? '10V - 28W';
        $contentPages = str_replace('{{ solarPvSpecs.panelType }}', htmlspecialchars($pvType), $contentPages);
        $contentPages = str_replace('{{ solarPvSpecs.panelPower }}', htmlspecialchars($pvPower), $contentPages);

        $bType = $raw['battery type'] ?? 'Lithium Iron Phosphate (LiFePO4)';
        $bPower = $raw['battery power'] ?? '6.4V - 24AH';
        $chgTime = $raw['charging time'] ?? '6–8 Hours (Direct Sunlight)';
        $disTime = $raw['discharging time'] ?? '2–3 Rainy Days Autonomy (12h/night)';
        $contentPages = str_replace('{{ solarBatterySpecs.batteryType }}', htmlspecialchars($bType), $contentPages);
        $contentPages = str_replace('{{ solarBatterySpecs.batteryPower }}', htmlspecialchars($bPower), $contentPages);
        $contentPages = str_replace('{{ solarBatterySpecs.chargingTime }}', htmlspecialchars($chgTime), $contentPages);
        $contentPages = str_replace('{{ solarBatterySpecs.dischargingTime }}', htmlspecialchars($disTime), $contentPages);

        $bodyMat = $raw['body material'] ?? 'Die-cast Aluminum alloy (ADC12)';
        $contentPages = str_replace('{{ solarMechSpecs.bodyMaterial }}', htmlspecialchars($bodyMat), $contentPages);

        $lifespan = $x['lifetime_hours'] ?? ($raw['lifespan'] ?? '50,000 Hours');
        $warranty = $x['warranty_years'] ?? ($raw['warranty'] ?? '2–3 Years');
        $contentPages = str_replace('{{ solarLifespanSpecs.lifetime }}', htmlspecialchars($lifespan), $contentPages);
        $contentPages = str_replace('{{ solarLifespanSpecs.warranty }}', htmlspecialchars($warranty), $contentPages);
    }

    // Footers across all assembled pages
    $footer = [
        'address' => 'El Giza, Haram, 188 tawoon st.',
        'phone' => '+20 1040359990',
        'email' => 'scc@shortcircuit.company',
        'website' => 'shortcircuit.company',
    ];
    $contentPages = str_replace('{{ footer.address }}', $footer['address'], $contentPages);
    $contentPages = str_replace('{{ footer.phone }}', $footer['phone'], $contentPages);
    $contentPages = str_replace('{{ footer.email }}', $footer['email'], $contentPages);
    $contentPages = str_replace('{{ footer.website }}', $footer['website'], $contentPages);

    $baseHtml = str_replace('{{ product.name }}', htmlspecialchars($name), $baseHtml);
    $baseHtml = str_replace('{{ product.modelNumber }}', htmlspecialchars($modelNumber), $baseHtml);
    $baseHtml = str_replace('{{ footer.address }}', $footer['address'], $baseHtml);
    $baseHtml = str_replace('{{ footer.phone }}', $footer['phone'], $baseHtml);
    $baseHtml = str_replace('{{ footer.email }}', $footer['email'], $baseHtml);
    $baseHtml = str_replace('{{ footer.website }}', $footer['website'], $baseHtml);

    // Update beam angle attribute for polar curve
    if (!empty($x['beam_angle_deg'])) {
        $contentPages = str_replace('data-beam-angle="120"', 'data-beam-angle="' . htmlspecialchars($x['beam_angle_deg']) . '"', $contentPages);
    }

    // Insert content pages into base shell
    $finalHtml = str_replace('{{{ content_pages }}}', $contentPages, $baseHtml);

    // Clean up any remaining legacy image fallback references
    $finalHtml = str_replace('src="others/chip.jpg"', 'src="data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\' viewBox=\'0 0 80 80\'><rect width=\'80\' height=\'80\' rx=\'6\' fill=\'%23f3f4f6\' stroke=\'%23d1d5db\'/><circle cx=\'40\' cy=\'40\' r=\'24\' fill=\'%23e5e7eb\'/><circle cx=\'40\' cy=\'40\' r=\'16\' fill=\'%23fef08a\' stroke=\'%23eab308\'/><text x=\'40\' y=\'72\' font-family=\'sans-serif\' font-size=\'8\' font-weight=\'bold\' text-anchor=\'middle\' fill=\'%236b7280\'>3030 LED</text></svg>"', $finalHtml);
    $finalHtml = str_replace('src="others/driver.png"', 'src="data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'240\' height=\'140\' viewBox=\'0 0 240 140\'><rect x=\'20\' y=\'20\' width=\'200\' height=\'100\' rx=\'8\' fill=\'%231f2937\'/><rect x=\'30\' y=\'30\' width=\'180\' height=\'80\' rx=\'4\' fill=\'%23374151\'/><text x=\'120\' y=\'65\' font-family=\'sans-serif\' font-size=\'14\' font-weight=\'bold\' fill=\'%23ef4444\' text-anchor=\'middle\'>SHORT CIRCUIT</text><text x=\'120\' y=\'85\' font-family=\'sans-serif\' font-size=\'10\' fill=\'%239ca3af\' text-anchor=\'middle\'>CONSTANT CURRENT LED DRIVER</text><circle cx=\'10\' cy=\'70\' r=\'6\' fill=\'%239ca3af\'/><circle cx=\'230\' cy=\'70\' r=\'6\' fill=\'%239ca3af\'/></svg>"', $finalHtml);

    return $finalHtml;
}
