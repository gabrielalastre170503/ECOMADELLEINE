<?php
session_start();
include __DIR__ . '/../core/conexion.php';

// Seguridad: Solo secretarias y administradores
if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['recepcionista', 'administrador'])) {
    exit('Acceso denegado');
}

// Verifica conexión
if (!$conex) {
    exit('Error de conexión a la base de datos.');
}

$termino_busqueda = isset($_POST['query']) ? trim($_POST['query']) : '';
$busqueda = "%" . $termino_busqueda . "%";

// Consulta que busca por nombre de paciente, profesional o cédula del paciente
$sql = "SELECT 
            c.fecha_cita, 
            c.estado,
            paciente.nombre_completo as paciente_nombre,
            paciente.cedula as paciente_cedula,
            psicologo.nombre_completo as psicologo_nombre
        FROM citas c
        JOIN usuarios paciente ON c.paciente_id = paciente.id
        LEFT JOIN usuarios psicologo ON c.ecografista_id = psicologo.id
        WHERE paciente.nombre_completo LIKE ? OR psicologo.nombre_completo LIKE ? OR paciente.cedula LIKE ?
        ORDER BY c.fecha_cita DESC";

$stmt = $conex->prepare($sql);
if (!$stmt) {
    exit('Error en la preparación de la consulta.');
}
$stmt->bind_param("sss", $busqueda, $busqueda, $busqueda);
$stmt->execute();
$resultado = $stmt->get_result();

// Construimos la tabla de resultados
if ($resultado && $resultado->num_rows > 0) {
    echo "<table class='approvals-table'><thead><tr><th class='sortable-header'>Paciente</th><th class='sortable-header'>Cédula</th><th class='sortable-header'>Profesional Asignado</th><th class='sortable-header'>Fecha Programada</th><th class='sortable-header'>Estado</th></tr></thead><tbody>";
    while($cita = $resultado->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($cita['paciente_nombre']) . "</td>";
        echo "<td>" . htmlspecialchars($cita['paciente_cedula']) . "</td>";
        echo "<td>" . htmlspecialchars($cita['psicologo_nombre'] ?? 'No Asignado') . "</td>";
        echo "<td>" . ($cita['fecha_cita'] ? htmlspecialchars(date('d/m/Y h:i A', strtotime($cita['fecha_cita']))) : 'N/A') . "</td>";
        echo "<td><span class='status-badge status-" . htmlspecialchars($cita['estado']) . "'>" . htmlspecialchars(ucfirst($cita['estado'])) . "</span></td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p>No se encontraron citas que coincidan con tu búsqueda.</p>";
}
$stmt->close();
$conex->close();
?>