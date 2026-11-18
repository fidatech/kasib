<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Draws a QR code as an SVG picture, without any library. Text is stored as bytes with the
 * medium level of error correction; up to 213 bytes fit (version 10), enough for an address
 * or the secret of a two-step sign-in.
 */
class QrCode
{
    /** version => [error-correction codewords per block, [[number of blocks, data codewords in each], ...]] (level M) */
    private const BLOCKS = [
        1 => [10, [[1, 16]]], 2 => [16, [[1, 28]]], 3 => [26, [[1, 44]]], 4 => [18, [[2, 32]]], 5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]], 7 => [18, [[4, 31]]], 8 => [22, [[2, 38], [2, 39]]], 9 => [22, [[3, 36], [2, 37]]], 10 => [26, [[4, 43], [1, 44]]],
    ];

    private const ALIGNMENT = [1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30], 6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50]];

    private const VERSION_INFO = [7 => 0x07C94, 8 => 0x085BC, 9 => 0x09A99, 10 => 0x0A4D3];

    /** The picture: dark squares on white, with the quiet border a reader needs. */
    public static function svg(string $text, int $pixels = 160): string
    {
        $matrix = self::matrix($text);
        $size = count($matrix);
        $path = '';
        foreach ($matrix as $y => $row) {
            foreach ($row as $x => $dark) {
                if ($dark) {
                    $path .= 'M'.($x + 4).' '.($y + 4).'h1v1h-1z';
                }
            }
        }
        $box = $size + 8;

        return '<svg xmlns="http://www.w3.org/2000/svg" width="'.$pixels.'" height="'.$pixels.'" viewBox="0 0 '.$box.' '.$box.'" shape-rendering="crispEdges" role="img">'
            .'<rect width="'.$box.'" height="'.$box.'" fill="#fff"/><path d="'.$path.'" fill="#000"/></svg>';
    }

    /** @return array<int, array<int, bool>> rows of squares, true where a square is dark */
    public static function matrix(string $text): array
    {
        $length = strlen($text);
        $version = null;
        foreach (self::BLOCKS as $v => [$ec, $groups]) {
            $capacity = array_sum(array_map(fn (array $g) => $g[0] * $g[1], $groups));
            // 4 bits for the mode, 8 or 16 for the length
            if ($length * 8 + 4 + ($v < 10 ? 8 : 16) <= $capacity * 8) {
                $version = $v;
                break;
            }
        }
        if ($version === null) {
            throw new InvalidArgumentException('The text is too long for a QR code.');
        }
        [$ecLength, $groups] = self::BLOCKS[$version];
        $capacity = array_sum(array_map(fn (array $g) => $g[0] * $g[1], $groups));

        // the data: mode, length, the bytes, an end mark, then filler up to the capacity
        $bits = '0100'.str_pad(decbin($length), $version < 10 ? 8 : 16, '0', STR_PAD_LEFT);
        foreach (unpack('C*', $text) ?: [] as $byte) {
            $bits .= str_pad(decbin($byte), 8, '0', STR_PAD_LEFT);
        }
        $bits .= str_repeat('0', min(4, $capacity * 8 - strlen($bits)));
        $bits .= str_repeat('0', (8 - strlen($bits) % 8) % 8);
        $data = array_map('bindec', str_split($bits, 8));
        for ($i = 0; count($data) < $capacity; $i++) {
            $data[] = $i % 2 === 0 ? 0xEC : 0x11;
        }

        // cut into blocks, each with its own error-correction codewords, then woven together
        $dataBlocks = [];
        $ecBlocks = [];
        $offset = 0;
        foreach ($groups as [$count, $each]) {
            for ($b = 0; $b < $count; $b++) {
                $block = array_slice($data, $offset, $each);
                $offset += $each;
                $dataBlocks[] = $block;
                $ecBlocks[] = self::remainder($block, $ecLength);
            }
        }
        $codewords = [];
        for ($i = 0; $i < max(array_map('count', $dataBlocks)); $i++) {
            foreach ($dataBlocks as $block) {
                if (isset($block[$i])) {
                    $codewords[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecLength; $i++) {
            foreach ($ecBlocks as $block) {
                $codewords[] = $block[$i];
            }
        }

        $size = 17 + 4 * $version;
        $m = array_fill(0, $size, array_fill(0, $size, false));
        $fixed = array_fill(0, $size, array_fill(0, $size, false));
        $set = function (int $x, int $y, bool $dark) use (&$m, &$fixed, $size) {
            if ($x >= 0 && $x < $size && $y >= 0 && $y < $size) {
                $m[$y][$x] = $dark;
                $fixed[$y][$x] = true;
            }
        };

        // timing lines, the three corner squares, the small alignment squares
        for ($i = 0; $i < $size; $i++) {
            $set(6, $i, $i % 2 === 0);
            $set($i, 6, $i % 2 === 0);
        }
        foreach ([[3, 3], [$size - 4, 3], [3, $size - 4]] as [$cx, $cy]) {
            for ($dy = -4; $dy <= 4; $dy++) {
                for ($dx = -4; $dx <= 4; $dx++) {
                    $d = max(abs($dx), abs($dy));
                    $set($cx + $dx, $cy + $dy, $d !== 2 && $d !== 4);
                }
            }
        }
        $centers = self::ALIGNMENT[$version];
        $last = count($centers) - 1;
        foreach ($centers as $i => $cy) {
            foreach ($centers as $j => $cx) {
                if (($i === 0 && $j === 0) || ($i === 0 && $j === $last) || ($i === $last && $j === 0)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $set($cx + $dx, $cy + $dy, max(abs($dx), abs($dy)) !== 1);
                    }
                }
            }
        }
        if ($version >= 7) {
            for ($i = 0; $i < 18; $i++) {
                $dark = ((self::VERSION_INFO[$version] >> $i) & 1) === 1;
                $a = $size - 11 + $i % 3;
                $b = intdiv($i, 3);
                $set($a, $b, $dark);
                $set($b, $a, $dark);
            }
        }
        // the places of the format bits are kept free; the bits themselves depend on the mask
        $format = function (int $mask) use ($set, $size) {
            $value = $mask;   // level M is written as 00
            $rem = $value;
            for ($i = 0; $i < 10; $i++) {
                $rem = ($rem << 1) ^ (($rem >> 9) * 0x537);
            }
            $bits = (($value << 10) | $rem) ^ 0x5412;
            $bit = fn (int $i) => (($bits >> $i) & 1) === 1;
            for ($i = 0; $i <= 5; $i++) {
                $set(8, $i, $bit($i));
            }
            $set(8, 7, $bit(6));
            $set(8, 8, $bit(7));
            $set(7, 8, $bit(8));
            for ($i = 9; $i < 15; $i++) {
                $set(14 - $i, 8, $bit($i));
            }
            for ($i = 0; $i < 8; $i++) {
                $set($size - 1 - $i, 8, $bit($i));
            }
            for ($i = 8; $i < 15; $i++) {
                $set(8, $size - 15 + $i, $bit($i));
            }
            $set(8, $size - 8, true);
        };
        $format(0);

        // the codewords go in two-square columns, from the bottom right corner, up and down in turn
        $stream = '';
        foreach ($codewords as $codeword) {
            $stream .= str_pad(decbin($codeword), 8, '0', STR_PAD_LEFT);
        }
        $i = 0;
        for ($right = $size - 1; $right >= 1; $right -= 2) {
            if ($right === 6) {
                $right = 5;
            }
            for ($vert = 0; $vert < $size; $vert++) {
                for ($j = 0; $j < 2; $j++) {
                    $x = $right - $j;
                    $y = (($right + 1) & 2) === 0 ? $size - 1 - $vert : $vert;
                    if (! $fixed[$y][$x] && $i < strlen($stream)) {
                        $m[$y][$x] = $stream[$i] === '1';
                        $i++;
                    }
                }
            }
        }

        // the mask that leaves the calmest picture
        $best = null;
        $bestScore = PHP_INT_MAX;
        $plain = $m;
        for ($mask = 0; $mask < 8; $mask++) {
            $m = $plain;
            for ($y = 0; $y < $size; $y++) {
                for ($x = 0; $x < $size; $x++) {
                    if (! $fixed[$y][$x] && self::masked($mask, $x, $y)) {
                        $m[$y][$x] = ! $m[$y][$x];
                    }
                }
            }
            $format($mask);
            $score = self::penalty($m, $size);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $m;
            }
        }

        return $best;
    }

    private static function masked(int $mask, int $x, int $y): bool
    {
        return match ($mask) {
            0 => ($x + $y) % 2 === 0,
            1 => $y % 2 === 0,
            2 => $x % 3 === 0,
            3 => ($x + $y) % 3 === 0,
            4 => (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0,
            5 => $x * $y % 2 + $x * $y % 3 === 0,
            6 => ($x * $y % 2 + $x * $y % 3) % 2 === 0,
            default => (($x + $y) % 2 + $x * $y % 3) % 2 === 0,
        };
    }

    /** Long runs of one colour, blocks of one colour, and too much of one colour make a code harder to read. */
    private static function penalty(array $m, int $size): int
    {
        $score = 0;
        $dark = 0;
        for ($a = 0; $a < $size; $a++) {
            foreach ([true, false] as $rows) {
                $run = 1;
                for ($b = 1; $b < $size; $b++) {
                    $now = $rows ? $m[$a][$b] : $m[$b][$a];
                    $before = $rows ? $m[$a][$b - 1] : $m[$b - 1][$a];
                    if ($now === $before) {
                        $run++;
                        if ($run === 5) {
                            $score += 3;
                        } elseif ($run > 5) {
                            $score++;
                        }
                    } else {
                        $run = 1;
                    }
                }
            }
            for ($b = 0; $b < $size; $b++) {
                $dark += $m[$a][$b] ? 1 : 0;
                if ($a < $size - 1 && $b < $size - 1 && $m[$a][$b] === $m[$a][$b + 1] && $m[$a][$b] === $m[$a + 1][$b] && $m[$a][$b] === $m[$a + 1][$b + 1]) {
                    $score += 3;
                }
            }
        }

        return $score + 10 * intdiv((int) abs($dark * 20 - $size * $size * 10), $size * $size);
    }

    /** The error-correction codewords of one block (Reed-Solomon over GF(256)). */
    private static function remainder(array $data, int $degree): array
    {
        static $exp = null, $log = null;
        if ($exp === null) {
            $exp = [];
            $log = [];
            for ($i = 0, $x = 1; $i < 255; $i++) {
                $exp[$i] = $x;
                $log[$x] = $i;
                $x <<= 1;
                if ($x & 0x100) {
                    $x ^= 0x11D;
                }
            }
        }
        $mul = fn (int $a, int $b) => $a === 0 || $b === 0 ? 0 : $exp[($log[$a] + $log[$b]) % 255];

        // the generator polynomial: (x - 1)(x - 2)(x - 4) ...
        $generator = array_fill(0, $degree, 0);
        $generator[$degree - 1] = 1;
        $root = 1;
        for ($i = 0; $i < $degree; $i++) {
            for ($j = 0; $j < $degree; $j++) {
                $generator[$j] = $mul($generator[$j], $root);
                if ($j + 1 < $degree) {
                    $generator[$j] ^= $generator[$j + 1];
                }
            }
            $root = $mul($root, 2);
        }

        $result = array_fill(0, $degree, 0);
        foreach ($data as $byte) {
            $factor = $byte ^ array_shift($result);
            $result[] = 0;
            foreach ($generator as $i => $coefficient) {
                $result[$i] ^= $mul($coefficient, $factor);
            }
        }

        return $result;
    }
}
