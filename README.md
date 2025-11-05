## Desarrollo

### 1. Configuración del Servidor (XAMPP, BD)

#### Instalación
- Instalar XAMPP con Apache, MySQL y PHP
- Iniciar servicios desde el panel de control
- Verificar `mod_rewrite` habilitado en `httpd.conf`

#### Base de Datos

```sql
CREATE DATABASE rest_api_catalina CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE rest_api_catalina;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100),
    rol VARCHAR(50),
    edad INT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO usuarios (nombre, rol, edad) VALUES
('María José González', 'admin', 28),
('José Ángel Pérez', 'usuario', 22);
```

---

### 2. Conexión PDO con Prepared Statements

**`config/db.php`**

```php
<?php
require_once __DIR__ . '/logger.php';

class Database {
    private $host = "localhost";
    private $db_name = "rest_api_catalina"; 
    private $username = "root";
    private $password = "";

    public function getConnection() {
        try {
            // Conexión PDO con UTF-8
            $conn = new PDO(
                "mysql:host={$this->host};dbname={$this->db_name};charset=utf8mb4",
                $this->username,
                $this->password
            );
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            Logger::info("DB conectado a {$this->db_name}");
            return $conn;
        } catch (PDOException $e) {
            Logger::error("Error de conexión: {$e->getMessage()}");
            http_response_code(500);
            echo json_encode(["error" => "Error al conectar a BD"]);
            exit;
        }
    }
}
```

**Ventajas:**
- Previene inyección SQL
- Separa datos del código SQL
- Mejor rendimiento

---

### 3. Implementación CRUD

**`models/Usuarios.php`**

```php
<?php
require_once __DIR__ . '/../config/db.php';

class Usuarios {
    private $db;

    public function __construct() {
        $this->db = (new Database())->getConnection();
    }

    // GET - Obtener todos
    public function getAll() {
        $stmt = $this->db->query("SELECT id, nombre, rol, edad FROM usuarios");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // POST - Crear usuario
    public function create($data) {
        try {
            $stmt = $this->db->prepare(
                "INSERT INTO usuarios (nombre, rol, edad) VALUES (:nombre, :rol, :edad)"
            );
            $stmt->execute([
                ':nombre' => $data['nombre'] ?? null,
                ':rol' => $data['rol'] ?? null,
                ':edad' => $data['edad'] ?? null
            ]);
            $id = $this->db->lastInsertId();
            Logger::info("Usuario creado con ID: $id");
            return ["success" => true, "id" => $id];
        } catch (PDOException $e) {
            Logger::error("Error al crear: {$e->getMessage()}");
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    // PATCH - Actualizar usuario
    public function update($data) {
        if (!isset($data['id'])) {
            return ["success" => false, "error" => "Falta campo 'id'"];
        }
        try {
            $stmt = $this->db->prepare(
                "UPDATE usuarios SET nombre=:nombre, rol=:rol, edad=:edad WHERE id=:id"
            );
            $stmt->execute([
                ':nombre' => $data['nombre'] ?? null,
                ':rol' => $data['rol'] ?? null,
                ':edad' => $data['edad'] ?? null,
                ':id' => $data['id']
            ]);
            return ["success" => true];
        } catch (PDOException $e) {
            Logger::error("Error al actualizar: {$e->getMessage()}");
            return ["success" => false, "error" => $e->getMessage()];
        }
    }

    // DELETE - Eliminar usuario
    public function delete($id) {
        try {
            $stmt = $this->db->prepare("DELETE FROM usuarios WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return ["success" => true];
        } catch (PDOException $e) {
            Logger::error("Error al eliminar: {$e->getMessage()}");
            return ["success" => false, "error" => $e->getMessage()];
        }
    }
}
```

**`controllers/UsuariosController.php`**

```php
<?php
require_once __DIR__ . '/../models/Usuarios.php';
require_once __DIR__ . '/../config/logger.php';

class UsuariosController {
    private $model;

    public function __construct() {
        $this->model = new Usuarios();
    }

    // GET /usuarios
    public function getAll() {
        Logger::info('GET /usuarios');
        echo json_encode($this->model->getAll());
    }

    // POST /usuarios
    public function create() {
        $input = json_decode(file_get_contents("php://input"), true);
        Logger::info('POST /usuarios: ' . json_encode($input));

        // Validación: solo letras, espacios y acentos
        $nombre = trim($input['nombre'] ?? '');
        if (!preg_match('/^[\p{L}\s]+$/u', $nombre)) {
            http_response_code(400);
            Logger::warn("Nombre inválido: $nombre");
            echo json_encode(["error" => "Nombre inválido"]);
            return;
        }

        $res = $this->model->create($input);
        if (!$res['success']) http_response_code(500);
        echo json_encode($res);
    }

    // PATCH /usuarios
    public function update() {
        $input = json_decode(file_get_contents("php://input"), true);
        Logger::info('PATCH /usuarios: ' . json_encode($input));

        if (isset($input['nombre'])) {
            $nombre = trim($input['nombre']);
            if (!preg_match('/^[\p{L}\s]+$/u', $nombre)) {
                http_response_code(400);
                Logger::warn("Nombre inválido: $nombre");
                echo json_encode(["error" => "Nombre inválido"]);
                return;
            }
        }

        $res = $this->model->update($input);
        if (!$res['success']) http_response_code(400);
        echo json_encode($res);
    }

    // DELETE /usuarios
    public function delete() {
        $input = json_decode(file_get_contents("php://input"), true);
        Logger::info('DELETE /usuarios: ' . json_encode($input));

        if (!isset($input['id'])) {
            http_response_code(400);
            echo json_encode(["error" => "Falta campo 'id'"]);
            return;
        }

        $res = $this->model->delete($input['id']);
        if (!$res['success']) http_response_code(400);
        echo json_encode($res);
    }
}
```

---

### 4. Rutas Limpias con `.htaccess`

**`.htaccess` (raíz)**

```apache
RewriteEngine On
RewriteRule ^api/(.*)$ api/routes.php [QSA,L]
```

**`logs/.htaccess`**

```apache
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>
Options -Indexes
```

**Resultado:**
- `/api/usuarios` → `routes.php`
- Logs protegidos (403 Forbidden)

---

### 5. Sistema de Logs

**`config/logger.php`**

```php
<?php
class Logger {
    private static $logFile = null;

    private static function ensureInit() {
        if (self::$logFile === null) {
            $projectLogDir = __DIR__ . '/../logs';
            if (!is_dir($projectLogDir)) @mkdir($projectLogDir, 0755, true);
            self::$logFile = $projectLogDir . '/server.log';
        }
    }

    // Rotación automática de logs
    private static function rotateIfNeeded() {
        self::ensureInit();
        $maxLines = getenv('LOG_MAX_LINES') ?: 5000;
        
        if (!file_exists(self::$logFile)) return;

        // Contar líneas
        $lineCount = 0;
        $fp = fopen(self::$logFile, 'r');
        if ($fp) {
            while (!feof($fp)) {
                fgets($fp);
                $lineCount++;
                if ($lineCount >= $maxLines) break;
            }
            fclose($fp);
        }

        if ($lineCount < $maxLines) return;

        // Comprimir log antiguo
        $archiveDir = dirname(self::$logFile) . '/archive';
        if (!is_dir($archiveDir)) @mkdir($archiveDir, 0755, true);
        
        $timestamp = date('Ymd_His');
        $archivePath = "$archiveDir/server.log.$timestamp.gz";

        // Crear archivo .gz
        $in = fopen(self::$logFile, 'rb');
        $out = gzopen($archivePath, 'wb9');
        if ($in && $out) {
            while (!feof($in)) {
                gzwrite($out, fread($in, 1024 * 512));
            }
            gzclose($out);
            fclose($in);
        }

        // Limpiar archivo original
        file_put_contents(self::$logFile, 
            "[" . date('Y-m-d H:i:s') . "] [INFO] Rotated log to " . basename($archivePath) . PHP_EOL
        );
    }

    private static function write($level, $message) {
        self::ensureInit();
        self::rotateIfNeeded();
        $entry = "[" . date('Y-m-d H:i:s') . "] [$level] $message" . PHP_EOL;
        file_put_contents(self::$logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    public static function info($message) { self::write('INFO', $message); }
    public static function warn($message) { self::write('WARN', $message); }
    public static function error($message) { self::write('ERROR', $message); }
}

date_default_timezone_set('America/Mexico_City');
```

**Características:**
- Rotación automática al alcanzar 5000 líneas
- Compresión gzip en `logs/archive/`
- Thread-safe con `LOCK_EX`
- Niveles: INFO, WARN, ERROR

**Ejemplo `logs/server.log`:**
```
[2025-11-04 15:30:45] [INFO] Request: GET /usuarios
[2025-11-04 15:31:20] [INFO] Usuario creado con ID: 15
[2025-11-04 15:32:10] [WARN] Nombre inválido: User123!
[2025-11-04 15:33:00] [ERROR] Error de conexión: Access denied
```

---

### 6. Endpoint `/stats`

**`controllers/StatsController.php`**

```php
<?php
class StatsController {
    public static function handler() {
        // Tiempo de ejecución del request
        $uptime = round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2);
        
        // Memoria usada (MB)
        $memory = round(memory_get_usage() / 1024 / 1024, 2);
        
        echo json_encode([
            "uptime_seconds" => $uptime,
            "memory_MB" => $memory,
            "fecha" => date("Y-m-d H:i:s")
        ]);
    }
}
```

**Respuesta:**
```json
{
  "uptime_seconds": 0.15,
  "memory_MB": 2.37,
  "fecha": "2025-11-04 15:35:22"
}
```

---

### 7. Validación y Manejo de Errores

#### Validación de Nombres

```php
// Acepta letras Unicode (á, é, í, ó, ú, ñ) y espacios
if (!preg_match('/^[\p{L}\s]+$/u', $nombre)) {
    http_response_code(400);
    Logger::warn("Validación fallida: $nombre");
    echo json_encode(["error" => "Nombre inválido"]);
    return;
}
```

**Válidos:** "María José", "François Müller"  
**Inválidos:** "User123", "admin@user"

#### Manejo de Errores

```php
try {
    $stmt = $this->db->prepare("INSERT INTO usuarios ...");
    $stmt->execute([...]);
} catch (PDOException $e) {
    Logger::error("Error SQL: {$e->getMessage()}");
    http_response_code(500);
    echo json_encode(["error" => "Error en el servidor"]);
}
```

**Códigos HTTP:**
- 200: OK
- 400: Datos inválidos
- 404: Ruta no encontrada
- 500: Error del servidor

---

### 8. Enrutador Principal

**`routes.php`**

```php
<?php
require_once __DIR__ . '/controllers/UsuariosController.php';
require_once __DIR__ . '/controllers/StatsController.php';
require_once __DIR__ . '/config/logger.php';

// Parsear URI: /api/usuarios → "usuarios"
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uri = preg_replace('#^.*/api/#', '', $uri);
$uri = trim($uri, '/');
$method = $_SERVER['REQUEST_METHOD'];

$controller = new UsuariosController();

// Alias: /alumnos → /usuarios
$aliases = ['alumnos' => 'usuarios'];
$resource = $aliases[$uri] ?? $uri;

Logger::info("Request: $method /$uri -> $resource");

// Enrutamiento
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
```

---

## Conclusiones

### Aprendizajes

- **Seguridad:** Prepared statements previenen inyección SQL
- **Modularidad:** Arquitectura MVC separa responsabilidades
- **Monitoreo:** Sistema de logs permite auditar y debuggear
- **Validación:** Regex Unicode-safe maneja nombres con acentos
- **Rotación:** Compresión automática previene crecimiento de logs

### Desafíos Superados

- Configurar `mod_rewrite` correctamente
- Implementar validación Unicode (acentos)
- Rotación de logs sin bloqueos
- Manejo de errores PDO

---

## Evidencias

### Pruebas con cURL

```bash
# GET - Listar usuarios
curl http://localhost/api/usuarios

# POST - Crear usuario
curl -X POST http://localhost/api/usuarios \
  -H "Content-Type: application/json" \
  -d '{"nombre":"Ana García","rol":"admin","edad":30}'

# PATCH - Actualizar usuario
curl -X PATCH http://localhost/api/usuarios \
  -H "Content-Type: application/json" \
  -d '{"id":1,"edad":31}'

# DELETE - Eliminar usuario
curl -X DELETE http://localhost/api/usuarios \
  -H "Content-Type: application/json" \
  -d '{"id":1}'

# GET - Estadísticas
curl http://localhost/api/stats
```