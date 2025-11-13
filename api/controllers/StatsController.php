<?php
// controllers/StatsController.php
require_once __DIR__ . '/../config/logger.php';

class StatsController
{
    public static function handler()
    {
        try {
            // Calcular métricas del sistema
            $uptime = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
            $memory = round(memory_get_usage() / 1024 / 1024, 2);
            $peakMemory = round(memory_get_peak_usage() / 1024 / 1024, 2);
            
            // Registrar evento de estadísticas
            Logger::info("Estadísticas del sistema consultadas - Uptime: {$uptime}s, Memoria: {$memory}MB");
            
            // Devolver métricas
            echo json_encode([
                "success" => true,
                "uptime_seconds" => $uptime,
                "memory_MB" => $memory,
                "peak_memory_MB" => $peakMemory,
                "fecha" => date("Y-m-d H:i:s"),
                "server_software" => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido'
            ]);
            
        } catch (Exception $e) {
            // Manejo de errores
            Logger::error("Error en StatsController: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error" => "Error al obtener estadísticas del sistema"
            ]);
        }
    }
}