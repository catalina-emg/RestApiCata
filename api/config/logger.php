<?php
// config/logger.php

/**
 * Sistema centralizado de logs para la API REST
 * - Registra eventos importantes sin datos sensibles
 * - Rotación automática para evitar archivos muy grandes
 * - Múltiples niveles: INFO, WARN, ERROR
 */

class Logger {
    private static $logFile = null;

    private static function ensureInit() {
        if (self::$logFile === null) {
            // Forzar uso del log dentro del repositorio `logs`.
            // Esto mantiene el archivo `logs/server.log` como copia principal.
            $projectLogDir = __DIR__ . '/../logs';
            if (!is_dir($projectLogDir)) {
                @mkdir($projectLogDir, 0755, true);
            }
            self::$logFile = $projectLogDir . DIRECTORY_SEPARATOR . 'server.log';
        }
    }

    /**
     * Verifica si el log alcanza el número máximo de líneas y, si es así,
     * comprime el archivo (gzip) en `logs/archive/` y vacía el original.
     * La cantidad máxima de líneas se configura con la variable de entorno LOG_MAX_LINES (int).
     */
    private static function rotateIfNeeded() {
        self::ensureInit();

        $maxLines = getenv('LOG_MAX_LINES');
        $maxLines = ($maxLines && is_numeric($maxLines)) ? intval($maxLines) : 5000;

        if (!file_exists(self::$logFile)) {
            return;
        }

        $lineCount = 0;
        $fp = @fopen(self::$logFile, 'r');
        if ($fp) {
            while (!feof($fp)) {
                fgets($fp);
                $lineCount++;
                if ($lineCount >= $maxLines) {
                    break;
                }
            }
            fclose($fp);
        }

        if ($lineCount < $maxLines) {
            return; // no es necesario rotar
        }

        // Crear directorio de archivos archivados
        $archiveDir = dirname(self::$logFile) . DIRECTORY_SEPARATOR . 'archive';
        if (!is_dir($archiveDir)) {
            @mkdir($archiveDir, 0755, true);
        }

        $timestamp = date('Ymd_His');
        $archivePath = $archiveDir . DIRECTORY_SEPARATOR . 'server.log.' . $timestamp . '.gz';

        // Comprimir el archivo actual en chunks y escribir el .gz
        $in = @fopen(self::$logFile, 'rb');
        if ($in) {
            $out = @gzopen($archivePath, 'wb9');
            if ($out) {
                while (!feof($in)) {
                    $chunk = fread($in, 1024 * 512);
                    if ($chunk === false) break;
                    gzwrite($out, $chunk);
                }
                gzclose($out);
            }
            fclose($in);

            // Truncar el archivo original para empezar uno nuevo
            $fp2 = @fopen(self::$logFile, 'w');
            if ($fp2) {
                // Escribir una entrada indicando rotación
                $note = "[" . date('Y-m-d H:i:s') . "] [INFO] Rotated log to " . basename($archivePath) . PHP_EOL;
                fwrite($fp2, $note);
                fclose($fp2);
            }
        }
    }

    private static function write($level, $message) {
        self::ensureInit();
        // Rotar/comprimir si es necesario antes de escribir
        self::rotateIfNeeded();
        $time = date('Y-m-d H:i:s');
        $entry = "[$time] [$level] " . $message . PHP_EOL;
        // Usar bloqueo para evitar escrituras concurrentes
        @file_put_contents(self::$logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    public static function info($message) {
        self::write('INFO', $message);
    }

    public static function warn($message) {
        self::write('WARN', $message);
    }

    public static function error($message) {
        self::write('ERROR', $message);
    }
}

date_default_timezone_set('America/Mexico_City');
function log_event($message,  $type = 'INFO') 
{
    // Si la clase Logger existe, reutilizar sus métodos para mantener consistencia
    if (class_exists('Logger')) {
        $t = strtoupper($type);
        if ($t === 'ERROR') { Logger::error($message); return; }
        if ($t === 'WARN' || $t === 'WARNING') { Logger::warn($message); return; }
        Logger::info($message);
        return;
    }

    // Fallback si Logger no está disponible: intentar usar la misma lógica de ruta
    $envDir = getenv('LOG_DIR');
    $defaultExternal = 'C:\\xampp\\logs\\rest-api-catalina';
    $useDir = null;
    if ($envDir) {
        $useDir = $envDir;
    } elseif (is_dir($defaultExternal) || @mkdir($defaultExternal, 0755, true)) {
        $useDir = $defaultExternal;
    }

    if ($useDir && is_dir($useDir)) {
        $logFile = rtrim($useDir, '/\\') . DIRECTORY_SEPARATOR . 'server.log';
    } else {
        $logFile = __DIR__ . "/../logs/server.log";
        $dir = dirname($logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
    }

    $date = date('Y-m-d H:i:s');
    $entry = "[$date] [$type] $message" . PHP_EOL;
    @file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}