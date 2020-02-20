<?php


namespace Salman\Mqtt\MqttClass;


class Helper
{
    /**
     * Converts given time to seconds
     * @param $duration time to convert
     * @return float|int the converted time in seconds
     */
    public static function timeToSeconds($duration)
    {
        $hours = (int)($duration / 60 / 60);
        $minutes = (int)($duration / 60) - $hours * 60;
        return (int)$duration - $hours * 60 * 60 - $minutes * 60;
    }
}
