<?php
// api/routes.php

// TODOS los archivos están dentro de api/
require_once __DIR__ . '/controllers/UsuariosController.php';
require_once __DIR__ . '/config/logger.php';
require_once __DIR__ . '/controllers/StatsController.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = preg_replace('#^.*/api/#', '', $uri);
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

$controller = new UsuariosController();

// Mapear alias: aceptar tanto /usuarios como /alumnos
$aliases = [
    'alumnos' => 'usuarios'
];

$resource = $aliases[$uri] ?? $uri;

Logger::info("Request: $method /$uri -> resolved to resource '$resource'");

switch (true) {
    case $resource === 'usuarios' && $method === 'GET':
        $controller->getAll();
        break;

    case $resource === 'stats' && $method === 'GET':
        StatsController::handler();
        break;

    case $resource === 'usuarios' && $method === 'POST':
        $controller->create();
        break;

    case $resource === 'logevent' && $method === 'POST':
        $input = json_decode(file_get_contents('php://input'), true);
        $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
        if ($nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
            http_response_code(400);
            if (function_exists('log_event')) {
                log_event("Intento de log_event invalido: $nombre", "WARN");
            }
            echo json_encode(["error" => "Nombre invalido"]);
        } else {
            if (function_exists('log_event')) {
                log_event("Evento usuario: " . json_encode($input), "INFO");
            }
            echo json_encode(["success" => true]);
        }
        break;

    case $resource === 'usuarios' && $method === 'PATCH':
        $controller->update();
        break;

    case $resource === 'usuarios' && $method === 'DELETE':
        $controller->delete();
        break;

    default:
        http_response_code(404);
        echo json_encode(["error" => "Ruta no encontrada", "ruta" => $uri]);
        break;
}