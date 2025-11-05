<?php
// api/models/Usuarios.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/logger.php';

class Usuarios {
    private $db;
    
    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    // ==================== MÉTODOS EXISTENTES (CRUD) ====================
    
    public function getAll() {
        $stmt = $this->db->query("SELECT id, nombre, email, rol, edad, is_active, created_at FROM usuarios WHERE is_deleted = false");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        // Para compatibilidad con el código existente - NO usar para registro de auth
        $nombre = isset($data['nombre']) ? $data['nombre'] : null;
        $rol = isset($data['rol']) ? $data['rol'] : null;
        $edad = isset($data['edad']) ? $data['edad'] : null;
        
        try {
            Logger::info("Intentando insertar usuario: " . json_encode(['nombre' => $nombre, 'rol' => $rol, 'edad' => $edad]));
            
            $stmt = $this->db->prepare("INSERT INTO usuarios (nombre, rol, edad) VALUES (:nombre, :rol, :edad)");
            $stmt->execute([
                ':nombre' => $nombre,
                ':rol' => $rol,
                ':edad' => $edad
            ]);
            $id = $this->db->lastInsertId();
            Logger::info("Usuario insertado correctamente con ID: " . $id);
            return ["success" => true, "id" => $id];
        } catch (PDOException $e) {
            $error = $e->getMessage();
            Logger::error("Error al crear usuario - SQL Error: " . $error);
            Logger::error("Datos intentados: " . json_encode(['nombre' => $nombre, 'rol' => $rol, 'edad' => $edad]));
            return [
                "success" => false, 
                "error" => "Error al crear usuario en la base de datos",
                "message" => "Verifica que la tabla 'usuarios' existe y tiene la estructura correcta",
                "debug" => $error
            ];
        }
    }

    public function update($data) {
        if (!isset($data['id'])) {
            return ["success" => false, "error" => "Falta el campo 'id' para actualizar"];
        }
        try {
            $stmt = $this->db->prepare("UPDATE usuarios SET nombre = :nombre, rol = :rol, edad = :edad WHERE id = :id");
            $stmt->execute([
                ':nombre' => isset($data['nombre']) ? $data['nombre'] : null,
                ':rol' => isset($data['rol']) ? $data['rol'] : null,
                ':edad' => isset($data['edad']) ? $data['edad'] : null,
                ':id' => $data['id']
            ]);
            return ["success" => true];
        } catch (PDOException $e) {
            Logger::error('Error en Usuarios::update - ' . $e->getMessage());
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    public function delete($id) {
        if (empty($id)) {
            return ["success" => false, "error" => "Falta el 'id' para eliminar"];
        }
        try {
            // SOFT DELETE - Marcar como eliminado en lugar de borrar físicamente
            $stmt = $this->db->prepare("UPDATE usuarios SET is_deleted = true, deleted_at = NOW() WHERE id = :id");
            $stmt->execute([':id' => $id]);
            Logger::info("Usuario marcado como eliminado (soft delete): " . $id);
            return ["success" => true];
        } catch (PDOException $e) {
            Logger::error('Error en Usuarios::delete - ' . $e->getMessage());
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    // ==================== NUEVOS MÉTODOS DE AUTENTICACIÓN ====================

    /**
     * Crear usuario con autenticación (email y password)
     */
    public function createUser($userData) {
        try {
            // Validar datos requeridos
            $required = ['nombre', 'email', 'password', 'edad', 'rol'];
            foreach ($required as $field) {
                if (!isset($userData[$field]) || empty(trim($userData[$field]))) {
                    return ["success" => false, "error" => "Campo requerido faltante: $field"];
                }
            }

            $nombre = trim($userData['nombre']);
            $email = trim($userData['email']);
            $password = $userData['password'];
            $edad = intval($userData['edad']);
            $rol = trim($userData['rol']);

            // Hash de la contraseña
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $this->db->prepare("INSERT INTO usuarios (nombre, email, password_hash, edad, rol) VALUES (:nombre, :email, :password_hash, :edad, :rol)");
            $stmt->execute([
                ':nombre' => $nombre,
                ':email' => $email,
                ':password_hash' => $password_hash,
                ':edad' => $edad,
                ':rol' => $rol
            ]);
            
            $id = $this->db->lastInsertId();
            Logger::info("Usuario registrado con autenticación - ID: $id, Email: $email");
            
            return ["success" => true, "user_id" => $id];
        } catch (PDOException $e) {
            Logger::error("Error en createUser - " . $e->getMessage());
            return ["success" => false, "error" => "Error al crear usuario: " . $e->getMessage()];
        }
    }

    /**
     * Verificar si el email ya existe
     */
    public function emailExists($email) {
        $stmt = $this->db->prepare("SELECT id FROM usuarios WHERE email = :email AND is_deleted = false");
        $stmt->execute([':email' => $email]);
        return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    }

    /**
     * Validar credenciales de login
     */
    public function validateCredentials($email, $password) {
        $stmt = $this->db->prepare("SELECT id, nombre, email, password_hash, rol, edad, is_active FROM usuarios WHERE email = :email AND is_deleted = false");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash']) && $user['is_active']) {
            // Remover password_hash del resultado
            unset($user['password_hash']);
            return $user;
        }
        
        return false;
    }

    /**
     * Obtener usuario por token de sesión
     */
    public function getUserBySessionToken($token) {
        $stmt = $this->db->prepare("SELECT id, nombre, email, rol, edad, is_active, last_login FROM usuarios WHERE session_token = :token AND is_active = true AND is_deleted = false");
        $stmt->execute([':token' => $token]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Actualizar token de sesión
     */
    public function updateSessionToken($userId, $token) {
        $stmt = $this->db->prepare("UPDATE usuarios SET session_token = :token, last_login = NOW() WHERE id = :id");
        return $stmt->execute([':token' => $token, ':id' => $userId]);
    }

    /**
     * Invalidar token de sesión (logout)
     */
    public function invalidateSessionToken($token) {
        $stmt = $this->db->prepare("UPDATE usuarios SET session_token = NULL WHERE session_token = :token");
        return $stmt->execute([':token' => $token]);
    }

    /**
     * Actualizar último acceso
     */
    public function updateLastAccess($userId) {
        $stmt = $this->db->prepare("UPDATE usuarios SET last_login = NOW() WHERE id = :id");
        return $stmt->execute([':id' => $userId]);
    }

    /**
     * Obtener usuario por ID (para perfil)
     */
    public function getById($id) {
        $stmt = $this->db->prepare("SELECT id, nombre, email, rol, edad, is_active, last_login, created_at FROM usuarios WHERE id = :id AND is_deleted = false");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}