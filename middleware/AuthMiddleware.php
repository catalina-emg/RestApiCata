<?php
// middleware/AuthMiddleware.php

require_once __DIR__ . '/../models/Usuarios.php';
require_once __DIR__ . '/../config/logger.php';

class AuthMiddleware {
    
    /**
     * Verifica si el usuario está autenticado mediante token de sesión
     * @return array Datos del usuario si está autenticado
     */
    public static function authenticate() {
        // Obtener el token del header Authorization
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        
        // Extraer el token (formato: "Bearer token" o solo "token")
        $token = self::extractToken($authHeader);
        
        if (!$token) {
            Logger::warn("Intento de acceso sin token de autenticación");
            self::sendUnauthorized("Token de autenticación requerido");
        }
        
        // Validar el token en la base de datos
        $userModel = new Usuarios();
        $user = $userModel->getUserBySessionToken($token);
        
        if (!$user) {
            Logger::warn("Intento de acceso con token inválido: $token");
            self::sendUnauthorized("Token inválido o sesión expirada");
        }
        
        // Verificar si el usuario está activo
        if (!$user['is_active']) {
            Logger::warn("Intento de acceso de usuario inactivo: " . $user['email']);
            self::sendUnauthorized("Cuenta desactivada");
        }
        
        // Actualizar último acceso
        $userModel->updateLastAccess($user['id']);
        
        Logger::info("Acceso autorizado para usuario: " . $user['email']);
        return $user;
    }
    
    /**
     * Extrae el token del header Authorization
     */
    private static function extractToken($authHeader) {
        if (empty($authHeader)) {
            return null;
        }
        
        // Si viene como "Bearer token"
        if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return trim($matches[1]);
        }
        
        // Si viene solo el token
        return trim($authHeader);
    }
    
    /**
     * Envía respuesta de no autorizado
     */
    private static function sendUnauthorized($message) {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'No autorizado',
            'message' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        exit;
    }
    
    /**
     * Middleware para verificar rol de administrador
     */
    public static function requireAdmin() {
        $user = self::authenticate();
        
        // Verificar si el usuario es administrador
        if ($user['rol'] !== 'administrador') {
            Logger::warn("Intento de acceso a recurso admin por usuario no autorizado: " . $user['email']);
            http_response_code(403);
            echo json_encode([
                'success' => false,
                'error' => 'Acceso denegado',
                'message' => 'Se requieren privilegios de administrador'
            ]);
            exit;
        }
        
        return $user;
    }
    
    /**
     * Middleware opcional - No bloquea pero proporciona info del usuario si está autenticado
     */
    public static function optionalAuth() {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        $token = self::extractToken($authHeader);
        
        if ($token) {
            $userModel = new Usuarios();
            $user = $userModel->getUserBySessionToken($token);
            
            if ($user && $user['is_active']) {
                $userModel->updateLastAccess($user['id']);
                return $user;
            }
        }
        
        return null;
    }
}