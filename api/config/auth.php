<?php
// config/auth.php

/**
 * Configuración de Autenticación y Seguridad
 * Configuración centralizada para JWT, sesiones y seguridad
 */

class AuthConfig {
    
    // ==================== CONFIGURACIÓN JWT ====================
    const JWT_SECRET = 'catalina_api_rest_secret_key_2024';
    const JWT_ALGORITHM = 'HS256';
    const JWT_EXPIRE_HOURS = 24; // Token expira en 24 horas
    
    // ==================== CONFIGURACIÓN SESIONES ====================
    const SESSION_TIMEOUT = 3600; // 1 hora en segundos
    const TOKEN_LENGTH = 32; // Longitud del token de sesión en bytes
    
    // ==================== CONFIGURACIÓN SEGURIDAD ====================
    const PASSWORD_MIN_LENGTH = 6;
    const PASSWORD_BCRYPT_COST = 12;
    const MAX_LOGIN_ATTEMPTS = 5;
    const LOCKOUT_TIME = 900; // 15 minutos en segundos
    
    // ==================== ROLES DEL SISTEMA ====================
    const ROLES = [
        'administrador' => [
            'level' => 100,
            'permissions' => ['*']
        ],
        'usuario' => [
            'level' => 50,
            'permissions' => ['read', 'write_own']
        ],
        'invitado' => [
            'level' => 10,
            'permissions' => ['read']
        ]
    ];
    
    // ==================== ENDPOINTS PÚBLICOS ====================
    const PUBLIC_ENDPOINTS = [
        'POST:/auth/login',
        'POST:/auth/register', 
        'GET:/auth/verify',
        'GET:/stats',
        'OPTIONS:/'
    ];
    
    // ==================== ENDPOINTS SOLO ADMIN ====================
    const ADMIN_ENDPOINTS = [
        'DELETE:/usuarios',
        'GET:/admin/stats',
        'POST:/admin/users'
    ];
    
    // ==================== MÉTODOS DE VALIDACIÓN ====================
    
    /**
     * Verificar si un endpoint es público
     */
    public static function isPublicEndpoint($method, $path) {
        $endpoint = "$method:$path";
        return in_array($endpoint, self::PUBLIC_ENDPOINTS);
    }
    
    /**
     * Verificar si un endpoint requiere admin
     */
    public static function requiresAdmin($method, $path) {
        $endpoint = "$method:$path";
        return in_array($endpoint, self::ADMIN_ENDPOINTS);
    }
    
    /**
     * Verificar fortaleza de contraseña
     */
    public static function validatePasswordStrength($password) {
        if (strlen($password) < self::PASSWORD_MIN_LENGTH) {
            return [
                'valid' => false,
                'message' => "La contraseña debe tener al menos " . self::PASSWORD_MIN_LENGTH . " caracteres"
            ];
        }
        
        // Puedes agregar más validaciones aquí
        // - Mayúsculas y minúsculas
        // - Números
        // - Caracteres especiales
        
        return ['valid' => true, 'message' => 'Contraseña válida'];
    }
    
    /**
     * Generar hash de contraseña
     */
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT, ['cost' => self::PASSWORD_BCRYPT_COST]);
    }
    
    /**
     * Verificar contraseña
     */
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    /**
     * Generar token seguro
     */
    public static function generateToken() {
        return bin2hex(random_bytes(self::TOKEN_LENGTH));
    }
    
    /**
     * Obtener tiempo de expiración
     */
    public static function getExpirationTime() {
        return time() + (self::JWT_EXPIRE_HOURS * 3600);
    }
    
    /**
     * Verificar nivel de rol
     */
    public static function checkRolePermission($userRole, $requiredRole) {
        $userLevel = self::ROLES[$userRole]['level'] ?? 0;
        $requiredLevel = self::ROLES[$requiredRole]['level'] ?? 0;
        
        return $userLevel >= $requiredLevel;
    }
    
    /**
     * Obtener configuración para respuestas
     */
    public static function getConfig() {
        return [
            'jwt' => [
                'algorithm' => self::JWT_ALGORITHM,
                'expire_hours' => self::JWT_EXPIRE_HOURS
            ],
            'security' => [
                'password_min_length' => self::PASSWORD_MIN_LENGTH,
                'max_login_attempts' => self::MAX_LOGIN_ATTEMPTS,
                'session_timeout' => self::SESSION_TIMEOUT
            ],
            'roles' => array_keys(self::ROLES)
        ];
    }
}

// Configuración de tiempo de sesión PHP
ini_set('session.gc_maxlifetime', AuthConfig::SESSION_TIMEOUT);
session_set_cookie_params(AuthConfig::SESSION_TIMEOUT);