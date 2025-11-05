<?php
// config/db.php
require_once __DIR__ . '/logger.php';

class Database {
    private $host = "localhost";
    private $db_name = "rest_api_catalina"; 
    private $username = "root";
    private $password = "";
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            // Intentar conectar
            $this->conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password
            );

            // Configurar modo de errores
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // Log de conexión con más detalles
            Logger::info("DB conectado a {$this->db_name} en {$this->host} con usuario {$this->username}");
        } catch (PDOException $e) {
            // Captura detallada de la excepción
            $error = $e->getMessage();
            Logger::error("Error de conexión a BD: {$error}");
            Logger::error("Detalles de conexión: host={$this->host}, db={$this->db_name}, user={$this->username}");
            http_response_code(500);
            echo json_encode([
                "error" => "Error al conectar a la base de datos",
                "message" => "Verifica que la base de datos exista y los datos de conexión sean correctos",
                "detalles" => $error
            ]);
            exit;
        }
        

        return $this->conn;
    }
}

