<?php
// config/cors.php

/**
 * Configuración CORS (Cross-Origin Resource Sharing)
 * Control de acceso desde diferentes dominios
 */

class CorsConfig {
    
    // ==================== CONFIGURACIÓN DOMINIOS PERMITIDOS ====================
    const ALLOWED_ORIGINS = [
        'http://localhost:3000',
        'http://localhost:8080',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:8080',
        'http://localhost',
        'https://localhost',
        'https://catalina-emg.github.io',
        'https://tu-dominio-production.com'
    ];
    
    // ==================== MÉTODOS HTTP PERMITIDOS ====================
    const ALLOWED_METHODS = [
        'GET',
        'POST', 
        'PUT',
        'PATCH',
        'DELETE',
        'OPTIONS'
    ];
    
    // ==================== HEADERS PERMITIDOS ====================
    const ALLOWED_HEADERS = [
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'Origin',
        'Accept',
        'X-CSRF-Token',
        'X-API-Key'
    ];
    
    // ==================== HEADERS EXPUESTOS ====================
    const EXPOSED_HEADERS = [
        'X-Total-Count',
        'X-Pagination-Count',
        'X-RateLimit-Limit',
        'X-RateLimit-Remaining'
    ];
    
    // ==================== CONFIGURACIÓN CACHE ====================
    const MAX_AGE = 86400; // 24 horas en segundos
    const ALLOW_CREDENTIALS = true;
    
    // ==================== CONFIGURACIÓN SEGURIDAD ====================
    const STRICT_TRANSPORT_SECURITY = 'max-age=31536000; includeSubDomains';
    const CONTENT_SECURITY_POLICY = "default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'";
    
    // ==================== MÉTODOS DE CONFIGURACIÓN ====================
    
    /**
     * Aplicar configuración CORS completa
     */
    public static function applyCorsHeaders() {
        $origin = self::getAllowedOrigin();
        
        // Headers CORS básicos
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: " . implode(', ', self::ALLOWED_METHODS));
        header("Access-Control-Allow-Headers: " . implode(', ', self::ALLOWED_HEADERS));
        header("Access-Control-Expose-Headers: " . implode(', ', self::EXPOSED_HEADERS));
        header("Access-Control-Max-Age: " . self::MAX_AGE);
        
        if (self::ALLOW_CREDENTIALS) {
            header("Access-Control-Allow-Credentials: true");
        }
        
        // Headers de seguridad
        self::applySecurityHeaders();
        
        // Manejar preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * Obtener origen permitido
     */
    public static function getAllowedOrigin() {
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // En desarrollo, permitir cualquier origen
        if (self::isDevelopment()) {
            return $requestOrigin ?: '*';
        }
        
        // En producción, verificar contra la lista blanca
        if (in_array($requestOrigin, self::ALLOWED_ORIGINS)) {
            return $requestOrigin;
        }
        
        // Si no está en la lista, no permitir CORS
        return '';
    }
    
    /**
     * Aplicar headers de seguridad
     */
    public static function applySecurityHeaders() {
        // Prevenir clickjacking
        header("X-Frame-Options: DENY");
        
        // Prevenir MIME type sniffing
        header("X-Content-Type-Options: nosniff");
        
        // XSS Protection
        header("X-XSS-Protection: 1; mode=block");
        
        // Referrer Policy
        header("Referrer-Policy: strict-origin-when-cross-origin");
        
        // HSTS (solo en HTTPS)
        if (self::isHttps()) {
            header("Strict-Transport-Security: " . self::STRICT_TRANSPORT_SECURITY);
        }
        
        // Content Security Policy
        // header("Content-Security-Policy: " . self::CONTENT_SECURITY_POLICY);
    }
    
    /**
     * Configuración CORS simplificada para APIs
     */
    public static function simpleCors() {
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Authorization, Content-Type");
        header("Content-Type: application/json; charset=UTF-8");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * Verificar si es entorno de desarrollo
     */
    public static function isDevelopment() {
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        return in_array($serverName, ['localhost', '127.0.0.1']) || strpos($serverName, '.local') !== false;
    }
    
    /**
     * Verificar si es HTTPS
     */
    public static function isHttps() {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') 
            || ($_SERVER['SERVER_PORT'] ?? '') == 443
            || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
    
    /**
     * Obtener configuración para el cliente
     */
    public static function getConfig() {
        return [
            'allowed_origins' => self::isDevelopment() ? ['*'] : self::ALLOWED_ORIGINS,
            'allowed_methods' => self::ALLOWED_METHODS,
            'allowed_headers' => self::ALLOWED_HEADERS,
            'max_age' => self::MAX_AGE,
            'allow_credentials' => self::ALLOW_CREDENTIALS
        ];
    }
}

// Aplicar CORS automáticamente si este archivo se incluye directamente
if (basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    CorsConfig::applyCorsHeaders();
}