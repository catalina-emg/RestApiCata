<?php
// api/routes.php

// Aplicar CORS PRIMERO, antes de cualquier output
require_once __DIR__ . '/middleware/CorsMiddleware.php';
CorsMiddleware::simpleCors();

// Ahora cargar el resto
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/logger.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/config/cors.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/controllers/UsuariosController.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/StatsController.php';
require_once __DIR__ . '/controllers/ProfileController.php';

// Configurar CORS usando la configuración centralizada
CorsConfig::simpleCors(); // Para desarrollo - usar applyCorsHeaders() en producción

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = preg_replace('#^.*/api/#', '', $uri);
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Mapear alias: aceptar tanto /usuarios como /estudiantes
$aliases = [
    'estudiantes' => 'usuarios'
];

$resource = $aliases[$uri] ?? $uri;

// Detectar si es una ruta /usuarios/{id}
$isUserById = preg_match('#^usuarios/(\d+)$#', $uri, $matches);
$userId = $isUserById ? $matches[1] : null;

Logger::info("Request: $method /$uri -> resolved to resource '$resource'");

try {
    switch (true) {
        // ==================== RUTAS PÚBLICAS ====================
        case $resource === 'auth/register' && $method === 'POST':
            $authController = new AuthController();
            $authController->register();
            break;

        case $resource === 'auth/login' && $method === 'POST':
            $authController = new AuthController();
            $authController->login();
            break;

        case $resource === 'auth/verify' && $method === 'GET':
            $authController = new AuthController();
            $authController->verify();
            break;

        case $resource === 'stats' && $method === 'GET':
            StatsController::handler();
            break;

        // ==================== RUTAS DE PERFIL ====================
        case $resource === 'profile' && $method === 'GET':
            // GET /profile - Obtener perfil del usuario autenticado
            $profileController = new ProfileController();
            $profileController->getProfile();
            break;

        case $resource === 'profile' && $method === 'PATCH':
            // PATCH /profile - Actualizar perfil
            $profileController = new ProfileController();
            $profileController->updateProfile();
            break;

        case $resource === 'profile/change-password' && $method === 'POST':
            // POST /profile/change-password - Cambiar contraseña
            $profileController = new ProfileController();
            $profileController->changePassword();
            break;

        case $resource === 'profile/stats' && $method === 'GET':
            // GET /profile/stats - Estadísticas del usuario
            $profileController = new ProfileController();
            $profileController->getUserStats();
            break;

        // ==================== RUTAS DE USUARIOS (PROTEGIDAS) ====================
        case $resource === 'usuarios' && $method === 'GET' && !$userId:
            // GET /usuarios - Listar todos
            AuthMiddleware::authenticate();
            $controller = new UsuariosController();
            $controller->getAll();
            break;

        case $isUserById && $method === 'GET':
            // GET /usuarios/{id} - Obtener usuario específico
            AuthMiddleware::authenticate();
            $controller = new UsuariosController();
            $controller->getById($userId);
            break;

        case $resource === 'usuarios' && $method === 'POST':
            // POST /usuarios - Crear usuario
            AuthMiddleware::authenticate();
            $controller = new UsuariosController();
            $controller->create();
            break;

        case $resource === 'usuarios' && $method === 'PATCH':
            // PATCH /usuarios - Actualizar usuario
            AuthMiddleware::authenticate();
            $controller = new UsuariosController();
            $controller->update();
            break;

        case $resource === 'usuarios' && $method === 'DELETE':
            // DELETE /usuarios - Eliminar usuario (solo admin)
            AuthMiddleware::requireAdmin();
            $controller = new UsuariosController();
            $controller->delete();
            break;

        // ==================== RUTAS DE AUTENTICACIÓN ====================
        case $resource === 'auth/profile' && $method === 'GET':
            // GET /auth/profile - Perfil del usuario autenticado (alternativa)
            $authController = new AuthController();
            $authController->profile();
            break;

        case $resource === 'auth/logout' && $method === 'POST':
            // POST /auth/logout - Cerrar sesión
            $authController = new AuthController();
            $authController->logout();
            break;

        // ==================== RUTAS DE LOGS ====================
        case $resource === 'logevent' && $method === 'POST':
            // POST /logevent - Registrar evento
            AuthMiddleware::authenticate();
            $input = json_decode(file_get_contents('php://input'), true);
            $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
            if ($nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
                http_response_code(400);
                Logger::warn("Intento de log_event invalido: $nombre");
                echo json_encode([
                    "success" => false,
                    "error" => "Nombre invalido"
                ]);
            } else {
                Logger::info("Evento usuario: " . json_encode($input));
                echo json_encode([
                    "success" => true,
                    "message" => "Evento registrado correctamente"
                ]);
            }
            break;

        // ==================== RUTAS DE CONFIGURACIÓN ====================
        case $resource === 'config/auth' && $method === 'GET':
            // GET /config/auth - Obtener configuración de autenticación (solo admin)
            AuthMiddleware::requireAdmin();
            echo json_encode([
                'success' => true,
                'config' => AuthConfig::getConfig()
            ]);
            break;

        case $resource === 'config/cors' && $method === 'GET':
            // GET /config/cors - Obtener configuración CORS (solo admin)
            AuthMiddleware::requireAdmin();
            echo json_encode([
                'success' => true,
                'config' => CorsConfig::getConfig()
            ]);
            break;

        default:
            http_response_code(404);
            echo json_encode([
                "success" => false,
                "error" => "Ruta no encontrada", 
                "ruta" => $uri,
                "method" => $method,
                "available_routes" => [
                    "PUBLIC: POST /auth/register",
                    "PUBLIC: POST /auth/login", 
                    "PUBLIC: GET /auth/verify",
                    "PUBLIC: GET /stats",
                    "PROTECTED: GET /profile",
                    "PROTECTED: PATCH /profile", 
                    "PROTECTED: POST /profile/change-password",
                    "PROTECTED: GET /profile/stats",
                    "PROTECTED: GET /usuarios",
                    "PROTECTED: GET /usuarios/{id}",
                    "PROTECTED: POST /usuarios",
                    "PROTECTED: PATCH /usuarios", 
                    "ADMIN ONLY: DELETE /usuarios",
                    "ADMIN ONLY: GET /config/auth",
                    "ADMIN ONLY: GET /config/cors",
                    "PROTECTED: GET /auth/profile",
                    "PROTECTED: POST /auth/logout",
                    "PROTECTED: POST /logevent"
                ]
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    Logger::error("Error en routes: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "error" => "Error interno del servidor",
        "message" => $e->getMessage()
    ]);
}