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
     * INICIO BLOQUE TRY/CATCH - FASE 1 (+20 puntos)
     */
    public function getAll() {
        try {
            // Verificar autenticación
            $currentUser = AuthMiddleware::authenticate();
            
            // CORRECCIÓN: Log solo evento, no datos sensibles (FASE 2)
            Logger::info("GET /usuarios - Usuario: " . $currentUser['email']);
            // FIN CORRECCIÓN FASE 2
            
            $result = $this->model->getAll();
            echo json_encode([
                'success' => true,
                'data' => $result,
                'count' => count($result),
                'requested_by' => $currentUser['email'],
                'user_role' => $currentUser['rol']
            ]);
        } catch (Exception $e) {
            // INICIO: Manejo de excepciones - FASE 1
            Logger::error("Error en GET /usuarios: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener usuarios'
            ]);
            // FIN MANEJO EXCEPCIONES - FASE 1
        }
    }

    /**
     * Crear usuario (requiere rol de administrador)
     * INICIO BLOQUE TRY/CATCH - FASE 1 (+20 puntos)
     */
    public function create() {
        try {
            // Verificar que sea administrador - FASE 3 (+10 puntos)
            $currentUser = AuthMiddleware::requireAdmin();
            
            $input = json_decode(file_get_contents("php://input"), true);
            
            // CORRECCIÓN: Log solo evento, no payload completo (FASE 2)
            Logger::info('POST /usuarios - Admin: ' . $currentUser['email'] . ' - Creando usuario');
            // FIN CORRECCIÓN FASE 2

            // Validación del nombre
            $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
            if ($nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
                http_response_code(400);
                Logger::warn("Intento de insercion invalida por usuario: " . $currentUser['email']);
                echo json_encode([
                    "success" => false,
                    "error" => "Nombre invalido"
                ]);
                return;
            }

            $res = $this->model->create($input);
            Logger::info('Usuario creado exitosamente por: ' . $currentUser['email']);
            
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
            // INICIO: Manejo de excepciones - FASE 1
            Logger::error("Error en POST /usuarios: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al crear usuario: ' . $e->getMessage()
            ]);
            // FIN MANEJO EXCEPCIONES - FASE 1
        }
    }

    /**
     * Actualizar usuario (requiere rol de administrador)
     * INICIO BLOQUE TRY/CATCH - FASE 1 (+20 puntos)
     */
    public function update() {
        try {
            // Verificar que sea administrador - FASE 3 (+10 puntos)
            $currentUser = AuthMiddleware::requireAdmin();
            
            $input = json_decode(file_get_contents("php://input"), true);
            
            // CORRECCIÓN: Log solo evento (FASE 2)
            Logger::info('PATCH /usuarios - Admin: ' . $currentUser['email'] . ' - Actualizando usuario ID: ' . ($input['id'] ?? 'no especificado'));
            // FIN CORRECCIÓN FASE 2
            
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
                    Logger::warn("Intento de actualizacion invalida por usuario: " . $currentUser['email']);
                    echo json_encode([
                        "success" => false,
                        "error" => "Nombre invalido"
                    ]);
                    return;
                }
                $input['nombre'] = $nombre;
            }

            $res = $this->model->update($input);
            Logger::info('Usuario actualizado exitosamente por: ' . $currentUser['email']);
            
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
            // INICIO: Manejo de excepciones - FASE 1
            Logger::error("Error en PATCH /usuarios: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al actualizar usuario: ' . $e->getMessage()
            ]);
            // FIN MANEJO EXCEPCIONES - FASE 1
        }
    }

    /**
     * Eliminar usuario (requiere rol de administrador)
     * INICIO BLOQUE TRY/CATCH - FASE 1 (+20 puntos)
     */
    public function delete() {
        try {
            // Verificar que sea administrador - FASE 3 (+10 puntos)
            $currentUser = AuthMiddleware::requireAdmin();
            
            $input = json_decode(file_get_contents("php://input"), true);
            
            // CORRECCIÓN: Log solo evento (FASE 2)
            Logger::info('DELETE /usuarios - Admin: ' . $currentUser['email'] . ' - Eliminando usuario ID: ' . ($input['id'] ?? 'no especificado'));
            // FIN CORRECCIÓN FASE 2
            
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
            Logger::info('Usuario eliminado exitosamente por: ' . $currentUser['email']);
            
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
            // INICIO: Manejo de excepciones - FASE 1
            Logger::error("Error en DELETE /usuarios: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al eliminar usuario: ' . $e->getMessage()
            ]);
            // FIN MANEJO EXCEPCIONES - FASE 1
        }
    }

    /**
     * Obtener usuario por ID (requiere autenticación)
     * INICIO BLOQUE TRY/CATCH - FASE 1 (+20 puntos)
     */
    public function getById($id) {
        try {
            // Verificar autenticación
            $currentUser = AuthMiddleware::authenticate();
            
            // CORRECCIÓN: Log solo evento (FASE 2)
            Logger::info("GET /usuarios/$id - Usuario: " . $currentUser['email']);
            // FIN CORRECCIÓN FASE 2
            
            $user = $this->model->getById($id);
            if ($user) {
                echo json_encode([
                    'success' => true,
                    'data' => $user,
                    'requested_by' => $currentUser['email'],
                    'user_role' => $currentUser['rol']
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Usuario no encontrado'
                ]);
            }
        } catch (Exception $e) {
            // INICIO: Manejo de excepciones - FASE 1
            Logger::error("Error en GET /usuarios/$id: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener usuario'
            ]);
           
        }
    }
}