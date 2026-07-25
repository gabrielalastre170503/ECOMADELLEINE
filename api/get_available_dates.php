<?php
session_start();
require_once __DIR__ . '/../lib/core/api.php';
include __DIR__ . '/../core/conexion.php';

api_json();

// Seguridad: solo se consulta desde solicitar_cita_paciente.php, con sesion.
api_require_login();

if (!isset($_GET['ecografista_id'])) {
    http_response_code(400);
    exit();
}

$ecografista_id = (int)$_GET['ecografista_id'];

// Obtener todas las citas confirmadas para este psicólogo
$citas_confirmadas = [];
$stmt_citas = $conex->prepare("SELECT fecha_cita FROM citas WHERE ecografista_id = ? AND estado IN ('confirmada', 'reprogramada')");
$stmt_citas->bind_param("i", $ecografista_id);
$stmt_citas->execute();
$resultado_citas = $stmt_citas->get_result();
while ($fila = $resultado_citas->fetch_assoc()) {
    $citas_confirmadas[date('Y-m-d H:i:s', strtotime($fila['fecha_cita']))] = true;
}
$stmt_citas->close();

// Obtener días no disponibles (excepciones)
$dias_no_disponibles = [];
$stmt_excepciones = $conex->prepare("SELECT fecha FROM disponibilidad_excepciones WHERE ecografista_id = ? AND tipo = 'no_disponible'");
$stmt_excepciones->bind_param("i", $ecografista_id);
$stmt_excepciones->execute();
$resultado_excepciones = $stmt_excepciones->get_result();
while ($fila = $resultado_excepciones->fetch_assoc()) {
    $dias_no_disponibles[$fila['fecha']] = true;
}
$stmt_excepciones->close();

// Obtener horario semanal
$horario_semanal = [];
$stmt_horario = $conex->prepare("SELECT dia_semana, hora_inicio, hora_fin FROM horarios_recurrentes WHERE ecografista_id = ?");
$stmt_horario->bind_param("i", $ecografista_id);
$stmt_horario->execute();
$resultado_horario = $stmt_horario->get_result();
while ($fila = $resultado_horario->fetch_assoc()) {
    $horario_semanal[$fila['dia_semana']] = ['inicio' => $fila['hora_inicio'], 'fin' => $fila['hora_fin']];
}
$stmt_horario->close();

// Calcular fechas disponibles en los próximos 90 días
$fechas_disponibles = [];
$fecha_inicio = new DateTime('today');
$fecha_fin = (new DateTime('today'))->modify('+360 days');
$intervalo_dia = new DateInterval('P1D');
$periodo = new DatePeriod($fecha_inicio, $intervalo_dia, $fecha_fin);

foreach ($periodo as $fecha) {
    $dia_semana = $fecha->format('N');
    $fecha_str = $fecha->format('Y-m-d');

    if (isset($dias_no_disponibles[$fecha_str]) || !isset($horario_semanal[$dia_semana])) {
        continue;
    }

    $hora_inicio = new DateTime($fecha_str . ' ' . $horario_semanal[$dia_semana]['inicio']);
    $hora_fin = new DateTime($fecha_str . ' ' . $horario_semanal[$dia_semana]['fin']);
    $intervalo_hora = new DateInterval('PT60M');

    for ($hora_actual = clone $hora_inicio; $hora_actual < $hora_fin; $hora_actual->add($intervalo_hora)) {
        $slot_str = $hora_actual->format('Y-m-d H:i:s');
        if (!isset($citas_confirmadas[$slot_str])) {
            $fechas_disponibles[] = $fecha_str;
            break; // Encontramos un hueco, el día es disponible, pasamos al siguiente día
        }
    }
}

echo json_encode($fechas_disponibles);
$conex->close();
?>