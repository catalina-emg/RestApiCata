<?php
// api/middleware/RateLimitMiddleware.php

require_once __DIR__ . '/../config/logger.php';

class RateLimitMiddleware {
    
    private static $limits = [
        'auth' => [
            'attempts' => 5,    // 5 intentos por minuto para login
            'window' => 60      // 60 segundos
        ],
        'api' => [
            'attempts' => 100,  // 100 requests por minuto para API general
            'window' => 60
        ],
        'stats' => [
            'attempts' => 10,   // 10 consultas por minuto para stats
            'window' => 60
        ]
    ];

    /**
     * Aplica rate limiting basado en IP y endpoint
     */
    public static function apply($endpointType = 'api') {
        $clientIP = self::getClientIP();
        $key = "rate_limit_{$endpointType}_{$clientIP}";
        
        $limit = self::$limits[$endpointType] ?? self::$limits['api'];
        
        // Verificar si existe el registro
        $current = self::getCurrentAttempts($key);
        
        if ($current['attempts'] >= $limit['attempts']) {
            // Verificar si la ventana de tiempo ha expirado
            if (time() - $current['first_attempt'] < $limit['window']) {
                Logger::warn("Rate limit excedido - IP: $clientIP, Endpoint: $endpointType");
                self::sendRateLimitResponse($limit['window']);
            } else {
                // Reiniciar contador si la ventana expiró
                self::resetAttempts($key);
            }
        }
        
        // Incrementar intentos
        self::incrementAttempts($key);
    }
    
    private static function getClientIP() {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return is_array($ip) ? $ip[0] : $ip;
    }
    
    private static function getCurrentAttempts($key) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $data = $_SESSION[$key] ?? ['attempts' => 0, 'first_attempt' => time()];
        
        // Si pasó más de 1 minuto, reiniciar
        if (time() - $data['first_attempt'] > 60) {
            $data = ['attempts' => 0, 'first_attempt' => time()];
        }
        
        return $data;
    }
    
    private static function incrementAttempts($key) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $data = $_SESSION[$key] ?? ['attempts' => 0, 'first_attempt' => time()];
        $data['attempts']++;
        $_SESSION[$key] = $data;
    }
    
    private static function resetAttempts($key) {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION[$key] = ['attempts' => 1, 'first_attempt' => time()];
    }
    
    private static function sendRateLimitResponse($window) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Límite de solicitudes excedido',
            'message' => 'Demasiadas solicitudes. Por favor intenta nuevamente en ' . $window . ' segundos.',
            'retry_after' => $window
        ]);
        exit;
    }
}