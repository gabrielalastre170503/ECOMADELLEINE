<?php
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

api_json();

$response = ['success' => false, 'message' => 'Acción no permitida.'];

// Seguridad: Solo administradores
if (api_rol() !== 'administrador') {
    api_fail('Acción no permitida.', 403);
}

// Habilitar/inhabilitar una cuenta es una escritura sensible: exige token.
// Los dos llamadores usan fetch(), y el envoltorio de shell.php ya adjunta
// la cabecera X-CSRF-Token en cada peticion al propio origen.
api_require_csrf();

// Usamos POST para recibir los datos de JavaScript
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'], $_POST['nuevo_estado'])) {
    $usuario_id = $_POST['id'];
    $nuevo_estado = $_POST['nuevo_estado'];

    // Validar que el nuevo estado sea uno de los permitidos
    if (!in_array($nuevo_estado, ['aprobado', 'inhabilitado'])) {
        $response['message'] = 'Estado no válido.';
        echo json_encode($response);
        exit();
    }

    // Seguridad: El administrador no puede inhabilitarse a sí mismo
    if ($usuario_id == api_uid()) {
        $response['message'] = 'No puedes cambiar tu propio estado.';
        echo json_encode($response);
        exit();
    }

    // Actualizar el estado del usuario en la base de datos
    $stmt = $conex->prepare("UPDATE usuarios SET estado = ? WHERE id = ?");
    $stmt->bind_param("si", $nuevo_estado, $usuario_id);

    if ($stmt->execute()) {
        eco_auditar($conex, 'usuario_estado_cambiado', ['entidad' => 'usuario', 'entidad_id' => $usuario_id, 'detalle' => ['estado' => $nuevo_estado]]);
        $response['success'] = true;
        $response['message'] = 'Estado del usuario actualizado correctamente.';
    } else {
        $response['message'] = 'Error al actualizar la base de datos.';
    }
    $stmt->close();
}

$conex->close();
echo json_encode($response);
