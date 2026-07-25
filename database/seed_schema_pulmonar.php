<?php
/**
 * Ejecutar UNA SOLA VEZ para crear el tipo "Ecografía Pulmonar" con su esquema
 * LITERAL extraído del documento físico Dra. Madelleine Toro.
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_pulmonar.php
 */
/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
include __DIR__ . '/../core/conexion.php';

/* Opciones de hallazgo por ventana (0–4) */
$opciones_ventana = [
    '0 — Líneas tipo A (deslizamiento pulmonar)',
    '1 — Líneas tipo B separadas (B7)',
    '2 — Líneas tipo B juntas (B3)',
    '3 — Líneas B confluentes (variante de Birolleau)',
    '4 — Consolidados',
];

/* Campos comunes a ambos pulmones (par: Derecho / Izquierdo) — 6 ventanas */
$campos_pulmon = [
    ['nombre' => 'antero_superior',  'etiqueta' => 'Antero-Superior',  'tipo' => 'select', 'opciones' => $opciones_ventana, 'ancho' => 'completo'],
    ['nombre' => 'antero_inferior',  'etiqueta' => 'Antero-Inferior',  'tipo' => 'select', 'opciones' => $opciones_ventana, 'ancho' => 'completo'],
    ['nombre' => 'latero_superior',  'etiqueta' => 'Latero-Superior',  'tipo' => 'select', 'opciones' => $opciones_ventana, 'ancho' => 'completo'],
    ['nombre' => 'latero_inferior',  'etiqueta' => 'Latero-Inferior',  'tipo' => 'select', 'opciones' => $opciones_ventana, 'ancho' => 'completo'],
    ['nombre' => 'postero_superior', 'etiqueta' => 'Póstero-Superior', 'tipo' => 'select', 'opciones' => $opciones_ventana, 'ancho' => 'completo'],
    ['nombre' => 'postero_inferior', 'etiqueta' => 'Póstero-Inferior', 'tipo' => 'select', 'opciones' => $opciones_ventana, 'ancho' => 'completo'],
];

$schema = [
    'version'   => 1,
    'secciones' => [

        /* ─── 1. Encabezado ─── */
        [
            'id'        => 'encabezado',
            'titulo'    => 'Datos del Paciente',
            'icono'     => 'fa-solid fa-id-card',
            'subtitulo' => 'Se realiza exploración anterior, lateral y posterior en las ventanas Pleuro-Pulmonares de ambos pulmones y excursión diafragmática con transductor lineal multifrecuencia de 7.5 a 10 MHz, observando:',
            'campos'    => [
                ['nombre' => 'nombres_apellidos', 'etiqueta' => 'Nombres y Apellidos', 'tipo' => 'text',     'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'edad',              'etiqueta' => 'Edad',                'tipo' => 'number',   'ancho' => 'tercio',   'unidad' => 'años', 'min' => 0, 'readonly' => true],
                ['nombre' => 'cedula',            'etiqueta' => 'Cédula (CI)',         'tipo' => 'text',     'ancho' => 'tercio',   'readonly' => true],
                ['nombre' => 'fecha',             'etiqueta' => 'Fecha',               'tipo' => 'date',     'ancho' => 'tercio',   'requerido' => true, 'readonly' => true],
                ['nombre' => 'motivo_consulta',   'etiqueta' => 'Motivo de Consulta',  'tipo' => 'textarea', 'ancho' => 'completo', 'filas' => 2, 'requerido' => true],
            ],
        ],

        /* ─── 2. Excursión Diafragmática ─── */
        [
            'id'     => 'excursion_diafragmatica',
            'titulo' => 'Excursión Diafragmática',
            'icono'  => 'fa-solid fa-wave-square',
            'campos' => [
                ['nombre' => 'conservada', 'etiqueta' => 'Conservada', 'tipo' => 'radio_sinno', 'ancho' => 'completo'],
                ['nombre' => 'derecha',    'etiqueta' => 'Derecha',     'tipo' => 'text',        'ancho' => 'medio'],
                ['nombre' => 'izquierda',  'etiqueta' => 'Izquierda',   'tipo' => 'text',        'ancho' => 'medio'],
            ],
        ],

        /* ─── 3. Ventanas Pleuropulmonares (par: Derecho / Izquierdo) ─── */
        [
            'id'           => 'ventanas_pleuropulmonares',
            'titulo'       => 'Ventanas Pleuropulmonares',
            'icono'        => 'fa-solid fa-lungs',
            'tipo_seccion' => 'par',
            'lados'        => ['Pulmón Derecho', 'Pulmón Izquierdo'],
            'ids_lados'    => ['pulmon_der', 'pulmon_izq'],
            'campos'       => $campos_pulmon,
        ],

        /* ─── 4. Hallazgos Generales ─── */
        [
            'id'        => 'hallazgos_generales',
            'titulo'    => 'Hallazgos Generales',
            'icono'     => 'fa-solid fa-list-check',
            'subtitulo' => 'Códigos: 0 = Líneas A · 1 = B separadas (B7) · 2 = B juntas (B3) · 3 = B confluentes (Birolleau) · 4 = Consolidados',
            'campos'    => [
                ['nombre' => 'derrames',         'etiqueta' => 'Derrames',         'tipo' => 'radio_sinno', 'ancho' => 'medio'],
                ['nombre' => 'lineas_pleurales', 'etiqueta' => 'Líneas Pleurales', 'tipo' => 'radio',
                 'opciones' => ['Lisas', 'Engrosadas', 'Fragmentadas'], 'ancho' => 'completo'],
            ],
        ],

        /* ─── 5. Nota y Diagnósticos Ecográficos ─── */
        [
            'id'        => 'dx_ecograficos',
            'titulo'    => 'Diagnósticos Ecográficos',
            'icono'     => 'fa-solid fa-file-medical',
            'subtitulo' => 'Nota: Ecografía a correlacionar con clínica y estudios paraclínicos.',
            'campos'    => [
                ['nombre' => 'conclusion',     'etiqueta' => 'Hallazgos ecográficos sugestivos de', 'tipo' => 'textarea', 'filas' => 4, 'ancho' => 'completo', 'requerido' => true],
                ['nombre' => 'dx_ecograficos', 'etiqueta' => 'DX Ecográficos',                       'tipo' => 'textarea', 'filas' => 4, 'ancho' => 'completo'],
            ],
        ],
    ],
];

$json        = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$codigo      = 'ECO_PULMONAR';
$nombre      = 'Ecografía Pulmonar';
$categoria   = 'Pulmonar';
$descripcion = 'Informe de ecografía pulmonar: ventanas pleuropulmonares y excursión diafragmática.';
$icono       = 'fa-solid fa-lungs';
$posicion    = 12;

/* INSERT con upsert idempotente por codigo */
$sql = "INSERT INTO tipos_ecografias
            (codigo, nombre, categoria, descripcion, icono, esquema_campos, esquema_version, activo, posicion)
        VALUES (?, ?, ?, ?, ?, ?, 1, 1, ?)
        ON DUPLICATE KEY UPDATE
            nombre          = VALUES(nombre),
            categoria       = VALUES(categoria),
            descripcion     = VALUES(descripcion),
            icono           = VALUES(icono),
            esquema_campos  = VALUES(esquema_campos),
            esquema_version = esquema_version + 1,
            posicion        = VALUES(posicion)";

$stmt = $conex->prepare($sql);
$stmt->bind_param('ssssssi', $codigo, $nombre, $categoria, $descripcion, $icono, $json, $posicion);

echo '<style>body{font-family:monospace;background:#f6f8fa;padding:24px;}pre{background:#f0fff4;padding:20px;border-radius:8px;font-size:13px;}</style>';

if ($stmt->execute()) {
    $id_afectado = $stmt->insert_id > 0 ? ('INSERT id=' . $stmt->insert_id) : 'UPDATE existente';
    $stmt->close();

    echo '<pre>';
    echo '<strong style="color:#15803d;">✔ Ecografía Pulmonar registrada (literal al documento físico).</strong>' . "\n\n";
    echo 'Acción : ' . $id_afectado . "\n";
    echo 'Código : ' . $codigo . "\n";
    echo 'Secciones en esquema : ' . count($schema['secciones']) . "\n\n";
    $total = 0;
    foreach ($schema['secciones'] as $s) {
        $c   = count($s['campos'] ?? []);
        $par = (($s['tipo_seccion'] ?? '') === 'par') ? ' [PAR: ' . implode(' / ', $s['lados']) . ']' : '';
        $total += $c;
        echo '  • ' . $s['titulo'] . ' — ' . $c . ' campo(s)' . $par . "\n";
    }
    echo "\n<strong>Total de campos:</strong> " . $total . "\n";
    echo "\n<strong>Siguiente paso:</strong> Recarga (Ctrl+Shift+R), la nueva tarjeta \"Ecografía Pulmonar\" aparecerá en el grid de selección.\n";
    echo '</pre>';
} else {
    echo '<p style="color:red;font-size:14px;"><strong>Error:</strong> ' . htmlspecialchars($conex->error) . '</p>';
    $stmt->close();
}

$conex->close();
