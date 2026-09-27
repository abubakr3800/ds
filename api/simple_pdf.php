<?php
/**
 * Minimal FPDF-like PDF generator
 * Creates basic but valid PDFs without external dependencies
 */

class SimplePDF {
    private $pages = [];
    private $currentPage = '';
    private $yPos = 50;

    public function addPage() {
        if ($this->currentPage) {
            $this->pages[] = $this->currentPage;
        }
        $this->currentPage = '';
        $this->yPos = 50;
    }

    public function setFont($font, $style, $size) {
        // Simplified font setting
    }

    public function cell($width, $height, $text) {
        // Escape special PDF literal characters
        $escaped = strtr((string)$text, [
            '\\' => '\\\\',
            '('  => '\\(',
            ')'  => '\\)',
            "\r" => '',
            "\n" => ' '
        ]);
        $this->currentPage .= "BT\n/F1 12 Tf\n50 " . (792 - $this->yPos) . " Td\n($escaped) Tj\nET\n";
        $this->yPos += $height;
    }

    public function ln($height = 10) {
        $this->yPos += $height;
    }

    public function multiCell($width, $height, $text) {
        $lines = explode("\n", wordwrap((string)$text, 80));
        foreach ($lines as $line) {
            $this->cell($width, $height, $line);
        }
    }

    public function output() {
        if ($this->currentPage) {
            $this->pages[] = $this->currentPage;
        }

        $content = implode("\n", $this->pages);

        $obj1 = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $obj2 = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $obj3 = "3 0 obj\n<< /Type /Page /Parent 2 0 R /Resources 4 0 R /MediaBox [0 0 612 792] /Contents 5 0 R >>\nendobj\n";
        $obj4 = "4 0 obj\n<< /Font << /F1 << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> >> >>\nendobj\n";
        $obj5 = "5 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream\nendobj\n";

        $header = "%PDF-1.4\n";
        $offsets = [];
        $pdf = $header;

        $objects = [$obj1, $obj2, $obj3, $obj4, $obj5];
        foreach ($objects as $i => $obj) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= $obj;
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 6\n";
        $pdf .= "0000000000 65535 f \r\n";
        for ($i = 1; $i <= 5; $i++) {
            $pdf .= sprintf("%010d 00000 n \r\n", $offsets[$i]);
        }

        $pdf .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n$xrefPos\n%%EOF\r\n";

        return $pdf;
    }
}
