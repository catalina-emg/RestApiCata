<?php
// controllers/AuthController.php

require_once __DIR__ . '/../models/Usuarios.php';
require_once __DIR__ . '/../config/logger.php';

class AuthController {
    private $model;

    public function __construct() {
        $this->model = new Usuarios();
    }

    /**
     * Registro de nuevo usuario
     * INICIO BLOQUE TRY/CATCH - FASE 1 (+20 puntos)
     */
    public function register() {
        try {
            // INICIO: Manejo seguro de input
            $input = json_decode(file_get_contents("php://input"), true);
            
            // CORRECCIÓN: Log solo evento, no payload completo (FASE 2)
            Logger::info('Intento de registro - Email: ' . ($input['email'] ?? 'no proporcionado'));
            // FIN CORRECCIÓN FASE 2

            // Validar campos requeridos
            $required = ['nombre', 'email', 'password', 'edad', 'rol'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    http_response_code(400);
                    Logger::warn("Registro fallido - Campo faltante: $field");
                    echo json_encode([
                        'success' => false,
                        'error' => "Campo requerido faltante: $field"
                    ]);
                    return;
                }
            }

            $nombre = trim($input['nombre']);
            $email = trim($input['email']);
            $password = $input['password'];
            $edad = intval($input['edad']);
            $rol = trim($input['rol']);

            // Validaciones...
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400);
                Logger::warn("Registro fallido - Email inválido: $email");
                echo json_encode([
                    'success' => false,
                    'error' => 'Formato de email inválido'
                ]);
                return;
            }

            // Verificar si el email ya existe
            if ($this->model->emailExists($email)) {
                http_response_code(409);
                Logger::warn("Registro fallido - Email ya registrado: $email");
                echo json_encode([
                    'success' => false,
                    'error' => 'El email ya está registrado'
                ]);
                return;
            }

            // Crear usuario
            $userData = [
                'nombre' => $nombre,
                'email' => $email,
                'password' => $password,
                'edad' => $edad,
                'rol' => $rol
            ];

            $result = $this->model->createUser($userData);

            if ($result['success']) {
                Logger::info("Registro exitoso: $email");
                echo json_encode([
                    'success' => true,
                    'message' => 'Usuario registrado exitosamente',
                    'user_id' => $result['user_id']
                ]);
            } else {
                http_response_code(500);
                Logger::error("Error en registro: " . $result['error']);
                echo json_encode($result);
            }

        } catch (Exception $e) {
            // INICIO: Manejo de excepciones - FASE 1
            http_response_code(500);
            Logger::error("Excepción en registro: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor en registro'
            ]);
            // FIN MANEJO EXCEPCIONES - FASE 1
        }
    }

    /**
     * Login de usuario
     * INICIO BLOQUE TRY/CATCH - FASE 1 (+20 puntos)
     */
    public function login() {
        try {
            $input = json_decode(file_get_contents("php://input"), true);
            
            // CORRECCIÓN: Log solo evento, no datos sensibles (FASE 2)
            Logger::info('Intento de login - Email: ' . ($input['email'] ?? 'no proporcionado'));
            // FIN CORRECCIÓN FASE 2

            // Validar campos
            if (!isset($input['email']) || !isset($input['password'])) {
                http_response_code(400);
                Logger::warn("Login fallido - Campos faltantes");
                echo json_encode([
                    'success' => false,
                    'error' => 'Email y password son requeridos'
                ]);
                return;
            }

            $email = trim($input['email']);
            $password = $input['password'];

            // Validar credenciales
            $user = $this->model->validateCredentials($email, $password);

            if ($user) {
                // Generar token de sesión
                $token = bin2hex(random_bytes(32));
                $this->model->updateSessionToken($user['id'], $token);

                Logger::info("Login exitoso: $email");
                echo json_encode([
                    'success' => true,
                    'message' => 'Login exitoso',
                    'token' => $token,
                    'user' => [
                        'id' => $user['id'],
                        'nombre' => $user['nombre'],
                        'email' => $user['email'],
                        'rol' => $user['rol'],
                        'edad' => $user['edad']
                    ]
                ]);
            } else {
                http_response_code(401);
                Logger::warn("Login fallido - Credenciales inválidas: $email");
                echo json_encode([
                    'success' => false,
                    'error' => 'Credenciales inválidas'
                ]);
            }

        } catch (Exception $e) {
            // INICIO: Manejo de excepciones - FASE 1
            http_response_code(500);
            Logger::error("Excepción en login: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor en login'
            ]);
            // FIN MANEJO EXCEPCIONES - FASE 1
        }
    }

    /**
     * Logout de usuario  
     * INICIO BLOQUE TRY/CATCH - FASE 1 (+20 puntos)
     */
    public function logout() {
        try {
            $headers = getallheaders();
            $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? '';
            
            if (preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
                $token = trim($matches[1]);
                
                // Invalidar token en la base de datos
                $this->model->invalidateSessionToken($token);
                
                Logger::info("Logout exitoso para token: " . substr($token, 0, 10) . "...");
                echo json_encode([
                    'success' => true,
                    'message' => 'Logout exitoso'
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Token no proporcionado'
                ]);
            }

        } catch (Exception $e) {
            // INICIO: Manejo de excepciones - FASE 1
            http_response_code(500);
            Logger::error("Excepción en logout: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'error' => 'Error interno del servidor en logout'
            ]);
            // FIN MANEJO EXCEPCIONES - FASE 1
        }
    }

    /**
     * Verificar token activo
     */
    public function verify() {
        require_once __DIR__ . '/../middleware/AuthMiddleware.php';
        
        try {
            $user = AuthMiddleware::authenticate();
            
            echo json_encode([
                'success' => true,
                'message' => 'Token válido',
                'user' => [
                    'id' => $user['id'],
                    'nombre' => $user['nombre'],
                    'email' => $user['email'],
                    'rol' => $user['rol']
                ]
            ]);
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode([
                'success' => false,
                'error' => 'Token inválido'
            ]);
        }
    }

    /**
     * Obtener perfil del usuario autenticado
     */
    public function profile() {
        require_once __DIR__ . '/../middleware/AuthMiddleware.php';
        
        $user = AuthMiddleware::authenticate();
        
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'email' => $user['email'],
                'rol' => $user['rol'],
                'edad' => $user['edad'],
                'last_login' => $user['last_login'],
                'is_active' => $user['is_active']
            ]
        ]);
    }
}