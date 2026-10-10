<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';
startWebSession();
header('Content-Type: application/json; charset=utf-8');

function respond(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    if (!is_string($action)) {
        respond(['error' => 'Acción inválida.'], 400);
    }
    $input = [];
    if ($method === 'POST') {
        if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 65536) {
            respond(['error' => 'Solicitud demasiado grande.'], 413);
        }
        $raw = file_get_contents('php://input', false, null, 0, 65537);
        if (strlen($raw) > 65536) {
            respond(['error' => 'Solicitud demasiado grande.'], 413);
        }
        try {
            $input = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException $error) {
            respond(['error' => 'El cuerpo debe contener JSON válido.'], 400);
        }
        if (!is_array($input)) {
            respond(['error' => 'Solicitud inválida.'], 400);
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['csrf'], $token)) {
            respond(['error' => 'La sesión expiró o el token CSRF no es válido. Recarga la página.'], 403);
        }
    } elseif ($method !== 'GET') {
        header('Allow: GET, POST');
        respond(['error' => 'Método no permitido.'], 405);
    }
    session_write_close();
    if ($action === 'records' && $method === 'GET') {
        $query = $_GET['q'] ?? '';
        $page = $_GET['page'] ?? '1';
        if (!is_string($query) || mb_strlen($query) > 120 || !is_string($page) || !ctype_digit($page)) {
            respond(['error' => 'Búsqueda inválida.'], 400);
        }
        respond((new PersonRepository(Database::connect()))->search(trim($query), (int)$page));
    }
    if (!in_array($action, ['analyze', 'anonymize', 'validate', 'save', 'delete', 'constraints'], true)) {
        respond(['error' => 'Acción desconocida.'], 404);
    }
    if ($method !== 'POST') {
        header('Allow: POST');
        respond(['error' => 'Esta operación requiere POST.'], 405);
    }
    switch ($action) {
        case 'analyze':
        case 'anonymize':
            if (!isset($input['text']) || !is_string($input['text'])) {
                respond(['error' => 'Ingresa un texto para analizar.'], 422);
            }
            respond(TextLab::analyze($input['text']));
        case 'validate':
        case 'save':
            $validation = Validation::person($input);
            if ($action === 'validate') {
                respond($validation);
            }
            if (!$validation['valid']) {
                respond(['error' => 'Corrige los campos indicados antes de guardar.', 'validation' => $validation], 422);
            }
            $id = (new PersonRepository(Database::connect()))->create($validation['data']);
            respond(['message' => 'Registro guardado. Los datos se almacenaron normalizados.', 'id' => $id, 'validation' => $validation], 201);
        case 'delete':
            if (!isset($input['id']) || !is_int($input['id']) || $input['id'] < 1) {
                respond(['error' => 'Identificador inválido.'], 422);
            }
            $deleted = (new PersonRepository(Database::connect()))->delete($input['id']);
            respond($deleted ? ['message' => 'Registro eliminado.'] : ['error' => 'El registro ya no existe.'], $deleted ? 200 : 404);
        case 'constraints':
            $case = $input['case'] ?? '';
            if (!is_string($case) || !isset(ConstraintLab::CASES[$case])) {
                respond(['error' => 'Selecciona una de las tres pruebas disponibles.'], 422);
            }
            respond(ConstraintLab::run(Database::connect(), $case));
    }
} catch (PDOException $error) {
    logTechnicalError($error, 'base de datos');
    respond(['error' => Database::userMessage($error)], Database::constraintType($error) === 'unique' ? 409 : 503);
} catch (InvalidArgumentException $error) {
    respond(['error' => $error->getMessage()], 422);
} catch (Throwable $error) {
    logTechnicalError($error, 'solicitud');
    respond(['error' => 'No se pudo completar la solicitud. Revisa la configuración y el registro de errores del servidor.'], 500);
}
