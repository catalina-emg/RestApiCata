<?php
// controllers/UsuariosController.php
require_once __DIR__ . '/../models/Usuarios.php';
require_once __DIR__ . '/../config/logger.php';

class UsuariosController {
    private $model;

    public function __construct() {
        $this->model = new Usuarios();
    }

    public function getAll() {
        Logger::info('GET /usuarios');
        $result = $this->model->getAll();
        echo json_encode($result);
    }

    public function create() {
        $input = json_decode(file_get_contents("php://input"), true);
        Logger::info('POST /usuarios payload: ' . json_encode($input));

        // Validación: el nombre sólo debe contener letras (incluye acentos) y espacios.
        // Si no coincide, devolvemos un error y registramos el intento.
        $nombre = isset($input['nombre']) ? trim($input['nombre']) : '';
        // Usar \\p{L} con modificador u para admitir letras Unicode (acentos, ñ, etc.)
        if ($nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
            http_response_code(400);
            Logger::warn("Intento de insercion invalida: $nombre");
            echo json_encode(["error" => "Nombre invalido"]);
            return;
        }

        $res = $this->model->create($input);
        Logger::info('POST /usuarios result: ' . json_encode($res));
        if (isset($res['success']) && $res['success'] === false) {
            http_response_code(500);
            echo json_encode($res);
            return;
        }
        echo json_encode($res);
    }

    public function update() {
        $input = json_decode(file_get_contents("php://input"), true);
        Logger::info('PATCH /usuarios payload: ' . json_encode($input));
        // Si se proporciona 'nombre' en el payload, validarlo
        if (isset($input['nombre'])) {
            $nombre = trim($input['nombre']);
            if ($nombre === '' || !preg_match('/^[\p{L}\s]+$/u', $nombre)) {
                http_response_code(400);
                Logger::warn("Intento de actualizacion invalida: $nombre");
                echo json_encode(["error" => "Nombre invalido"]);
                return;
            }
            // actualizar el valor normalizado
            $input['nombre'] = $nombre;
        }

        $res = $this->model->update($input);
        Logger::info('PATCH /usuarios result: ' . json_encode($res));
        if (isset($res['success']) && $res['success'] === false) {
            http_response_code(400);
            echo json_encode($res);
            return;
        }
        echo json_encode($res);
    }

    public function delete() {
        $input = json_decode(file_get_contents("php://input"), true);
        Logger::info('DELETE /usuarios payload: ' . json_encode($input));
        if (!isset($input['id'])) {
            http_response_code(400);
            $err = ["success" => false, "error" => "Falta el campo 'id'"];
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
        echo json_encode($res);
    }
}
