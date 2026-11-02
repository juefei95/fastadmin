<?php
namespace addons\checkin\library;

class SpringFestival
{
    public static function contains($date)
    {
        $year = (int)substr($date, 0, 4);
        $newYearDates = require __DIR__ . '/spring_festival_dates.php';
        if (!isset($newYearDates[$year])) return false;
        $firstDay = $newYearDates[$year];
        $lastDay = date('Y-m-d', strtotime($firstDay . ' +6 days'));
        return $date >= $firstDay && $date <= $lastDay;
    }
}
