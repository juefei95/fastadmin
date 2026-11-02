<?php
require __DIR__ . '/../library/SpringFestival.php';

use addons\checkin\library\SpringFestival;

foreach ([
    '2026-02-16' => false,
    '2026-02-17' => true,
    '2026-02-23' => true,
    '2026-02-24' => false,
    '2027-02-05' => false,
    '2027-02-06' => true,
    '2027-02-12' => true,
    '2027-02-13' => false,
    '2028-01-26' => true,
    '2028-02-02' => false,
] as $date => $expected) {
    if (SpringFestival::contains($date) !== $expected) {
        throw new RuntimeException('Wrong Spring Festival classification for ' . $date);
    }
}
echo "Spring Festival boundary dates passed\n";
