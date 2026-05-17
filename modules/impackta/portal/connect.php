<?php

function impacktaLoadParameters()
{
    // Carga los parametros de base de datos tanto en estructura clasica como moderna de PrestaShop.
    $candidates = array(
        '../../../app/config/parameters.php',
        '../../../config/parameters.php',
    );

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            $parameters = include $candidate;
            if (is_array($parameters) && isset($parameters['parameters'])) {
                return $parameters['parameters'];
            }
        }
    }

    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode(array('error' => 'No se pudo cargar la configuracion de base de datos.')));
}

function impacktaGetString($key, $source = INPUT_GET, $default = '')
{
    // Lee parametros GET/POST como texto normalizado para soportar llamadas externas de Impackta.
    $value = filter_input($source, $key, FILTER_UNSAFE_RAW);
    if (($value === null || $value === false) && $source === INPUT_GET) {
        $value = filter_input(INPUT_POST, $key, FILTER_UNSAFE_RAW);
    }

    if ($value === null || $value === false) {
        return $default;
    }

    return trim((string) $value);
}

function impacktaGetInt($key, $source = INPUT_GET, $default = 0)
{
    // Lee ids numericos de la peticion evitando usar directamente $_GET o $_POST.
    $value = filter_input($source, $key, FILTER_VALIDATE_INT);
    if (($value === false || $value === null) && $source === INPUT_GET) {
        $value = filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT);
    }

    if ($value === false || $value === null) {
        return (int) $default;
    }

    return (int) $value;
}

function impacktaIsValidDateTime($value)
{
    // Comprueba si una fecha recibida por la API se puede convertir al formato SQL esperado.
    return impacktaNormalizeDateTime($value) !== false;
}

function impacktaNormalizeDateTime($value, $endOfDay = false)
{
    // Acepta varios formatos de fecha habituales y los unifica como Y-m-d H:i:s para filtrar pedidos.
    if (!is_string($value) || trim($value) === '') {
        return false;
    }

    $value = trim($value);
    $formats = array(
        'Y-m-d H:i:s',
        'Y-m-d H:i',
        'Y-m-d',
        'd-m-Y H:i:s',
        'd-m-Y H:i',
        'd-m-Y',
    );

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $value);
        if ($date instanceof DateTime && $date->format($format) === $value) {
            if ($format === 'Y-m-d' || $format === 'd-m-Y') {
                if ($endOfDay) {
                    $date->setTime(23, 59, 59);
                } else {
                    $date->setTime(0, 0, 0);
                }
            } elseif ($format === 'Y-m-d H:i' || $format === 'd-m-Y H:i') {
                $date->setTime((int) $date->format('H'), (int) $date->format('i'), 0);
            }

            return $date->format('Y-m-d H:i:s');
        }
    }

    return false;
}

function impacktaJsonResponse($payload, $statusCode = 200)
{
    // Finaliza la peticion devolviendo JSON, que es el formato usado por consultas de pedidos/productos.
    header('Content-Type: application/json');
    http_response_code((int) $statusCode);
    die(json_encode($payload));
}

function impacktaTextResponse($message, $statusCode = 200)
{
    // Finaliza la peticion con texto plano para acciones tipo confirmado/borrado usadas por Impackta.
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code((int) $statusCode);
    die((string) $message);
}

function impacktaPrepareAndExecute($link, $sql, array $params = array(), $types = '')
{
    // Envoltorio unico para sentencias preparadas; reduce SQL injection y funciona con mysqli o compat PDO.
    $statement = $link->prepare($sql);

    if (!$statement) {
        return false;
    }

    if (!empty($params)) {
        $bindParams = array($types);
        foreach ($params as $paramKey => $paramValue) {
            $bindParams[] = &$params[$paramKey];
        }

        call_user_func_array(array($statement, 'bind_param'), $bindParams);
    }

    if (!$statement->execute()) {
        $statement->close();

        return false;
    }

    return $statement;
}

function impacktaBootKernel()
{
    // Arranca el kernel Symfony cuando una accion de estado necesita servicios/eventos de PS modernos.
    global $kernel;

    if ($kernel) {
        return;
    }

    if (!file_exists(_PS_ROOT_DIR_ . '/app/AppKernel.php')) {
        return;
    }

    require_once _PS_ROOT_DIR_ . '/app/AppKernel.php';

    if (!class_exists('AppKernel', false)) {
        return;
    }

    try {
        $reflection = new ReflectionClass('AppKernel');
        if ($reflection->isAbstract()) {
            return;
        }

        $kernel = $reflection->newInstance('prod', false);
        if (is_object($kernel) && method_exists($kernel, 'boot')) {
            $kernel->boot();
        }
    } catch (Exception $exception) {
        return;
    } catch (Error $error) {
        return;
    }
}

$parameters = impacktaLoadParameters();

$ip = isset($parameters['database_host']) ? $parameters['database_host'] : '';
$port = isset($parameters['database_port']) ? $parameters['database_port'] : '';
$user = isset($parameters['database_user']) ? $parameters['database_user'] : '';
$password = isset($parameters['database_password']) ? $parameters['database_password'] : '';
$database = isset($parameters['database_name']) ? $parameters['database_name'] : '';
$prefix = isset($parameters['database_prefix']) ? preg_replace('/[^a-zA-Z0-9_]/', '', $parameters['database_prefix']) : '';

if (!class_exists('mysqli')) {
    class ImpacktaMysqliCompat
    {
        /** @var PDO */
        private $pdo;

        public function __construct($host, $user, $password, $database, $port = null)
        {
            // Compatibilidad para entornos sin extension mysqli: imita lo minimo usando PDO.
            $dsn = 'mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8';

            if (!empty($port)) {
                $dsn .= ';port=' . $port;
            }

            $this->pdo = new PDO($dsn, $user, $password, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_NUM,
            ));
        }

        public function prepare($sql)
        {
            // Devuelve un statement compatible con la interfaz mysqli usada por el portal.
            return new ImpacktaMysqliCompatStatement($this->pdo->prepare($sql));
        }

        public function set_charset($charset)
        {
            // PDO ya queda configurado en UTF-8 desde el DSN.
            return true;
        }
    }

    class ImpacktaMysqliCompatStatement
    {
        /** @var PDOStatement */
        private $statement;

        /** @var array<int, mixed> */
        private $params = array();

        /** @var array<int, mixed> */
        private $currentRow = array();

        /** @var array<int, mixed> */
        private $boundResults = array();

        public function __construct(PDOStatement $statement)
        {
            $this->statement = $statement;
        }

        public function bind_param()
        {
            // Traduce bind_param de mysqli a un array de parametros para PDO.
            $arguments = func_get_args();
            array_shift($arguments);
            $this->params = array_values($arguments);

            return true;
        }

        public function execute()
        {
            // Ejecuta el statement PDO con los parametros guardados por bind_param.
            return $this->statement->execute($this->params);
        }

        public function bind_result()
        {
            // Mantiene referencias a variables para simular bind_result de mysqli.
            $this->boundResults = array();
            $arguments = func_get_args();

            foreach ($arguments as $index => &$argument) {
                $this->boundResults[$index] = &$argument;
            }

            return true;
        }

        public function fetch()
        {
            // Copia la fila actual en las variables enlazadas, igual que haria mysqli.
            $this->currentRow = $this->statement->fetch();

            if ($this->currentRow === false) {
                return false;
            }

            foreach ($this->boundResults as $index => &$boundResult) {
                $boundResult = array_key_exists($index, $this->currentRow) ? $this->currentRow[$index] : null;
            }

            return true;
        }

        public function close()
        {
            // Libera el cursor PDO con la misma llamada que espera el codigo mysqli.
            $this->statement->closeCursor();

            return true;
        }
    }
}

if (class_exists('mysqli')) {
    if (empty($port)) {
        $link = new mysqli($ip, $user, $password, $database);
    } else {
        $link = new mysqli($ip, $user, $password, $database, $port);
    }
} else {
    $link = new ImpacktaMysqliCompat($ip, $user, $password, $database, $port);
}

$link->set_charset("utf8");

$id_lang = 0;
$id_bbdd = null;

$sql = "SELECT value FROM `" . $prefix . "configuration` WHERE name = 'PS_LANG_DEFAULT' LIMIT 1";
$sentencia = impacktaPrepareAndExecute($link, $sql);
if ($sentencia) {
    $sentencia->bind_result($id_bbdd);
    if ($sentencia->fetch()) {
        $id_lang = (int) $id_bbdd;
    }
    $sentencia->close();
}

if ($id_lang <= 0) {
    $sql = "SELECT id_lang FROM `" . $prefix . "lang` WHERE active = 1 ORDER BY id_lang ASC LIMIT 1";
    $sentencia = impacktaPrepareAndExecute($link, $sql);
    if ($sentencia) {
        $sentencia->bind_result($id_bbdd);
        if ($sentencia->fetch()) {
            $id_lang = (int) $id_bbdd;
        }
        $sentencia->close();
    }
}

$uid = "";
$idCliente = "";
$id_bbdd = null;
$id_cliente_bbdd = null;
$requestedShopId = impacktaGetInt('idShop');

if ($requestedShopId > 0) {
    $sql = "SELECT guid, idCliente FROM `" . $prefix . "impackta` WHERE id_shop = ? LIMIT 1";
    $sentencia = impacktaPrepareAndExecute($link, $sql, array((int) $requestedShopId), 'i');
} else {
    // Compatibilidad con URLs antiguas generadas antes de incluir idShop.
    $sql = "SELECT guid, idCliente FROM `" . $prefix . "impackta` ORDER BY id_shop ASC LIMIT 1";
    $sentencia = impacktaPrepareAndExecute($link, $sql);
}

if ($sentencia) {
    $sentencia->bind_result($id_bbdd, $id_cliente_bbdd);

    if ($sentencia->fetch()) {
        $uid = $id_bbdd;
        $idCliente = $id_cliente_bbdd;
    }

    $sentencia->close();
}
