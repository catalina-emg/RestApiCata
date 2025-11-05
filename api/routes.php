<?php
// api/routes.php

// Cargar todos los controladores y middleware
require_once __DIR__ . '/controllers/UsuariosController.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/StatsController.php';
require_once __DIR__ . '/middleware/AuthMiddleware.php';
require_once __DIR__ . '/config/logger.php';

// Configurar headers CORS básicos
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Manejar preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = preg_replace('#^.*/api/#', '', $uri);
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

// Mapear alias: aceptar tanto /usuarios como /estudiantes
$aliases = [
    'estudiantes' => 'usuarios'
];

$resource = $aliases[$uri] ?? $uri;

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

        // ==================== RUTAS PROTEGIDAS ====================
        case $resource === 'usuarios' && $method === 'GET':
            // Proteger con autenticación
            AuthMiddleware::authenticate();
            $controller = new UsuariosController();
            $controller->getAll();
            break;

        case $resource === 'usuarios' && $method === 'POST':
            // Proteger con autenticación
            AuthMiddleware::authenticate();
            $controller = new UsuariosController();
            $controller->create();
            break;

        case $resource === 'usuarios' && $method === 'PATCH':
            // Proteger con autenticación
            AuthMiddleware::authenticate();
            $controller = new UsuariosController();
            $controller->update();
            break;

        case $resource === 'usuarios' && $method === 'DELETE':
            // Proteger con autenticación - Solo admin
            AuthMiddleware::requireAdmin();
            $controller = new UsuariosController();
            $controller->delete();
            break;

        case $resource === 'auth/profile' && $method === 'GET':
            $authController = new AuthController();
            $authController->profile();
            break;

        case $resource === 'auth/logout' && $method === 'POST':
            $authController = new AuthController();
            $authController->logout();
            break;

        case $resource === 'logevent' && $method === 'POST':
            // Proteger logevent con autenticación
            AuthMiddleware::authenticate();
            $input = json_decode(file_get_contents('php://input'), true);
            $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
            if ($nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
                http_response_code(400);
                Logger::warn("Intento de log_event invalido: $nombre");
                echo json_encode(["error" => "Nombre invalido"]);
            } else {
                Logger::info("Evento usuario: " . json_encode($input));
                echo json_encode(["success" => true]);
            }
            break;

        default:
            http_response_code(404);
            echo json_encode([
                "error" => "Ruta no encontrada", 
                "ruta" => $uri,
                "method" => $method
            ]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    Logger::error("Error en routes: " . $e->getMessage());
    echo json_encode([
        "error" => "Error interno del servidor",
        "message" => $e->getMessage()
    ]);
}