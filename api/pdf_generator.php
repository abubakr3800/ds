<?php
/**
 * SC Datasheet Generator — PDF Generator
 *
 * Generates PDF datasheets matching templates/datasheet-template-v2.html.
 * Primary engine: Headless Chrome / Microsoft Edge for exact A4 template rendering.
 * Fallback engine: TCPDF library.
 */

require_once __DIR__ . '/template_renderer.php';

/**
 * Generate PDF for a fixture variant
 *
 * @param array $fixture Fixture data
 * @param array $variant Variant data
 * @return string PDF binary content
 */
function generatePDF($fixture, $variant) {
    // 1. Try modern headless browser generation for exact v2 template
    try {
        $pdf = generateBrowserPDF($fixture, $variant);
        header('X-PDF-Engine: headless-browser');
        return $pdf;
    } catch (\Throwable $e) {
        error_log("Headless browser PDF generation failed, falling back to TCPDF: " . $e->getMessage());
    }

    header('X-PDF-Engine: tcpdf-fallback');

    // 2. Try TCPDF
    if (!class_exists('TCPDF') && file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
        require_once(dirname(__DIR__) . '/vendor/autoload.php');
    }

    if (class_exists('TCPDF')) {
        return generateTCPDF($fixture, $variant);
    }

    // 3. Fallback to SimplePDF
    return generateSimplePDF($fixture, $variant);
}

/**
 * Locate Chrome or Edge headless executable
 */
function findHeadlessBrowser() {
    $paths = [
        'C:\Program Files\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
        'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
        '/usr/bin/google-chrome',
        '/usr/bin/google-chrome-stable',
        '/usr/bin/chromium-browser',
        '/usr/bin/chromium',
        '/snap/bin/chromium',
        '/usr/local/bin/chrome',
        '/usr/local/bin/chromium',
    ];
    foreach ($paths as $p) {
        if (@file_exists($p)) return $p;
    }

    // Check PATH on Unix/Linux systems if exec is available
    if (DIRECTORY_SEPARATOR === '/' && function_exists('exec')) {
        foreach (['google-chrome', 'google-chrome-stable', 'chromium-browser', 'chromium'] as $bin) {
            $which = trim(@exec("which $bin 2>/dev/null"));
            if (!empty($which) && @file_exists($which)) {
                return $which;
            }
        }
    }

    return null;
}

/**
 * Generate PDF via headless Chrome/Edge using datasheet-template-v2.html
 */
function generateBrowserPDF($fixture, $variant) {
    $browser = findHeadlessBrowser();
    if (!$browser) {
        throw new Exception("Headless browser not found on host");
    }

    $html = renderDatasheetTemplate($fixture, $variant, true);
    $tempId = uniqid('sc_ds_', true);
    $tempHtml = sys_get_temp_dir() . '/' . $tempId . '.html';
    $tempPdf = sys_get_temp_dir() . '/' . $tempId . '.pdf';

    if (file_put_contents($tempHtml, $html) === false) {
        throw new Exception("Could not write temporary HTML file for PDF generation");
    }

    $unixPath = str_replace('\\', '/', $tempHtml);
    $fileUrl = 'file:///' . ltrim($unixPath, '/');
    // Linux hosts: web user often has no writable HOME and cannot use the sandbox
    $extra = '';
    if (DIRECTORY_SEPARATOR === '/') {
        $profile = sys_get_temp_dir() . '/' . $tempId . '_profile';
        $extra = ' --user-data-dir="' . $profile . '"';
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $extra .= ' --no-sandbox';
        }
    }
    $cmd = sprintf(
        '"%s" --headless=new --disable-gpu --no-pdf-header-footer --disable-extensions --disable-background-networking --disable-sync --no-first-run --virtual-time-budget=5000%s --print-to-pdf="%s" "%s" 2>&1',
        $browser,
        $extra,
        $tempPdf,
        $fileUrl
    );

    exec($cmd, $output, $returnCode);

    if (file_exists($tempPdf) && filesize($tempPdf) > 1000) {
        $pdfData = file_get_contents($tempPdf);
        @unlink($tempHtml);
        @unlink($tempPdf);
        return $pdfData;
    }

    @unlink($tempHtml);
    if (file_exists($tempPdf)) @unlink($tempPdf);

    throw new Exception("Headless browser execution exited with code $returnCode and no output file");
}

/**
 * Generate filename for PDF download
 *
 * @param array $fixture
 * @param array $variant
 * @return string
 */
function generatePDFFilename($fixture, $variant) {
    $name = $fixture['name'] ?? 'datasheet';
    $power = $variant['power'] ?? '';

    // Sanitize filename - remove special characters
    $filename = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $name);
    $filename = preg_replace('/\s+/', '_', trim($filename));

    if ($power && stripos($filename, (string)$power) === false) {
        $filename .= '_' . $power . 'W';
    }
    $filename .= '_Datasheet.pdf';

    return $filename;
}

/**
 * Simple PDF generation fallback (without TCPDF or headless browser)
 */
function generateSimplePDF($fixture, $variant) {
    require_once(__DIR__ . '/simple_pdf.php');

    $pdf = new SimplePDF();
    $pdf->addPage();
    $pdf->setFont('Helvetica', '', 12);

    // Title
    $name = $fixture['name'] ?? 'Unknown';
    $power = $variant['power'] ?? '';
    $title = strtoupper($name . ($power ? " {$power}W" : ''));
    $pdf->cell(0, 20, $title);
    $pdf->ln(10);

    // Manufacturer
    $mfr = 'Manufacturer: ' . ($fixture['mfr'] ?? 'Short Circuit Company');
    $pdf->cell(0, 15, $mfr);
    $pdf->ln(15);

    // Overview
    if (!empty($variant['abstract'])) {
        $pdf->cell(0, 15, 'OVERVIEW');
        $pdf->ln(10);
        $abstract = substr($variant['abstract'], 0, 500);
        $pdf->multiCell(0, 10, $abstract);
        $pdf->ln(15);
    }

    // Specifications
    $pdf->cell(0, 15, 'GENERAL SPECIFICATIONS');
    $pdf->ln(10);

    $specs = [
        'Power' => isset($variant['power']) ? $variant['power'] . ' W' : 'N/A',
        'Efficacy' => isset($variant['efficacy']) ? $variant['efficacy'] . ' lm/W' : 'N/A',
        'CRI' => isset($variant['cri']) ? '>' . $variant['cri'] : 'N/A',
    ];

    foreach ($specs as $label => $value) {
        $pdf->cell(0, 12, "$label: $value");
    }

    $pdf->ln(20);
    $pdf->cell(0, 10, 'For full specifications, visit shortcircuit.company');

    return $pdf->output();
}

/**
 * Generate professional PDF using TCPDF (when available)
 */
function generateTCPDF($fixture, $variant) {
    try {
        $autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';
        if (file_exists($autoloadPath) && !class_exists('TCPDF')) {
            require_once($autoloadPath);
        }

        if (!class_exists('TCPDF')) {
            throw new Exception("TCPDF class not available");
        }

        // Create new PDF document
        $pdf = new TCPDF('P', 'pt', 'LETTER', true, 'UTF-8', false);

        // Set document information
        $pdf->SetCreator('SC Datasheet Generator');
        $pdf->SetAuthor('Short Circuit Company');
        $pdf->SetTitle($fixture['name'] ?? 'Datasheet');

        // Remove default header/footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);

        // Set margins
        $pdf->SetMargins(28, 28, 28);
        $pdf->SetAutoPageBreak(true, 28);

        // Add a page
        $pdf->AddPage();

        // Build PDF content
        buildPDFContent($pdf, $fixture, $variant);

        return $pdf->Output('', 'S');
    } catch (\Throwable $e) {
        error_log("TCPDF generation failed: " . $e->getMessage());
        return generateSimplePDF($fixture, $variant);
    }
}

/**
 * Build PDF content with TCPDF
 */
function buildPDFContent($pdf, $fixture, $variant) {
    // Header section
    $pdf->SetFont('helvetica', 'B', 24);
    $name = $fixture['name'] ?? 'Datasheet';
    $power = $variant['power'] ?? '';
    $title = $name . ($power && stripos($name, (string)$power) === false ? " {$power}W" : '');
    $pdf->MultiCell(0, 30, strtoupper($title), 0, 'L');

    // Manufacturer
    $pdf->SetFont('helvetica', '', 12);
    $mfr = 'Manufacturer: ' . ($fixture['mfr'] ?? 'Short Circuit Company');
    $pdf->Cell(0, 20, $mfr, 'B', 1, 'L');

    $pdf->Ln(10);

    // Overview section
    if (!empty($variant['abstract'])) {
        $pdf->SetFont('helvetica', 'B', 13);
        $pdf->SetFillColor(235, 27, 38);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 22, '  1  OVERVIEW', 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->Ln(5);
        $pdf->SetFont('helvetica', '', 11);
        $pdf->MultiCell(0, 16, $variant['abstract'], 0, 'L');
        $pdf->Ln(10);
    }

    // General Specifications
    $pdf->SetFont('helvetica', 'B', 13);
    $pdf->SetFillColor(235, 27, 38);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 22, '  2  GENERAL SPECIFICATIONS', 0, 1, 'L', true);
    $pdf->SetTextColor(0, 0, 0);

    $pdf->Ln(5);

    // Build specifications table
    $specs = buildSpecificationsArray($variant);
    $pdf->SetFont('helvetica', '', 11);

    foreach ($specs as $spec) {
        if ($spec['value']) {
            $pdf->SetFillColor(249, 249, 249);
            $pdf->Cell(200, 18, $spec['label'], 1, 0, 'L', true);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Cell(0, 18, (string)$spec['value'], 1, 1, 'L');
        }
    }
}

/**
 * Build specifications array from variant data
 */
function buildSpecificationsArray($variant) {
    $x = $variant['x'] ?? [];

    return [
        ['label' => 'Power', 'value' => isset($variant['power']) ? $variant['power'] . ' W' : null],
        ['label' => 'Efficacy', 'value' => isset($variant['efficacy']) ? $variant['efficacy'] . ' lm/W' : null],
        ['label' => 'Total Luminous Flux', 'value' => $x['total_luminous_lm'] ?? null],
        ['label' => 'Beam Angle', 'value' => isset($x['beam_angle_deg']) ? $x['beam_angle_deg'] . '°' : null],
        ['label' => 'Color Temperature', 'value' => isset($x['cct_k']) ? $x['cct_k'] . ' K' : null],
        ['label' => 'CRI', 'value' => isset($variant['cri']) ? '>' . $variant['cri'] : null],
        ['label' => 'Power Factor', 'value' => $variant['pf'] ?? null],
        ['label' => 'LED Chip', 'value' => $variant['chip'] ?? null],
        ['label' => 'Driver', 'value' => $variant['driver'] ?? null],
        ['label' => 'Input Voltage', 'value' => $x['input_voltage'] ?? null],
        ['label' => 'Operating Temperature', 'value' => $x['operating_temperature'] ?? null],
        ['label' => 'Humidity', 'value' => $x['humidity'] ?? null],
        ['label' => 'IP Rating', 'value' => $x['ip_rating'] ?? null],
        ['label' => 'Lifetime', 'value' => $x['lifetime_hours'] ?? null],
        ['label' => 'Warranty', 'value' => $x['warranty_years'] ?? null],
        ['label' => 'Weight', 'value' => $x['weight_kg'] ?? null],
    ];
}
