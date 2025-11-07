<?php
// controllers/ProfileController.php

require_once __DIR__ . '/../models/Usuarios.php';
require_once __DIR__ . '/../config/logger.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

class ProfileController {
    private $model;

    public function __construct() {
        $this->model = new Usuarios();
    }

    /**
     * Obtener perfil completo del usuario autenticado
     */
    public function getProfile() {
        try {
            $user = AuthMiddleware::authenticate();
            
            Logger::info("GET /profile - Usuario: " . $user['email']);
            
            // Obtener datos completos del perfil
            $profile = $this->model->getById($user['id']);
            
            if ($profile) {
                echo json_encode([
                    'success' => true,
                    'profile' => [
                        'id' => $profile['id'],
                        'nombre' => $profile['nombre'],
                        'email' => $profile['email'],
                        'rol' => $profile['rol'],
                        'edad' => $profile['edad'],
                        'is_active' => $profile['is_active'],
                        'last_login' => $profile['last_login'],
                        'created_at' => $profile['created_at'],
                        'member_since' => $this->getTimeSince($profile['created_at'])
                    ]
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Perfil no encontrado'
                ]);
            }
        } catch (Exception $e) {
            Logger::error("Error en GET /profile: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener perfil'
            ]);
        }
    }

    /**
     * Actualizar perfil del usuario autenticado
     */
    public function updateProfile() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $input = json_decode(file_get_contents("php://input"), true);
            Logger::info('PATCH /profile - Usuario: ' . $user['email'] . ' - Payload: ' . json_encode($input));
            
            // Campos permitidos para actualizar en el perfil
            $allowedFields = ['nombre', 'edad'];
            $updateData = ['id' => $user['id']];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    if ($field === 'nombre') {
                        // Validar nombre
                        $nombre = trim($input['nombre']);
                        if ($nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
                            http_response_code(400);
                            echo json_encode([
                                'success' => false,
                                'error' => 'Nombre inválido'
                            ]);
                            return;
                        }
                        $updateData['nombre'] = $nombre;
                    } else {
                        $updateData[$field] = $input[$field];
                    }
                }
            }
            
            // Si no hay campos para actualizar
            if (count($updateData) === 1) { // Solo tiene el ID
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'No se proporcionaron campos válidos para actualizar',
                    'allowed_fields' => $allowedFields
                ]);
                return;
            }
            
            $result = $this->model->update($updateData);
            
            if ($result['success']) {
                Logger::info("Perfil actualizado - Usuario: " . $user['email']);
                echo json_encode([
                    'success' => true,
                    'message' => 'Perfil actualizado exitosamente',
                    'updated_fields' => array_keys(array_diff_key($updateData, ['id' => true]))
                ]);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
            
        } catch (Exception $e) {
            Logger::error("Error en PATCH /profile: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al actualizar perfil'
            ]);
        }
    }

    /**
     * Cambiar contraseña del usuario autenticado
     */
    public function changePassword() {
        try {
            $user = AuthMiddleware::authenticate();
            
            $input = json_decode(file_get_contents("php://input"), true);
            Logger::info('POST /profile/change-password - Usuario: ' . $user['email']);
            
            // Validar campos requeridos
            $required = ['current_password', 'new_password'];
            foreach ($required as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => "Campo requerido faltante: $field"
                    ]);
                    return;
                }
            }
            
            $currentPassword = $input['current_password'];
            $newPassword = $input['new_password'];
            
            // Verificar contraseña actual
            if (!$this->model->validateCredentials($user['email'], $currentPassword)) {
                http_response_code(401);
                Logger::warn("Intento de cambio de contraseña fallido - Contraseña actual incorrecta: " . $user['email']);
                echo json_encode([
                    'success' => false,
                    'error' => 'Contraseña actual incorrecta'
                ]);
                return;
            }
            
            // Validar nueva contraseña (mínimo 6 caracteres)
            if (strlen($newPassword) < 6) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'La nueva contraseña debe tener al menos 6 caracteres'
                ]);
                return;
            }
            
            // Actualizar contraseña
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
            $stmt = $this->model->getConnection()->prepare("UPDATE usuarios SET password_hash = :password_hash WHERE id = :id");
            $success = $stmt->execute([
                ':password_hash' => $passwordHash,
                ':id' => $user['id']
            ]);
            
            if ($success) {
                Logger::info("Contraseña cambiada exitosamente - Usuario: " . $user['email']);
                echo json_encode([
                    'success' => true,
                    'message' => 'Contraseña cambiada exitosamente'
                ]);
            } else {
                throw new Exception("Error al actualizar contraseña en la base de datos");
            }
            
        } catch (Exception $e) {
            Logger::error("Error en POST /profile/change-password: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al cambiar contraseña'
            ]);
        }
    }

    /**
     * Obtener estadísticas del usuario
     */
    public function getUserStats() {
        try {
            $user = AuthMiddleware::authenticate();
            
            Logger::info("GET /profile/stats - Usuario: " . $user['email']);
            
            // Aquí puedes agregar más estadísticas según tu aplicación
            $stats = [
                'user_id' => $user['id'],
                'member_since' => $user['created_at'] ?? 'N/A',
                'last_login' => $user['last_login'] ?? 'Nunca',
                'account_status' => $user['is_active'] ? 'Activo' : 'Inactivo',
                'role' => $user['rol']
            ];
            
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
            
        } catch (Exception $e) {
            Logger::error("Error en GET /profile/stats: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error al obtener estadísticas'
            ]);
        }
    }

    /**
     * Helper para calcular tiempo desde la fecha de creación
     */
    private function getTimeSince($date) {
        if (!$date) return 'N/A';
        
        $created = new DateTime($date);
        $now = new DateTime();
        $interval = $created->diff($now);
        
        if ($interval->y > 0) return $interval->y . ' año' . ($interval->y > 1 ? 's' : '');
        if ($interval->m > 0) return $interval->m . ' mes' . ($interval->m > 1 ? 'es' : '');
        if ($interval->d > 0) return $interval->d . ' día' . ($interval->d > 1 ? 's' : '');
        
        return 'Hoy';
    }

    /**
     * Método para obtener la conexión (helper)
     */
    private function getConnection() {
        return (new Database())->getConnection();
    }
}