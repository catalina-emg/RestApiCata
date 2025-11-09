<?php
// middleware/CorsMiddleware.php

class CorsMiddleware {
    
    /**
     * Configuración de CORS para la API
     */
    public static function handle() {
        // Lista de dominios permitidos (puedes agregar más)
        $allowedOrigins = [
            'http://localhost:3000',
            'http://localhost:8080',
            'http://127.0.0.1:3000',
            'http://127.0.0.1:8080',
            'http://localhost',
            'https://catalina-emg.github.io'
        ];
        
        // Obtener el origen de la solicitud
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Verificar si el origen está permitido
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        } else {
            // Si no está en la lista, permitir cualquier origen (solo para desarrollo)
            header("Access-Control-Allow-Origin: *");
        }
        
        // Headers CORS esenciales
        header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Authorization, Content-Type, X-Requested-With, Origin, Accept");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 86400"); // 24 horas
        
        // Headers de seguridad adicionales
        header("X-Content-Type-Options: nosniff");
        header("X-Frame-Options: DENY");
        header("X-XSS-Protection: 1; mode=block");
        
        // Manejar solicitudes preflight OPTIONS
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * Método simplificado para APIs REST
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
     * CORS para desarrollo local
     */
    public static function developmentCors() {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
        header("Access-Control-Allow-Origin: $origin");
        header("Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: *");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 3600");
        
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    
    /**
     * CORS estricto para producción
     */
    public static function productionCors() {
        $allowedOrigins = [
            'https://tudominio.com',
            'https://www.tudominio.com'
        ];
        
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (in_array($origin, $allowedOrigins)) {
            header("Access-Control-Allow-Origin: $origin");
        }
        
        header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE");
        header("Access-Control-Allow-Headers: Authorization, Content-Type");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Max-Age: 3600");
    }
    
    /**
     * Validar origen de la solicitud
     */
    public static function validateOrigin($origin) {
        $allowedPatterns = [
            '/^https?:\/\/localhost(:[0-9]+)?$/',
            '/^https?:\/\/127\.0\.0\.1(:[0-9]+)?$/',
            '/^https?:\/\/catalina-emg\.github\.io$/'
        ];
        
        foreach ($allowedPatterns as $pattern) {
            if (preg_match($pattern, $origin)) {
                return true;
            }
        }
        
        return false;
    }
}