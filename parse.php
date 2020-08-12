#!/usr/bin/env php
<?php

error_reporting(-1);

const FIRST_DOW_COL = 5;
const TZ_COL = 4;

exit(main());

function main(): int {
    $daystr = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    echo "day,hour,count\n";
    foreach (get_adjusted_hours('poll.csv') as $dow => $hours) {
        ksort($hours);
        foreach($hours as $hour => $count) {
            echo "\"", $daystr[$dow], "\",$hour,$count\n";
        }
    }

    return 0;
}

function get_adjusted_hours(string $path): array {
    $h = fopen($path, 'r');

    $perDOW = array_fill(0, 7, []);
    fgetcsv($h, 4096); // skip headers

    while (($line = fgetcsv($h, 4096)) !== false) {
        $offset = parse_offset($line[TZ_COL]);
        if ($offset === null) {
            fwrite(STDERR, 'cannot parse TZ ' . $line[TZ_COL]);
            continue;
        }

        for ($i = FIRST_DOW_COL; $i < FIRST_DOW_COL+7; $i++) {
            if ($line[$i] === '') {
                continue;
            }

            $hours = array_merge(
                explode(',', $line[$i]),
                explode(',', $line[$i+7]),
            );

            foreach ($hours as $v) {
                $hour = ((int) substr(trim($v), 0, -1)) + $offset;
                $iOff = 0;
                if ($hour < 0) {
                    $iOff = -1;
                    $hour = 24 + $hour;
                } else if ($hour > 24) {
                    $iOff = 1;
                }
                $hour = $hour % 24;

                if (($i + $iOff) < FIRST_DOW_COL) {
                    $iOff = 6;
                } else {
                    if (($i + $iOff) > (FIRST_DOW_COL+7)) {
                        $iOff = -1;
                    }
                }

                $index = $i - FIRST_DOW_COL + $iOff;

                if (!array_key_exists($hour, $perDOW[$index])) {
                    $perDOW[$index][$hour] = 0;
                }

                $perDOW[$index][$hour]++;
            }
        }
    }

    fclose($h);
    return $perDOW;
}

function parse_offset(string $str): ?int {
    if ($str === 'UTC' || $str === 'GMT') {
        return 0;
    }

    if (!preg_match('`([-+])(\d{1,2}):`', $str, $matches)) {
        return null;
    }

    $sign = 1;
    if ($matches[1] === '-') {
        $sign = -1;
    }

    return $sign * $matches[2];
}
