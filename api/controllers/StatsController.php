<?php

class StatsController
{
    public static function handler()
    {
        $uptime = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
        $memory = round(memory_get_usage() / 1024 / 1024, 2);
        echo json_encode([
            "uptime_seconds" => $uptime,
            "memory_MB" => $memory,
            "fecha" => date("Y-m-d H:i:s")  
        ]);
    }
}




