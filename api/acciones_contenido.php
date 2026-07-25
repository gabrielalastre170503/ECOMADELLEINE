<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    header('Location: ' . eco_url('login'));
    exit();
}

// Todas las ramas de abajo escriben en la BD: sin token, un sitio externo podia
// dar de alta o borrar contenido usando la sesion del administrador.
require_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    $accion = $_POST['accion'];
    $tipo   = $_POST['tipo'] ?? '';

    if ($accion === 'agregar' && $tipo === 'faq') {
        $pregunta  = trim($_POST['pregunta']  ?? '');
        $respuesta = trim($_POST['respuesta'] ?? '');
        $stmt = $conex->prepare("INSERT INTO faqs (pregunta, respuesta) VALUES (?, ?)");
        $stmt->bind_param("ss", $pregunta, $respuesta);
        $ok = $stmt->execute();
        $stmt->close();
        header('Location: ' . eco_url('gestionar-faq') . '?status=' . ($ok ? 'added' : 'error'));
        exit();
    }

    if ($accion === 'actualizar' && $tipo === 'textos_web') {
        $mision  = $_POST['mision']  ?? '';
        $vision  = $_POST['vision']  ?? '';
        $valores = $_POST['valores'] ?? '';
        $stmt = $conex->prepare("INSERT INTO contenido_web (clave, valor) VALUES ('mision', ?), ('vision', ?), ('valores', ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        $stmt->bind_param("sss", $mision, $vision, $valores);
        $ok = $stmt->execute();
        $stmt->close();
        header('Location: ' . eco_url('gestionar-textos') . '?status=' . ($ok ? 'updated' : 'error'));
        exit();
    }

    if ($accion === 'agregar' && $tipo === 'eco_tipo') {
        $nombre      = trim($_POST['nombre'] ?? '');
        $codigo      = trim($_POST['codigo'] ?? '');
        $categoria   = trim($_POST['categoria'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $icono       = trim($_POST['icono'] ?? '') ?: 'fa-solid fa-wave-square';

        if ($nombre === '') {
            header('Location: ' . eco_url('gestionar-estudios') . '?status=error');
            exit();
        }

        if ($codigo === '') {
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $nombre));
            $codigo = strtoupper(trim($slug, '_'));
        }

        $esquema = json_encode(['version' => 1, 'secciones' => []], JSON_UNESCAPED_UNICODE);
        $stmt = $conex->prepare(
            "INSERT INTO tipos_ecografias (codigo, nombre, categoria, descripcion, icono, esquema_campos, activo)
             VALUES (?, ?, ?, ?, ?, ?, 1)"
        );
        $stmt->bind_param('ssssss', $codigo, $nombre, $categoria, $descripcion, $icono, $esquema);
        $ok = $stmt->execute();
        $stmt->close();
        header('Location: ' . eco_url('gestionar-estudios') . '?status=' . ($ok ? 'added' : 'error'));
        exit();
    }
}

// Acciones destructivas por enlace (GET). El token viaja en la URL porque
// require_csrf() solo mira $_POST y la cabecera X-CSRF-Token.
if (isset($_GET['accion'])) {
    $accion = $_GET['accion'];
    $tipo   = $_GET['tipo'] ?? '';
    $id     = (int)($_GET['id'] ?? 0);

    if (!csrf_validate($_GET['csrf_token'] ?? '')) {
        header('Location: ' . eco_url('contenido') . '?status=error');
        exit();
    }

    if ($accion === 'borrar' && $tipo === 'faq' && $id > 0) {
        $stmt = $conex->prepare("DELETE FROM faqs WHERE id = ?");
        $stmt->bind_param("i", $id);
        $ok = $stmt->execute();
        $stmt->close();
        header('Location: ' . eco_url('gestionar-faq') . '?status=' . ($ok ? 'deleted' : 'error'));
        exit();
    }

    // Antes esta rama exigia accion === 'desactivar' dentro de un if que ya
    // habia filtrado por 'borrar': nunca se ejecutaba y desactivar un estudio
    // no hacia nada.
    if ($accion === 'desactivar' && $tipo === 'eco_tipo' && $id > 0) {
        $stmt = $conex->prepare("UPDATE tipos_ecografias SET activo = 0 WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        $stmt->close();
        header('Location: ' . eco_url('gestionar-estudios') . '?status=' . ($ok ? 'deleted' : 'error'));
        exit();
    }
}

$conex->close();
header('Location: ' . eco_url('contenido'));
exit();
