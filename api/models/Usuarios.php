<?php
// api/models/Usuarios.php
require_once __DIR__ . '/../config/db.php';
class Usuarios {
    private $db;
    public function __construct() {
        $this->db = (new Database())->getConnection();
    }
    public function getAll() {
        $stmt = $this->db->query("SELECT id, nombre, rol, edad FROM usuarios");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function create($data) {
        // Aceptar nombre, rol y edad; valores nulos permitidos
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
            // Registrar error detallado
            if (class_exists('Logger')) {
                Logger::error("Error al crear usuario - SQL Error: " . $error);
                Logger::error("Datos intentados: " . json_encode(['nombre' => $nombre, 'rol' => $rol, 'edad' => $edad]));
            }
            return [
                "success" => false, 
                "error" => "Error al crear usuario en la base de datos",
                "message" => "Verifica que la tabla 'usuarios' existe y tiene la estructura correcta",
                "debug" => $error
            ];
        }
    }
    public function update($data) {
        // Para simplicidad actualizamos nombre, rol y edad; los valores pueden ser nulos
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
            if (class_exists('Logger')) {
                Logger::error('Error en Usuarios::update - ' . $e->getMessage());
            }
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
    public function delete($id) {
        if (empty($id)) {
            return ["success" => false, "error" => "Falta el 'id' para eliminar"];
        }
        try {
            $stmt = $this->db->prepare("DELETE FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return ["success" => true];
        } catch (PDOException $e) {
            if (class_exists('Logger')) {
                Logger::error('Error en Usuarios::delete - ' . $e->getMessage());
            }
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
