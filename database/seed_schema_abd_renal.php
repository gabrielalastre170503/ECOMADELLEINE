<?php
/**
 * Ejecutar UNA SOLA VEZ para subir el esquema de Ecografía Abdominal/Renal (id=4).
 * Acceder desde: http://localhost/Sistema_EcoMadelleineV1/database/seed_schema_abd_renal.php
 */
/* Guarda: escribe en la base de datos, solo por linea de comandos.
   Sin esto, cualquiera podia dispararlo desde el navegador. */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este script solo puede ejecutarse por linea de comandos.');
}
include __DIR__ . '/../core/conexion.php';

$schema = [
    'version' => 1,
    'secciones' => [

        /* ─── 1. Encabezado ─── */
        [
            'id'    => 'encabezado',
            'titulo'=> 'Datos del Paciente',
            'icono' => 'fa-solid fa-id-card',
            'campos'=> [
                ['nombre'=>'nombres_apellidos','etiqueta'=>'Nombres y Apellidos','tipo'=>'text',    'ancho'=>'completo','requerido'=>true],
                ['nombre'=>'edad',             'etiqueta'=>'Edad',               'tipo'=>'number',  'ancho'=>'tercio',  'unidad'=>'años','min'=>0,          'readonly'=>true],
                ['nombre'=>'cedula',           'etiqueta'=>'Cédula (CI)',         'tipo'=>'text',    'ancho'=>'tercio',                                             'readonly'=>true],
                ['nombre'=>'fecha',            'etiqueta'=>'Fecha',              'tipo'=>'date',    'ancho'=>'tercio',  'requerido'=>true,                         'readonly'=>true],
                ['nombre'=>'motivo_consulta',  'etiqueta'=>'Motivo de Consulta', 'tipo'=>'textarea','ancho'=>'completo','filas'=>3,'requerido'=>true],
            ],
        ],

        /* ─── 2. Hígado ─── */
        [
            'id'    => 'higado',
            'titulo'=> 'Hígado',
            'icono' => 'fa-solid fa-droplet',
            'campos'=> [
                ['nombre'=>'parenquima',                'etiqueta'=>'Parénquima',                  'tipo'=>'radio',      'opciones'=>['Homogéneo','Heterogéneo'],   'ancho'=>'medio'],
                ['nombre'=>'bordes',                    'etiqueta'=>'Bordes',                      'tipo'=>'radio',      'opciones'=>['Regulares','Irregulares'],   'ancho'=>'medio'],
                ['nombre'=>'tamano',                    'etiqueta'=>'Tamaño',                      'tipo'=>'radio',      'opciones'=>['Normal','Aumentado'],        'ancho'=>'medio'],
                ['nombre'=>'ecogenicidad_aumentada',    'etiqueta'=>'Ecogenicidad Aumentada',      'tipo'=>'radio_sinno','ancho'=>'medio'],
                ['nombre'=>'lobulos_definidos',         'etiqueta'=>'Lóbulos definidos',           'tipo'=>'radio_sinno','ancho'=>'medio'],
                ['nombre'=>'patron_vascular_conservado','etiqueta'=>'Patrón vascular conservado',  'tipo'=>'radio_sinno','ancho'=>'medio'],
                ['nombre'=>'hepatometria_lobulo_der',   'etiqueta'=>'Hepatometría — Lóbulo Derecho','tipo'=>'number',    'unidad'=>'mm','ancho'=>'medio'],
                ['nombre'=>'hepatometria_lobulo_izq',   'etiqueta'=>'Hepatometría — Lóbulo Izquierdo','tipo'=>'number',  'unidad'=>'mm','ancho'=>'medio'],
            ],
        ],

        /* ─── 3. Vías Biliares ─── */
        [
            'id'    => 'vias_biliares',
            'titulo'=> 'Vías Biliares',
            'icono' => 'fa-solid fa-water',
            'campos'=> [
                ['nombre'=>'intrahepaticas_dilatadas',  'etiqueta'=>'Intrahepáticas dilatadas',      'tipo'=>'radio_sinno','ancho'=>'medio'],
                ['nombre'=>'coledoco_dilatado',         'etiqueta'=>'Colédoco dilatado',             'tipo'=>'radio_sinno','ancho'=>'medio'],
                ['nombre'=>'hepatocoledoco_dilatado',   'etiqueta'=>'Hepatocolédoco dilatado',       'tipo'=>'radio_sinno','ancho'=>'medio'],
                ['nombre'=>'vesicula_distendida',       'etiqueta'=>'Vesícula Distendida',           'tipo'=>'radio_sinno','ancho'=>'medio'],
                ['nombre'=>'vesicula_paredes_delgadas', 'etiqueta'=>'Paredes delgadas de vesícula',  'tipo'=>'radio_sinno','ancho'=>'completo'],
                ['nombre'=>'vesicula_medida_l',         'etiqueta'=>'Vesícula biliar — L',           'tipo'=>'number',    'unidad'=>'mm','ancho'=>'medio'],
                ['nombre'=>'vesicula_medida_t',         'etiqueta'=>'Vesícula biliar — T',           'tipo'=>'number',    'unidad'=>'mm','ancho'=>'medio'],
                ['nombre'=>'litiasis_en_interior',      'etiqueta'=>'Imágenes de litiasis en su interior',    'tipo'=>'radio_sinno','ancho'=>'medio'],
                ['nombre'=>'litiasis_tamano',           'etiqueta'=>'Litiasis — Medida',                      'tipo'=>'number',    'unidad'=>'mm','ancho'=>'medio',
                 'depende_de'=>'litiasis_en_interior','depende_valor'=>'SI'],
            ],
        ],

        /* ─── 4. Páncreas ─── */
        [
            'id'    => 'pancreas',
            'titulo'=> 'Páncreas',
            'icono' => 'fa-solid fa-stethoscope',
            'campos'=> [
                ['nombre'=>'parenquima',    'etiqueta'=>'Parénquima',   'tipo'=>'radio',      'opciones'=>['Homogéneo','Heterogéneo'],'ancho'=>'medio'],
                ['nombre'=>'lesiones_focales','etiqueta'=>'Lesiones focales','tipo'=>'radio_sinno','ancho'=>'medio'],
                ['nombre'=>'cabeza',        'etiqueta'=>'Cabeza',       'tipo'=>'number','unidad'=>'mm','ancho'=>'tercio'],
                ['nombre'=>'cuerpo',        'etiqueta'=>'Cuerpo',       'tipo'=>'number','unidad'=>'mm','ancho'=>'tercio'],
                ['nombre'=>'cola',          'etiqueta'=>'Cola',         'tipo'=>'number','unidad'=>'mm','ancho'=>'tercio'],
            ],
        ],

        /* ─── 5. Aorta ─── */
        [
            'id'    => 'aorta',
            'titulo'=> 'Aorta',
            'icono' => 'fa-solid fa-heart-pulse',
            'campos'=> [
                ['nombre'=>'posicion_prevertebral','etiqueta'=>'Posición prevertebral','tipo'=>'radio_sinno','ancho'=>'medio'],
                ['nombre'=>'diametro',            'etiqueta'=>'Diámetro',             'tipo'=>'number','unidad'=>'mm','ancho'=>'tercio'],
            ],
        ],

        /* ─── 6. Bazo ─── */
        [
            'id'    => 'bazo',
            'titulo'=> 'Bazo',
            'icono' => 'fa-solid fa-circle-half-stroke',
            'campos'=> [
                ['nombre'=>'aspecto_conservado','etiqueta'=>'Aspecto y ecoestructura conservada','tipo'=>'radio_sinno','ancho'=>'completo'],
                ['nombre'=>'medida_l',          'etiqueta'=>'Medida L','tipo'=>'number','unidad'=>'mm','ancho'=>'medio'],
                ['nombre'=>'medida_t',          'etiqueta'=>'Medida T','tipo'=>'number','unidad'=>'mm','ancho'=>'medio'],
            ],
        ],

        /* ─── 7. Riñones (par: Derecho / Izquierdo) ─── */
        [
            'id'          => 'rinones',
            'titulo'      => 'Riñones',
            'icono'       => 'fa-solid fa-shield-halved',
            'tipo_seccion'=> 'par',
            'lados'       => ['Riñón Derecho','Riñón Izquierdo'],
            'ids_lados'   => ['rinon_der','rinon_izq'],
            'campos'      => [
                ['nombre'=>'situacion_tamano_normal','etiqueta'=>'Situación y tamaño normal','tipo'=>'radio_sinno','ancho'=>'completo'],
                ['nombre'=>'medida_l',              'etiqueta'=>'Medida L',                  'tipo'=>'number','unidad'=>'mm','ancho'=>'medio'],
                ['nombre'=>'medida_ap',             'etiqueta'=>'Medida AP',                 'tipo'=>'number','unidad'=>'mm','ancho'=>'medio'],
                ['nombre'=>'medida_t',              'etiqueta'=>'Medida T',                  'tipo'=>'number','unidad'=>'mm','ancho'=>'medio'],
                ['nombre'=>'tipo_litiasis',         'etiqueta'=>'Tipo de imagen',            'tipo'=>'radio',
                 'opciones'=>['Litiasis','Microlitiasis'],                                                     'ancho'=>'completo'],
                ['nombre'=>'litiasis_medida',       'etiqueta'=>'Litiasis — Medida',         'tipo'=>'number','unidad'=>'mm','ancho'=>'completo',
                 'depende_de'=>'tipo_litiasis','depende_valor'=>'Litiasis'],
                ['nombre'=>'microlitiasis_medida',  'etiqueta'=>'Microlitiasis — Medida',    'tipo'=>'number','unidad'=>'mm','ancho'=>'completo',
                 'depende_de'=>'tipo_litiasis','depende_valor'=>'Microlitiasis'],
                ['nombre'=>'quiste_l',              'etiqueta'=>'Quiste L',                  'tipo'=>'number','unidad'=>'mm','ancho'=>'medio'],
                ['nombre'=>'quiste_ap',             'etiqueta'=>'Quiste AP',                 'tipo'=>'number','unidad'=>'mm','ancho'=>'medio'],
                ['nombre'=>'quiste_t',              'etiqueta'=>'Quiste T',                  'tipo'=>'number','unidad'=>'mm','ancho'=>'medio'],
                ['nombre'=>'quiste_volumen',        'etiqueta'=>'Quiste Volumen',            'tipo'=>'number','unidad'=>'ml','ancho'=>'medio'],
                ['nombre'=>'imagenes_tumorales',    'etiqueta'=>'Imágenes tumorales',        'tipo'=>'radio_sinno','ancho'=>'completo'],
                ['nombre'=>'colecciones_liquidas',  'etiqueta'=>'Colecciones líquidas',      'tipo'=>'radio_sinno','ancho'=>'completo'],
                ['nombre'=>'ureter_visible',        'etiqueta'=>'Uréter visible',            'tipo'=>'radio_sinno','ancho'=>'completo'],
            ],
        ],

        /* ─── 8. Vejiga ─── */
        [
            'id'    => 'vejiga',
            'titulo'=> 'Vejiga',
            'icono' => 'fa-solid fa-circle-dot',
            'campos'=> [
                ['nombre'=>'paredes_medida',      'etiqueta'=>'Paredes — Medida',      'tipo'=>'number',     'unidad'=>'mm','ancho'=>'medio'],
                ['nombre'=>'litiasis_mm',         'etiqueta'=>'Litiasis',              'tipo'=>'number',     'unidad'=>'mm','ancho'=>'medio'],
                ['nombre'=>'bordes_regulares',    'etiqueta'=>'Bordes regulares',      'tipo'=>'radio_sinno','ancho'=>'sexto'],
                ['nombre'=>'sedimentos',          'etiqueta'=>'Sedimentos',            'tipo'=>'radio_sinno','ancho'=>'sexto'],
                ['nombre'=>'imagenes_patologicas','etiqueta'=>'Imágenes patológicas',  'tipo'=>'radio_sinno','ancho'=>'sexto'],
            ],
        ],

        /* ─── 9. Espacio de Morrison ─── */
        [
            'id'    => 'morrison',
            'titulo'=> 'Espacio de Morrison',
            'icono' => 'fa-solid fa-magnifying-glass',
            'campos'=> [
                ['nombre'=>'libre','etiqueta'=>'Libre','tipo'=>'radio_sinno','ancho'=>'medio'],
            ],
        ],

        /* ─── 10. Digestivo ─── */
        [
            'id'    => 'digestivo',
            'titulo'=> 'Digestivo',
            'icono' => 'fa-solid fa-wave-square',
            'campos'=> [
                ['nombre'=>'gas_forma',          'etiqueta'=>'Gas en forma',         'tipo'=>'text',  'ancho'=>'medio'],
                ['nombre'=>'imagenes_patologicas','etiqueta'=>'Imágenes patológicas', 'tipo'=>'radio_sinno','ancho'=>'medio'],
                ['nombre'=>'asas_intestinales',  'etiqueta'=>'Asas intestinales',    'tipo'=>'radio',
                 'opciones'=>['Normal','Levemente distendidas','Moderadamente distendidas','Severamente distendidas'],
                 'ancho'=>'completo'],
                ['nombre'=>'predominio',         'etiqueta'=>'Predominio',           'tipo'=>'radio',
                 'opciones'=>['Ascendente','Transverso','Descendente'],
                 'ancho'=>'completo'],
            ],
        ],

        /* ─── 11. Conclusión y Diagnóstico ─── */
        [
            'id'    => 'conclusion',
            'titulo'=> 'Conclusión y Diagnóstico',
            'icono' => 'fa-solid fa-file-medical',
            'campos'=> [
                ['nombre'=>'conclusion',   'etiqueta'=>'Conclusión',   'tipo'=>'textarea','filas'=>5,'ancho'=>'completo','requerido'=>true],
                ['nombre'=>'diagnostico_1','etiqueta'=>'Diagnóstico 1','tipo'=>'textarea','filas'=>3,'ancho'=>'completo'],
            ],
        ],
    ],
];

$json = json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

$stmt = $conex->prepare("UPDATE tipos_ecografias SET esquema_campos = ?, esquema_version = esquema_version + 1 WHERE id = 4");
$stmt->bind_param('s', $json);

if ($stmt->execute()) {
    echo '<pre style="font-family:monospace;background:#f6ffed;padding:20px;border-radius:8px;">';
    echo '<strong style="color:#15803d;">✔ Esquema actualizado correctamente para Ecografía Abdominal/Renal (id=4)</strong>' . "\n\n";
    echo 'Secciones: ' . count($schema['secciones']) . "\n";
    foreach ($schema['secciones'] as $s) {
        $c = count($s['campos'] ?? []);
        echo '  • ' . $s['titulo'] . ' — ' . $c . ' campo(s)';
        if (($s['tipo_seccion'] ?? '') === 'par') echo ' [PAR: ' . implode(' / ', $s['lados']) . ']';
        echo "\n";
    }
    echo '</pre>';
} else {
    echo '<p style="color:red;">Error: ' . $conex->error . '</p>';
}
$stmt->close();
$conex->close();
