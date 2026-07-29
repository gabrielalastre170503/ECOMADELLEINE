<?php
session_start();
include __DIR__ . '/../core/conexion.php';

// Seguridad
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    exit('Acceso denegado');
}

require_once __DIR__ . '/../lib/core/paginacion.php';
[$page, $perPage, $offset] = eco_paginacion_args(25);

$termino_busqueda = isset($_POST['query']) ? $_POST['query'] : '';
$busqueda = "%" . $termino_busqueda . "%";

$where = "WHERE paciente.nombre_completo LIKE ? OR psicologo.nombre_completo LIKE ? OR paciente.cedula LIKE ?";

// Total para la paginación (mismo WHERE).
$total = 0;
$cstmt = $conex->prepare("SELECT COUNT(*) AS n
        FROM citas c
        JOIN usuarios paciente ON c.paciente_id = paciente.id
        LEFT JOIN usuarios psicologo ON c.ecografista_id = psicologo.id
        $where");
$cstmt->bind_param("sss", $busqueda, $busqueda, $busqueda);
$cstmt->execute();
$total = (int)($cstmt->get_result()->fetch_assoc()['n'] ?? 0);
$cstmt->close();

// Consulta actualizada para incluir la cédula del paciente
$sql = "SELECT
            c.id, c.fecha_cita, c.estado,
            paciente.nombre_completo as paciente_nombre,
            paciente.cedula as paciente_cedula, -- <-- Campo añadido
            psicologo.nombre_completo as psicologo_nombre
        FROM citas c
        JOIN usuarios paciente ON c.paciente_id = paciente.id
        LEFT JOIN usuarios psicologo ON c.ecografista_id = psicologo.id
        $where
        ORDER BY c.fecha_solicitud DESC
        LIMIT ? OFFSET ?";

$stmt = $conex->prepare($sql);
$stmt->bind_param("sssii", $busqueda, $busqueda, $busqueda, $perPage, $offset);
$stmt->execute();
$resultado = $stmt->get_result();

// Construimos la tabla de resultados
if ($resultado->num_rows > 0) {
    echo "<table class='users-table'><thead><tr><th class='sortable-header'>Paciente</th><th class='sortable-header'>Cédula</th><th class='sortable-header'>Profesional Asignado</th><th class='sortable-header'>Fecha Programada</th><th class='sortable-header'>Estado</th><th>Acciones</th></tr></thead><tbody>";
    while($cita = $resultado->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($cita['paciente_nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($cita['paciente_cedula']) . "</td>"; // <-- Nuevo campo
        echo "<td>" . htmlspecialchars($cita['psicologo_nombre'] ?? 'No Asignado') . "</td>";
        echo "<td>" . ($cita['fecha_cita'] ? htmlspecialchars(date('d/m/Y h:i A', strtotime($cita['fecha_cita']))) : 'N/A') . "</td>";
        echo "<td><span class='status-badge status-" . htmlspecialchars($cita['estado']) . "'>" . htmlspecialchars(ucfirst($cita['estado'])) . "</span></td>";
        // Ruta absoluta: la tabla se inyecta por AJAX en /citas-admin y una
        // acción relativa se resolvería contra esa página, dando 404.
        echo "<td class='action-links'><form method='post' action='" . htmlspecialchars(eco_url('api/borrar_cita_admin.php')) . "' style='display:inline' onsubmit=\"return confirm('¿Estás seguro de que quieres eliminar esta cita permanentemente?');\">" . csrf_field() . "<input type='hidden' name='id' value='" . (int)$cita['id'] . "'><button type='submit' class='reject'><i class='fa-solid fa-trash'></i> Eliminar</button></form></td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p>No se encontraron citas que coincidan con tu búsqueda.</p>";
}
echo eco_paginacion_html($page, $perPage, $total, 'citas');
$stmt->close();
$conex->close();
?>