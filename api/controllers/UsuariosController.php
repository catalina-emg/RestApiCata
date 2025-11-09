<?php
// controllers/UsuariosController.php
require_once __DIR__ . '/../models/Usuarios.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class UsuariosController {
    private $model;

    public function __construct() {
        $this->model = new Usuarios();
    }

    /**
     * Obtener todos los usuarios (requiere autenticación)
     */
    public function getAll() {
        try {
            // Verificar autenticación
            $currentUser = AuthMiddleware::authenticate();
            Logger::info("GET /usuarios - Usuario: " . $currentUser['email']);
            
            $result = $this->model->getAll();
            echo json_encode([
                'success' => true,
                'data' => $result,
                'count' => count($result),
                'requested_by' => $currentUser['email'],
                'user_role' => $currentUser['rol'] // ← NUEVO: enviar rol al frontend
            ]);
        } catch (Exception $e) {
            Logger::error("Error en GET /usuarios: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener usuarios'
            ]);
        }
    }

    /**
     * Crear usuario (requiere rol de administrador)
     */
    public function create() {
        try {
            // Verificar que sea administrador
            $currentUser = AuthMiddleware::requireAdmin();
            
            $input = json_decode(file_get_contents("php://input"), true);
            Logger::info('POST /usuarios - Admin: ' . $currentUser['email'] . ' - Payload: ' . json_encode($input));

            // Validación del nombre
            $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
            if ($nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
                http_response_code(400);
                Logger::warn("Intento de insercion invalida por usuario: " . $currentUser['email'] . " - Nombre: $nombre");
                echo json_encode([
                    "success" => false,
                    "error" => "Nombre invalido"
                ]);
                return;
            }

            $res = $this->model->create($input);
            Logger::info('POST /usuarios result: ' . json_encode($res));
            
            if (isset($res['success']) && $res['success'] === false) {
                http_response_code(500);
                echo json_encode($res);
                return;
            }
            
            // Respuesta exitosa
            echo json_encode([
                'success' => true,
                'message' => 'Usuario creado exitosamente',
                'id' => $res['id'],
                'created_by' => $currentUser['email']
            ]);
            
        } catch (Exception $e) {
            Logger::error("Error en POST /usuarios: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al crear usuario: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Actualizar usuario (requiere rol de administrador)
     */
    public function update() {
        try {
            // Verificar que sea administrador
            $currentUser = AuthMiddleware::requireAdmin();
            
            $input = json_decode(file_get_contents("php://input"), true);
            Logger::info('PATCH /usuarios - Admin: ' . $currentUser['email'] . ' - Payload: ' . json_encode($input));
            
            // Validar que tenga ID
            if (!isset($input['id'])) {
                http_response_code(400);
                echo json_encode([
                    "success" => false, 
                    "error" => "Falta el campo 'id' para actualizar"
                ]);
                return;
            }

            // Si se proporciona 'nombre' en el payload, validarlo
            if (isset($input['nombre'])) {
                $nombre = trim($input['nombre']);
                if ($nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
                    http_response_code(400);
                    Logger::warn("Intento de actualizacion invalida por usuario: " . $currentUser['email'] . " - Nombre: $nombre");
                    echo json_encode([
                        "success" => false,
                        "error" => "Nombre invalido"
                    ]);
                    return;
                }
                $input['nombre'] = $nombre;
            }

            $res = $this->model->update($input);
            Logger::info('PATCH /usuarios result: ' . json_encode($res));
            
            if (isset($res['success']) && $res['success'] === false) {
                http_response_code(400);
                echo json_encode($res);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuario actualizado exitosamente',
                'updated_by' => $currentUser['email']
            ]);
            
        } catch (Exception $e) {
            Logger::error("Error en PATCH /usuarios: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al actualizar usuario: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Eliminar usuario (requiere rol de administrador)
     */
    public function delete() {
        try {
            // Verificar que sea administrador
            $currentUser = AuthMiddleware::requireAdmin();
            
            $input = json_decode(file_get_contents("php://input"), true);
            Logger::info('DELETE /usuarios - Admin: ' . $currentUser['email'] . ' - Payload: ' . json_encode($input));
            
            if (!isset($input['id'])) {
                http_response_code(400);
                $err = [
                    "success" => false, 
                    "error" => "Falta el campo 'id'"
                ];
                Logger::warn('DELETE /usuarios faltó id en payload');
                echo json_encode($err);
                return;
            }

            $res = $this->model->delete($input['id']);
            Logger::info('DELETE /usuarios result: ' . json_encode($res));
            
            if (isset($res['success']) && $res['success'] === false) {
                http_response_code(400);
                echo json_encode($res);
                return;
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Usuario eliminado exitosamente (soft delete)',
                'deleted_by' => $currentUser['email']
            ]);
            
        } catch (Exception $e) {
            Logger::error("Error en DELETE /usuarios: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al eliminar usuario: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Obtener usuario por ID (requiere autenticación)
     */
    public function getById($id) {
        try {
            // Verificar autenticación
            $currentUser = AuthMiddleware::authenticate();
            
            Logger::info("GET /usuarios/$id - Usuario: " . $currentUser['email']);
            
            $user = $this->model->getById($id);
            if ($user) {
                echo json_encode([
                    'success' => true,
                    'data' => $user,
                    'requested_by' => $currentUser['email'],
                    'user_role' => $currentUser['rol'] // ← NUEVO: enviar rol al frontend
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Usuario no encontrado'
                ]);
            }
        } catch (Exception $e) {
            Logger::error("Error en GET /usuarios/$id: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener usuario'
            ]);
        }
    }
}