<?php
// controllers/StatsController.php
require_once __DIR__ . '/../config/logger.php';

/**
 * Controlador para proporcionar estadísticas del sistema en tiempo real
 * - Métricas de rendimiento del servidor
 * - Uso de memoria y tiempo de actividad
 * - Logs de consultas para monitoreo
 */

class StatsController
{
     /**
     * Maneja las solicitudes al endpoint /stats
     * Retorna métricas del sistema en formato JSON
     */
    public static function handler()
    {
        try {
            // Calcular tiempo de actividad desde que inició la solicitud
            $uptime = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
            // Obtener uso actual de memoria en MB
            $memory = round(memory_get_usage() / 1024 / 1024, 2);
             // Obtener pico máximo de memoria usado en MB
            $peakMemory = round(memory_get_peak_usage() / 1024 / 1024, 2);
            // Registrar consulta de estadísticas en el log
            Logger::info("Estadísticas del sistema consultadas - Uptime: {$uptime}s, Memoria: {$memory}MB");
            
            // Devolver métricas en formato JSON    
              echo json_encode([
                "success" => true,
                "uptime_seconds" => $uptime,        // Tiempo de actividad en segundos
                "memory_MB" => $memory,            // Memoria actual en uso
                "peak_memory_MB" => $peakMemory,   // Pico máximo de memoria
                "fecha" => date("Y-m-d H:i:s"),    // Fecha y hora actual
                "server_software" => $_SERVER['SERVER_SOFTWARE'] ?? 'Desconocido' // Info del servidor
            ]);
            
        } catch (Exception $e) {
             // Manejo de errores en caso de fallo
            Logger::error("Error en StatsController: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                "success" => false,
                "error" => "Error al obtener estadísticas del sistema"
            ]);
        }
    }
}