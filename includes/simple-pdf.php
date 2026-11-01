<?php
declare(strict_types=1);

final class SimplePdf
{
    private const WIDTH = 595.28;
    private const HEIGHT = 841.89;
    private array $pages = [];
    private int $currentPage = -1;

    public function addPage(): void
    {
        $this->pages[] = '';
        $this->currentPage = count($this->pages) - 1;
    }

    public function text(float $x, float $top, string $text, float $size = 10, bool $bold = false): void
    {
        $this->ensurePage();
        $font = $bold ? 'F2' : 'F1';
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) ?: $text;
        $encoded = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
        $y = self::HEIGHT - $top;
        $this->pages[$this->currentPage] .= sprintf("BT /%s %.2F Tf 1 0 0 1 %.2F %.2F Tm (%s) Tj ET\n", $font, $size, $x, $y, $encoded);
    }

    public function line(float $x1, float $top1, float $x2, float $top2, float $width = 0.7): void
    {
        $this->ensurePage();
        $y1 = self::HEIGHT - $top1;
        $y2 = self::HEIGHT - $top2;
        $this->pages[$this->currentPage] .= sprintf("%.2F w %.2F %.2F m %.2F %.2F l S\n", $width, $x1, $y1, $x2, $y2);
    }

    public function filledRectangle(float $x, float $top, float $width, float $height, float $red, float $green, float $blue): void
    {
        $this->ensurePage();
        $y = self::HEIGHT - $top - $height;
        $this->pages[$this->currentPage] .= sprintf("%.3F %.3F %.3F rg %.2F %.2F %.2F %.2F re f 0 0 0 rg\n", $red, $green, $blue, $x, $y, $width, $height);
    }

    public function output(): string
    {
        $this->ensurePage();
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];
        foreach ($this->pages as $index => $content) {
            $pageObject = 5 + ($index * 2);
            $contentObject = $pageObject + 1;
            $kids[] = $pageObject . ' 0 R';
            $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . self::WIDTH . ' ' . self::HEIGHT . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
            $objects[$contentObject] = "<< /Length " . strlen($content) . " >>\nstream\n" . $content . "endstream";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [0];
        foreach ($objects as $number => $object) {
            $offsets[$number] = strlen($pdf);
            $pdf .= $number . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $size = max(array_keys($objects)) + 1;
        $pdf .= "xref\n0 " . $size . "\n0000000000 65535 f \n";
        for ($number = 1; $number < $size; $number++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$number] ?? 0);
        }
        $pdf .= "trailer\n<< /Size " . $size . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";
        return $pdf;
    }

    private function ensurePage(): void
    {
        if ($this->currentPage < 0) {
            $this->addPage();
        }
    }
}
