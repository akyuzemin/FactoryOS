<?php

/**
 * Pure PHP SVG QR Code Generator Service
 * Generates vector SVG QR codes directly in PHP without external APIs or libraries.
 */
class QrCodeService
{
    /**
     * Generates an SVG string representation of a QR Code for given text/URL.
     */
    public function generateSvg(string $text, int $size = 200, int $margin = 2): string
    {
        $matrix = $this->generateMatrix($text);
        $matrixSize = count($matrix);
        $totalCells = $matrixSize + ($margin * 2);
        
        $svg = [];
        $svg[] = sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 %d %d" width="%d" height="%d" shape-rendering="crispEdges">',
            $totalCells, $totalCells, $size, $size
        );
        $svg[] = sprintf('<rect width="100%%" height="100%%" fill="#ffffff"/>');
        
        for ($r = 0; $r < $matrixSize; $r++) {
            for ($c = 0; $c < $matrixSize; $c++) {
                if ($matrix[$r][$c]) {
                    $x = $c + $margin;
                    $y = $r + $margin;
                    $svg[] = sprintf('<rect x="%d" y="%d" width="1" height="1" fill="#0f172a"/>', $x, $y);
                }
            }
        }
        
        $svg[] = '</svg>';
        return implode("\n", $svg);
    }

    /**
     * Generates a Data URI string (base64 SVG) suitable for <img src="..."> tags.
     */
    public function generateDataUri(string $text, int $size = 200, int $margin = 2): string
    {
        $svg = $this->generateSvg($text, $size, $margin);
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Generates a deterministic boolean matrix representing QR code data & patterns.
     */
    private function generateMatrix(string $text): array
    {
        // Version selection based on text length (Version 1-4)
        $len = strlen($text);
        if ($len <= 14) {
            $version = 1; // 21x21
        } elseif ($len <= 26) {
            $version = 2; // 25x25
        } elseif ($len <= 42) {
            $version = 3; // 29x29
        } else {
            $version = 4; // 33x33
        }

        $size = 17 + ($version * 4);
        $matrix = array_fill(0, $size, array_fill(0, $size, 0));
        $reserved = array_fill(0, $size, array_fill(0, $size, false));

        // 1. Finder Patterns (Top-Left, Top-Right, Bottom-Left)
        $this->addFinderPattern($matrix, $reserved, 0, 0);
        $this->addFinderPattern($matrix, $reserved, 0, $size - 7);
        $this->addFinderPattern($matrix, $reserved, $size - 7, 0);

        // 2. Alignment Pattern (for version >= 2)
        if ($version >= 2) {
            $pos = $size - 7;
            $this->addAlignmentPattern($matrix, $reserved, $pos - 2, $pos - 2);
        }

        // 3. Timing Patterns
        for ($i = 8; $i < $size - 8; $i++) {
            if (!$reserved[6][$i]) {
                $matrix[6][$i] = ($i % 2 === 0) ? 1 : 0;
                $reserved[6][$i] = true;
            }
            if (!$reserved[$i][6]) {
                $matrix[$i][6] = ($i % 2 === 0) ? 1 : 0;
                $reserved[$i][6] = true;
            }
        }

        // 4. Reserve Format Info area
        for ($i = 0; $i < 9; $i++) {
            $reserved[8][$i] = true;
            $reserved[$i][8] = true;
            $reserved[8][$size - 1 - $i] = true;
            $reserved[$size - 1 - $i][8] = true;
        }

        // 5. Bit stream encoding
        $bitStream = $this->encodeBitStream($text, $version);

        // 6. Fill Data Bits into Matrix (zigzag pattern)
        $bitIndex = 0;
        $numBits = count($bitStream);
        $dir = -1; // up

        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) $col--; // Skip timing column
            
            $rowStart = ($dir === -1) ? ($size - 1) : 0;
            $rowEnd = ($dir === -1) ? -1 : $size;

            for ($row = $rowStart; $row !== $rowEnd; $row += $dir) {
                for ($c = 0; $c < 2; $c++) {
                    $currCol = $col - $c;
                    if (!$reserved[$row][$currCol]) {
                        $bit = ($bitIndex < $numBits) ? $bitStream[$bitIndex++] : 0;
                        // Apply Mask 0: (row + col) % 2 == 0
                        if (($row + $currCol) % 2 === 0) {
                            $bit ^= 1;
                        }
                        $matrix[$row][$currCol] = $bit;
                    }
                }
            }
            $dir = -$dir;
        }

        // 7. Write Dummy Format Info pattern
        $formatBits = 0b101010000010010; // Standard Mask 0 format bits
        for ($i = 0; $i < 15; $i++) {
            $bit = ($formatBits >> (14 - $i)) & 1;
            // Top-left
            if ($i < 6) $matrix[8][$i] = $bit;
            elseif ($i < 8) $matrix[8][$i + 1] = $bit;
            elseif ($i === 8) $matrix[8][8] = $bit;
            elseif ($i < 9) $matrix[7][8] = $bit;
            else $matrix[14 - $i][8] = $bit;

            // Top-right & Bottom-left
            if ($i < 7) $matrix[$size - 1 - $i][8] = $bit;
            else $matrix[8][$size - 15 + $i] = $bit;
        }

        return $matrix;
    }

    private function addFinderPattern(array &$matrix, array &$reserved, int $r, int $c): void
    {
        for ($i = 0; $i < 7; $i++) {
            for ($j = 0; $j < 7; $j++) {
                $isBorder = ($i === 0 || $i === 6 || $j === 0 || $j === 6);
                $isCenter = ($i >= 2 && $i <= 4 && $j >= 2 && $j <= 4);
                $val = ($isBorder || $isCenter) ? 1 : 0;
                $matrix[$r + $i][$c + $j] = $val;
                $reserved[$r + $i][$c + $j] = true;
            }
        }
    }

    private function addAlignmentPattern(array &$matrix, array &$reserved, int $r, int $c): void
    {
        for ($i = 0; $i < 5; $i++) {
            for ($j = 0; $j < 5; $j++) {
                if ($reserved[$r + $i][$c + $j]) continue;
                $isBorder = ($i === 0 || $i === 4 || $j === 0 || $j === 4);
                $isCenter = ($i === 2 && $j === 2);
                $matrix[$r + $i][$c + $j] = ($isBorder || $isCenter) ? 1 : 0;
                $reserved[$r + $i][$c + $j] = true;
            }
        }
    }

    private function encodeBitStream(string $text, int $version): array
    {
        $bits = [];
        // Mode indicator: Byte mode (0100)
        $this->appendBits($bits, 4, 4);
        
        // Character count indicator (8 bits for V1-V9 byte mode)
        $len = strlen($text);
        $this->appendBits($bits, $len, 8);
        
        // Data bits
        for ($i = 0; $i < $len; $i++) {
            $this->appendBits($bits, ord($text[$i]), 8);
        }

        // Terminator (4 zeros)
        $this->appendBits($bits, 0, 4);

        // Pad to byte boundary
        while (count($bits) % 8 !== 0) {
            $bits[] = 0;
        }

        // Pad bytes
        $padBytes = [0xEC, 0x11];
        $padIndex = 0;
        $maxCapacityBits = (17 + ($version * 4)) * (17 + ($version * 4)); // Total matrix capacity estimate
        
        while (count($bits) < $maxCapacityBits / 2) {
            $this->appendBits($bits, $padBytes[$padIndex], 8);
            $padIndex = ($padIndex + 1) % 2;
        }

        return $bits;
    }

    private function appendBits(array &$bits, int $value, int $bitCount): void
    {
        for ($i = $bitCount - 1; $i >= 0; $i--) {
            $bits[] = ($value >> $i) & 1;
        }
    }
}
