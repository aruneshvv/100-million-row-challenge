<?php

namespace App;

final class Parser
{
    public function parse(string $inputPath, string $outputPath): void
    {
        gc_disable();

        $fileSize = filesize($inputPath);
        $data = [];
        $handle = fopen($inputPath, 'r');

        $remaining = $fileSize;
        $leftover = '';

        while ($remaining > 0) {
            $chunk = fread($handle, min(8_388_608, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }
            $remaining -= strlen($chunk);

            $startPos = 0;

            // Complete leftover line without copying the entire buffer
            if ($leftover !== '') {
                $firstNl = strpos($chunk, "\n");
                if ($firstNl === false) {
                    $leftover .= $chunk;
                    continue;
                }
                $line = $leftover . substr($chunk, 0, $firstNl);
                $len = strlen($line);
                if ($len > 45) {
                    $path = substr($line, 19, $len - 45);
                    $ds = substr($line, $len - 25, 10);
                    $dateInt = (int)($ds[0] . $ds[1] . $ds[2] . $ds[3] . $ds[5] . $ds[6] . $ds[8] . $ds[9]);
                    if (isset($data[$path][$dateInt])) {
                        $data[$path][$dateInt]++;
                    } elseif (isset($data[$path])) {
                        $data[$path][$dateInt] = 1;
                    } else {
                        $data[$path] = [$dateInt => 1];
                    }
                }
                $startPos = $firstNl + 1;
                $leftover = '';
            }

            $lastNl = strrpos($chunk, "\n");
            if ($lastNl === false || $lastNl < $startPos) {
                $leftover = ($startPos > 0) ? substr($chunk, $startPos) : $chunk;
                continue;
            }
            if ($lastNl < strlen($chunk) - 1) {
                $leftover = substr($chunk, $lastNl + 1);
            } else {
                $leftover = '';
            }

            // Hot parsing loop — 2x unrolled, integer date keys
            $pos = $startPos;
            while ($pos < $lastNl) {
                $nlPos = strpos($chunk, "\n", $pos);
                if ($nlPos === false) {
                    break;
                }
                $path = substr($chunk, $pos + 19, $nlPos - $pos - 45);
                $ds = substr($chunk, $nlPos - 25, 10);
                $dateInt = (int)($ds[0] . $ds[1] . $ds[2] . $ds[3] . $ds[5] . $ds[6] . $ds[8] . $ds[9]);
                if (isset($data[$path][$dateInt])) {
                    $data[$path][$dateInt]++;
                } elseif (isset($data[$path])) {
                    $data[$path][$dateInt] = 1;
                } else {
                    $data[$path] = [$dateInt => 1];
                }
                $pos = $nlPos + 1;
                if ($pos >= $lastNl) {
                    break;
                }

                $nlPos = strpos($chunk, "\n", $pos);
                if ($nlPos === false) {
                    break;
                }
                $path = substr($chunk, $pos + 19, $nlPos - $pos - 45);
                $ds = substr($chunk, $nlPos - 25, 10);
                $dateInt = (int)($ds[0] . $ds[1] . $ds[2] . $ds[3] . $ds[5] . $ds[6] . $ds[8] . $ds[9]);
                if (isset($data[$path][$dateInt])) {
                    $data[$path][$dateInt]++;
                } elseif (isset($data[$path])) {
                    $data[$path][$dateInt] = 1;
                } else {
                    $data[$path] = [$dateInt => 1];
                }
                $pos = $nlPos + 1;
            }
        }

        // Handle final leftover (last line without trailing newline)
        if ($leftover !== '') {
            $len = strlen($leftover);
            if ($len > 45) {
                $path = substr($leftover, 19, $len - 45);
                $ds = substr($leftover, $len - 25, 10);
                $dateInt = (int)($ds[0] . $ds[1] . $ds[2] . $ds[3] . $ds[5] . $ds[6] . $ds[8] . $ds[9]);
                if (isset($data[$path][$dateInt])) {
                    $data[$path][$dateInt]++;
                } elseif (isset($data[$path])) {
                    $data[$path][$dateInt] = 1;
                } else {
                    $data[$path] = [$dateInt => 1];
                }
            }
        }

        fclose($handle);

        // Sort dates and convert integer keys back to YYYY-MM-DD strings
        foreach ($data as &$dates) {
            ksort($dates);
            $stringDates = [];
            foreach ($dates as $dateInt => $cnt) {
                $d = (string)$dateInt;
                $stringDates[$d[0] . $d[1] . $d[2] . $d[3] . '-' . $d[4] . $d[5] . '-' . $d[6] . $d[7]] = $cnt;
            }
            $dates = $stringDates;
        }
        unset($dates);

        file_put_contents($outputPath, json_encode($data, JSON_PRETTY_PRINT));
    }
}
