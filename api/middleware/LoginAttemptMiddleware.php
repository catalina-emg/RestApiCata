<?php
// api/middleware/LoginAttemptMiddleware.php

require_once __DIR__ . '/../config/logger.php';

class LoginAttemptMiddleware {
    
    private static $maxAttempts = 5;
    private static $blockTime = 60; // 60 segundos = 1 minuto
    
    /**
     * Verifica si la IP está bloqueada por muchos intentos fallidos
     */
    public static function checkLoginAttempts() {
        $clientIP = self::getClientIP();
        $key = "login_attempts_{$clientIP}";
        
        $attempts = self::getLoginAttempts($key);
        
        // Si excedió los intentos y aún está en tiempo de bloqueo
        if ($attempts['count'] >= self::$maxAttempts && 
            (time() - $attempts['first_attempt']) < self::$blockTime) {
            
            $remainingTime = self::$blockTime - (time() - $attempts['first_attempt']);
            Logger::warn("Login bloqueado - IP: $clientIP - Tiempo restante: {$remainingTime}s");
            self::sendBlockedResponse($remainingTime);
        }
        
        // Si el tiempo de bloqueo expiró, reiniciar contador
        if ((time() - $attempts['first_attempt']) >= self::$blockTime) {
            self::resetLoginAttempts($key);
        }
    }
    
    /**
     * Registra un intento fallido de login
     */
    public static function recordFailedAttempt() {
        $clientIP = self::getClientIP();
        $key = "login_attempts_{$clientIP}";
        
        $attempts = self::getLoginAttempts($key);
        $attempts['count']++;
        
        // Si es el primer intento, guardar timestamp
        if ($attempts['count'] === 1) {
            $attempts['first_attempt'] = time();
        }
        
        self::saveLoginAttempts($key, $attempts);
        
        $remainingAttempts = self::$maxAttempts - $attempts['count'];
        Logger::warn("Intento fallido de login - IP: $clientIP - Intentos restantes: $remainingAttempts");
        
        // Si excedió el límite, bloquear
        if ($attempts['count'] >= self::$maxAttempts) {
            $blockTime = self::$blockTime;
            Logger::warn("Usuario bloqueado por 1 minuto - IP: $clientIP");
            self::sendBlockedResponse($blockTime);
        }
    }
    
    /**
     * Reinicia los intentos cuando el login es exitoso
     */
    public static function resetOnSuccess() {
        $clientIP = self::getClientIP();
        $key = "login_attempts_{$clientIP}";
        self::resetLoginAttempts($key);
        Logger::info("Intentos de login reiniciados - IP: $clientIP");
    }
    
    private static function getClientIP() {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        return is_array($ip) ? $ip[0] : $ip;
    }
    
    private static function getLoginAttempts($key) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $data = $_SESSION[$key] ?? ['count' => 0, 'first_attempt' => time()];
    return $data;
    }
    
    private static function saveLoginAttempts($key, $data) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION[$key] = $data;
    }
    
    private static function resetLoginAttempts($key) {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
    $_SESSION[$key] = ['count' => 0, 'first_attempt' => time()];
    }
    
    private static function sendBlockedResponse($remainingTime) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Cuenta temporalmente bloqueada',
            'message' => "Demasiados intentos fallidos. Por favor espera $remainingTime segundos.",
            'blocked' => true,
            'retry_after' => $remainingTime
        ]);
        exit;
    }
    
    /**
     * Obtiene información del estado de bloqueo (para el frontend)
     */
    public static function getBlockStatus() {
        $clientIP = self::getClientIP();
        $key = "login_attempts_{$clientIP}";
        
        $attempts = self::getLoginAttempts($key);
        
        if ($attempts['count'] >= self::$maxAttempts && 
            (time() - $attempts['first_attempt']) < self::$blockTime) {
            
            $remainingTime = self::$blockTime - (time() - $attempts['first_attempt']);
            return [
                'blocked' => true,
                'remaining_time' => $remainingTime,
                'message' => "Cuenta bloqueada. Espera $remainingTime segundos."
            ];
        }
        
        return [
            'blocked' => false,
            'remaining_attempts' => self::$maxAttempts - $attempts['count'],
            'message' => 'Puedes intentar iniciar sesión'
        ];
    }
}