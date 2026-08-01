<?php
session_start();
include __DIR__ . '/../core/conexion.php';
require_once __DIR__ . '/../lib/informes/estudios_render.php';
require_once __DIR__ . '/../lib/informes/archivos.php';
require_once __DIR__ . '/../lib/seguridad/seguridad.php';

if (!isset($_SESSION['usuario_id']) || !in_array($_SESSION['rol'], ['ecografista', 'administrador', 'recepcionista', 'paciente'])) {
    header('Location: ' . eco_url('login'));
    exit();
}

if (!isset($_GET['informe_id']) || !is_numeric($_GET['informe_id'])) {
    die("Error: Informe no especificado.");
}

$informe_id = (int)$_GET['informe_id'];

$sql = "SELECT
            inf.id, inf.numero_informe, inf.estado, inf.datos_clinicos, inf.esquema_version,
            inf.creado_en, inf.fecha_estudio, inf.medico_solicitante,
            inf.paciente_id, inf.ecografista_id, inf.tipo_ecografia_id,
            inf.fecha_firma, inf.firmado_por, inf.documento_sha256, inf.sello_firma,
            pac.nombre_completo AS paciente_nombre, pac.cedula AS paciente_cedula,
            TIMESTAMPDIFF(YEAR, pac.fecha_nacimiento, CURDATE()) AS paciente_edad,
            eco.nombre_completo AS ecografista_nombre, eco.cedula AS ecografista_cedula,
            fb.nombre_completo AS firmante_nombre,
            t.nombre AS tipo_nombre, t.categoria AS tipo_categoria, t.icono AS tipo_icono, t.esquema_campos
        FROM informes_estudios inf
        JOIN usuarios pac           ON pac.id = inf.paciente_id
        JOIN usuarios eco           ON eco.id = inf.ecografista_id
        LEFT JOIN usuarios fb       ON fb.id  = inf.firmado_por
        JOIN tipos_ecografias t     ON t.id   = inf.tipo_ecografia_id
        WHERE inf.id = ?";
$stmt = $conex->prepare($sql);
$stmt->bind_param("i", $informe_id);
$stmt->execute();
$informe = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$informe) {
    die("Error: Informe no encontrado.");
}

/* Esta página ES el informe clínico completo (hallazgos, mediciones,
   conclusiones y firma). Un único criterio decide quién puede verla, el mismo
   que usa la modal: eco_puede_ver_clinico(). Cubre de una vez el caso del
   paciente (solo lo suyo, finalizado o firmado) y el de la recepción, que
   antes entraba aquí y leía el informe entero. */
require_once __DIR__ . '/../lib/informes/informes.php';
if (!eco_puede_ver_clinico(
        (string)($_SESSION['rol'] ?? ''),
        (int)$_SESSION['usuario_id'],
        (int)$informe['paciente_id'],
        (string)$informe['estado'],
        $conex,
        (int)$informe['ecografista_id'])) {
    eco_auditar($conex, 'acceso_informe_denegado', [
        'entidad' => 'informe', 'entidad_id' => $informe_id,
    ]);
    http_response_code(403);
    die('No tienes acceso a este informe.');
}

// Bitácora de acceso a datos clínicos (cumplimiento): quién abrió este informe.
eco_auditar($conex, 'acceso_informe', [
    'entidad'    => 'informe',
    'entidad_id' => $informe_id,
    'detalle'    => ['paciente' => $informe['paciente_nombre'] ?? '', 'numero' => $informe['numero_informe'] ?? ''],
]);

$esquema = json_decode($informe['esquema_campos'], true) ?: ['secciones' => []];
$datos   = json_decode($informe['datos_clinicos'], true) ?: [];

/* Motivo de consulta: se captura en el formulario y vive en datos_clinicos
   (sección "encabezado"). No es medico_solicitante. */
$motivo_consulta = '';
if (!empty($datos['encabezado']['motivo_consulta'])) {
    $motivo_consulta = (string)$datos['encabezado']['motivo_consulta'];
} else {
    foreach ($datos as $sec) {
        if (is_array($sec) && !empty($sec['motivo_consulta'])) {
            $motivo_consulta = (string)$sec['motivo_consulta'];
            break;
        }
    }
}

// Imagenes ecograficas y adjuntos del estudio (Fase 3). Solo pantalla.
$archivos = eco_archivos_de_informe($conex, $informe_id);
$archivos_img = array_filter($archivos, static fn($a) => strpos((string)$a['mime'], 'image/') === 0);
$archivos_adj = array_filter($archivos, static fn($a) => strpos((string)$a['mime'], 'image/') !== 0);

$fecha_creado     = date('d/m/Y H:i', strtotime($informe['creado_en']));
$fecha_estudio_fmt = !empty($informe['fecha_estudio'])
    ? date('d/m/Y', strtotime($informe['fecha_estudio']))
    : date('d/m/Y', strtotime($informe['creado_en']));

/* Firma electrónica: el bloque sale en todo informe firmado. La huella/sello
   criptográfico (Fase 3c) solo existe en los firmados después de esa fase. */
$esta_firmado    = ($informe['estado'] === 'firmado');
$tiene_sello     = !empty($informe['documento_sha256']);
$firmante_nombre = $informe['firmante_nombre'] ?: $informe['ecografista_nombre'];
$fecha_firma_fmt = !empty($informe['fecha_firma'])
    ? date('d/m/Y H:i', strtotime($informe['fecha_firma']))
    : '';

/* Título centrado tipo "REPORTE ECOGRÁFICO RENAL" */
$titulo_print = mb_strtoupper('Reporte Ecográfico ' . preg_replace('/^Ecograf[ií]a\s+/i', '', $informe['tipo_nombre']), 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe <?php echo htmlspecialchars($informe['numero_informe']); ?> - EcoMadelleine</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&family=Playfair+Display:wght@600;700;900&display=swap" rel="stylesheet">
    <style>
        /* La firma del membrete se servía desde Google Fonts y, si no llegaba
           antes de imprimir, el nombre salía con la letra de reserva del
           sistema. Se sirve desde el propio servidor para que siempre sea la
           misma. Great Vibes — SIL Open Font License 1.1. */
        @font-face {
            font-family: 'Great Vibes';
            font-style: normal;
            font-weight: 400;
            font-display: block;   /* block, no swap: antes de imprimir se espera a la buena */
            src: url('<?php echo eco_url('assets/fonts/great-vibes-v21-latin.woff2'); ?>') format('woff2');
            unicode-range: U+0000-00FF, U+0131, U+0152-0153, U+02BB-02BC, U+02C6, U+02DA, U+02DC, U+2000-206F, U+2122;
        }
        @font-face {
            font-family: 'Great Vibes';
            font-style: normal;
            font-weight: 400;
            font-display: block;
            src: url('<?php echo eco_url('assets/fonts/great-vibes-v21-latin-ext.woff2'); ?>') format('woff2');
            unicode-range: U+0100-02BA, U+02BD-02C5, U+02C7-02CC, U+02CE-02D7, U+02DD-02FF, U+1E00-1E9F, U+1EF2-1EFF, U+2020, U+20A0-20AB, U+2C60-2C7F, U+A720-A7FF;
        }
        /* ============================================================
           VISTA EN PANTALLA  (web)  — diseño moderno
           ============================================================ */
        :root {
            --eco-azul: #02b1f4;
            --eco-azul-oscuro: #014a82;
            --eco-tinta: #1a2332;
            --eco-gris: #5a6878;
            --eco-borde: #e1e8f0;
        }
        * { box-sizing: border-box; }
        body {
            background: #eef2f7;
            font-family: "Poppins", sans-serif;
            margin: 0;
            padding: 30px;
            color: var(--eco-tinta);
        }
        .pagina {
            max-width: 920px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .barra-acciones {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 28px;
            background: #f8fbff;
            border-bottom: 1px solid var(--eco-borde);
        }
        .back-link { color: var(--eco-gris); text-decoration: none; font-weight: 500; font-size: 14px; }
        .back-link:hover { color: var(--eco-azul); }
        .acciones { display: flex; gap: 10px; }
        .btn {
            cursor: pointer; padding: 9px 18px; font-size: 13px; font-weight: 600;
            border-radius: 6px; background: transparent; border: 2px solid var(--eco-azul);
            color: var(--eco-azul); text-decoration: none; transition: .15s;
        }
        .btn:hover { background: var(--eco-azul); color: white; }
        .btn-secundario { border-color: #6c757d; color: #6c757d; }
        .btn-secundario:hover { background: #6c757d; color: white; }
        .btn-imprimir { border-color: #4f46e5; color: #4f46e5; }
        .btn-imprimir:hover { background: #4f46e5; color: white; }

        .documento { padding: 42px 52px; background: white; }

        /* Membrete moderno (solo pantalla) */
        .membrete {
            display: grid; grid-template-columns: auto 1fr auto;
            gap: 18px; align-items: center;
            padding-bottom: 14px;
            border-bottom: 2.5px solid var(--eco-azul);
            margin-bottom: 4px;
        }
        .membrete-logo {
            width: 64px; height: 64px; border-radius: 14px;
            background: linear-gradient(135deg, #02b1f4 0%, #014a82 100%);
            display: flex; align-items: center; justify-content: center;
            color: white; font-size: 30px;
            box-shadow: 0 4px 14px rgba(2, 177, 244, .35);
        }
        .membrete-texto h1 {
            font-family: "Playfair Display", serif; font-size: 26px;
            margin: 0; color: var(--eco-azul-oscuro); font-weight: 700; line-height: 1;
        }
        .membrete-texto .sub-clinica { font-size: 11.5px; color: var(--eco-gris); letter-spacing: 2.5px; text-transform: uppercase; font-weight: 500; margin-top: 3px; }
        .membrete-texto .dra { font-size: 13px; color: var(--eco-tinta); font-weight: 600; margin-top: 6px; }
        .membrete-meta { text-align: right; font-size: 11px; color: var(--eco-gris); line-height: 1.55; }
        .membrete-meta strong { display: block; color: var(--eco-azul-oscuro); font-size: 12.5px; font-weight: 700; }

        .titulo-estudio { text-align: center; margin: 18px 0 14px; }
        .titulo-estudio h2 {
            font-family: "Playfair Display", serif; font-size: 19px; font-weight: 700;
            color: var(--eco-tinta); margin: 0; text-transform: uppercase; letter-spacing: 3px;
        }
        .titulo-estudio .deco { color: var(--eco-azul); margin-top: 4px; }

        .ficha-paciente {
            display: grid; grid-template-columns: repeat(4, 1fr);
            gap: 6px 18px; padding: 11px 16px;
            background: #f7fbff; border: 1px solid #d6e6f5; border-radius: 6px;
            margin-bottom: 16px; font-size: 12px;
        }
        .ficha-paciente .item { display: flex; flex-direction: column; }
        .ficha-paciente .item .lbl { font-size: 9.5px; text-transform: uppercase; letter-spacing: .8px; color: var(--eco-gris); font-weight: 600; }
        .ficha-paciente .item .val { color: var(--eco-tinta); font-weight: 600; font-size: 12px; }
        .ficha-paciente .item.amplio { grid-column: span 2; }

        .cuerpo-informe { font-size: 12px; }
        .form-seccion { margin-bottom: 12px; break-inside: avoid; page-break-inside: avoid; }
        .form-seccion-header {
            display: flex; align-items: baseline; gap: 8px;
            padding: 4px 0 3px; border-bottom: 1.5px solid var(--eco-azul); margin-bottom: 8px;
        }
        .form-seccion-header h3 {
            margin: 0; font-size: 12.5px; font-weight: 700;
            color: var(--eco-azul-oscuro); text-transform: uppercase; letter-spacing: 1.2px;
        }
        .form-seccion-header h3 i { color: var(--eco-azul); margin-right: 5px; font-size: 11.5px; }
        .form-seccion-header p { font-style: italic; font-size: 10.5px; color: var(--eco-gris); margin: 0; flex: 1; }

        .form-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px 14px; }
        .form-group { margin-bottom: 2px; }
        .form-group.campo-completo { grid-column: span 6; }
        .form-group.campo-medio    { grid-column: span 3; }
        .form-group.campo-tercio   { grid-column: span 2; }
        .form-group.campo-sexto    { grid-column: span 1; }
        .form-group label { display: block; font-weight: 600; color: var(--eco-gris); font-size: 9.5px; margin-bottom: 1px; text-transform: uppercase; letter-spacing: .5px; }
        .campo-valor {
            margin: 0; padding: 3px 8px; background: #fafcff;
            border-left: 2.5px solid var(--eco-azul); border-radius: 0 4px 4px 0;
            font-size: 11.5px; color: var(--eco-tinta); min-height: 18px; line-height: 1.35;
        }
        .campo-valor em { color: #c1ccd9; font-style: normal; }

        .seccion-par-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .seccion-par-col { border: 1px solid #e0eaf5; border-radius: 6px; padding: 8px 10px; background: #fcfdff; }
        .seccion-par-col-titulo { font-weight: 700; font-size: 11px; color: var(--eco-azul-oscuro); text-transform: uppercase; letter-spacing: 1px; border-bottom: 1px solid #d6e6f5; padding-bottom: 3px; margin-bottom: 6px; text-align: center; }
        .form-grid-par { grid-template-columns: repeat(4, 1fr); }
        .seccion-par-col .form-group.campo-completo { grid-column: span 4; }
        .seccion-par-col .form-group.campo-medio    { grid-column: span 2; }
        .seccion-par-col .form-group.campo-tercio   { grid-column: span 2; }

        .pie-firma { margin-top: 26px; display: grid; grid-template-columns: 1fr 1fr; gap: 50px; align-items: end; }
        .firma-bloque { text-align: center; }
        .firma-linea { border-top: 1px solid #455364; margin: 50px 14% 6px; }
        .firma-bloque .nombre { font-weight: 700; font-size: 12px; color: var(--eco-tinta); }
        .firma-bloque .cargo { font-size: 10.5px; color: var(--eco-gris); font-style: italic; }
        .firma-bloque .registro { font-size: 10px; color: var(--eco-gris); margin-top: 2px; }
        .pie-documento { margin-top: 20px; padding-top: 10px; border-top: 1px solid var(--eco-borde); display: flex; justify-content: space-between; font-size: 9.5px; color: #94a3b8; font-style: italic; }
        .pie-documento .marca { color: var(--eco-azul-oscuro); font-weight: 600; font-style: normal; }

        /* Galería de imágenes del estudio (solo pantalla) */
        .estudio-imagenes { margin: 24px 0 4px; padding-top: 16px; border-top: 1px solid var(--eco-borde); }
        .ei-title { font-size: 14px; color: var(--eco-azul-oscuro); display: flex; align-items: center; gap: 8px; margin: 0 0 12px; font-weight: 700; }
        .ei-title i { color: var(--eco-azul); }
        .ei-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 10px; }
        .ei-item { display: block; border: 1px solid var(--eco-borde); border-radius: 8px; overflow: hidden; aspect-ratio: 1; cursor: zoom-in; }
        .ei-item img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .ei-adjuntos { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 10px; }
        .ei-adj { display: inline-flex; align-items: center; gap: 7px; padding: 8px 12px; border: 1px solid var(--eco-borde); border-radius: 8px; text-decoration: none; color: var(--eco-gris); font-size: 12.5px; }
        .ei-adj i { color: #dc2626; }
        .ei-adj:hover { border-color: var(--eco-azul); color: var(--eco-azul); }
        .ei-lightbox { position: fixed; inset: 0; background: rgba(10,18,30,.9); display: none; align-items: center; justify-content: center; z-index: 9999; padding: 24px; }
        .ei-lightbox.is-open { display: flex; }
        .ei-lightbox img { max-width: 95vw; max-height: 92vh; border-radius: 6px; box-shadow: 0 20px 60px rgba(0,0,0,.5); }
        .ei-close { position: absolute; top: 16px; right: 20px; width: 40px; height: 40px; border-radius: 50%; border: none; background: rgba(255,255,255,.15); color: #fff; font-size: 18px; cursor: pointer; }
        .ei-close:hover { background: rgba(255,255,255,.28); }

        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
        .badge-finalizado { background: #d4edda; color: #155724; }
        .badge-borrador   { background: #fff3cd; color: #856404; }
        .badge-firmado    { background: #cce5ff; color: #004085; }
        .badge-anulado    { background: #f8d7da; color: #721c24; }

        /* Bloques solo-impresión ocultos en pantalla */
        .print-only { display: none; }

        @media (max-width: 768px) {
            .documento { padding: 24px 18px; }
            .membrete { grid-template-columns: 1fr; text-align: center; gap: 10px; }
            .membrete-logo { margin: 0 auto; }
            .membrete-meta { text-align: center; }
            .ficha-paciente { grid-template-columns: 1fr 1fr; }
            .form-grid, .form-grid-par { grid-template-columns: 1fr 1fr; }
            .form-group.campo-completo, .form-group.campo-medio, .form-group.campo-tercio, .form-group.campo-sexto { grid-column: span 2; }
            .seccion-par-grid { grid-template-columns: 1fr; }
            .pie-firma { grid-template-columns: 1fr; gap: 20px; }
        }

        /* ============================================================
           IMPRESIÓN — RÉPLICA DEL DOCUMENTO FÍSICO DE LA DRA. TORO
           Una sola hoja Letter. Tipografía Times, blanco y negro,
           flujo inline tipo "label: valor".
           ============================================================ */
        /* Margen 0 en la hoja y el margen real como relleno del cuerpo: el
           navegador dibuja su encabezado (fecha y hora, título) DENTRO del
           margen de página, así que sin margen no lo imprime. La fecha ya
           está en el propio informe. */
        @page {
            size: Letter;
            margin: 0;
        }

        @media print {
            html, body {
                background: white !important;
                color: #000 !important;
                margin: 0 !important;
                padding: 0 !important;
                font-family: "Times New Roman", Times, serif !important;
                font-size: 8pt !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
            .pagina {
                box-shadow: none !important;
                border-radius: 0 !important;
                max-width: 100% !important;
            }
            .barra-acciones, .no-print { display: none !important; }
            /* El margen que antes ponía @page: la hoja sigue igual de holgada. */
            .documento { padding: 10mm 12mm 9mm 12mm !important; }

            /* Ocultar diseño moderno en impresión */
            .documento > .membrete,
            .documento > .titulo-estudio,
            .documento > .ficha-paciente,
            .documento > .pie-firma,
            .documento > .pie-documento { display: none !important; }

            /* Mostrar bloques clásicos solo-impresión */
            .print-only { display: block !important; }
            .print-only.print-ficha { display: grid !important; }

            /* ─── MEMBRETE CLÁSICO (compacto) ─── */
            .print-membrete {
                margin: 0 0 2px;
            }
            .print-membrete h1 {
                font-family: "Great Vibes", "Brush Script MT", cursive;
                font-size: 26pt;
                font-weight: 400;
                color: #000;
                margin: 0 0 -3px;
                line-height: 1;
                letter-spacing: .5pt;
            }
            .print-membrete .credenciales {
                border-top: 1pt solid #000;
                padding-top: 1px;
                font-weight: 700;
                font-style: italic;
                font-size: 8.5pt;
                color: #000;
                line-height: 1.15;
            }
            .print-membrete .mpps {
                font-size: 7.5pt;
                font-weight: 700;
                color: #000;
                margin-top: 0;
                line-height: 1.15;
            }

            /* ─── CAJA DATOS PACIENTE (compacta) ─── */
            .print-ficha {
                grid-template-columns: 3fr 1.3fr;
                border: 0.75pt solid #000;
                margin: 3px 0 3px;
                font-size: 8pt;
                color: #000;
            }
            .print-ficha .col-left,
            .print-ficha .col-right { padding: 2px 6px; }
            .print-ficha .col-right { border-left: 0.75pt solid #000; }
            .print-ficha .linea {
                display: flex;
                align-items: baseline;
                gap: 4px;
                padding: 1px 0;
                line-height: 1.2;
            }
            .print-ficha .linea strong { font-weight: 700; white-space: nowrap; font-size: 8pt; }
            .print-ficha .valor {
                flex: 1;
                border-bottom: 0.5pt solid #000;
                padding: 0 3px;
                font-weight: 500;
                min-height: 9pt;
                font-size: 8pt;
            }
            .print-ficha .col-right .valor-fecha {
                border-bottom: 0.5pt solid #000;
                padding: 1px 3px;
                margin-top: 2px;
                text-align: center;
                font-weight: 600;
                font-size: 8pt;
            }

            /* ─── TÍTULO CENTRADO ─── */
            .print-titulo {
                text-align: center;
                font-weight: 700;
                font-size: 10pt;
                margin: 15pt 0 15pt;
                letter-spacing: .6pt;
                color: #000;
                font-family: "Times New Roman", Times, serif;
            }

            /* ─── CUERPO  ·  2 COLUMNAS ESTRICTAMENTE BALANCEADAS ─── */
            .cuerpo-informe {
                font-family: "Times New Roman", Times, serif !important;
                font-size: 8.5pt !important;
                line-height: 1.3 !important;
                color: #000 !important;
                column-count: 2 !important;
                column-width: 50% !important;
                column-gap: 12pt !important;
                column-rule: 0.4pt solid #888 !important;
                column-fill: balance !important;
                -moz-column-fill: balance !important;
                widows: 2 !important;
                orphans: 2 !important;
                text-align: left;
            }

            /* Permitir que las secciones se dividan entre columnas para balance perfecto */
            .form-seccion {
                margin: 0 0 3px !important;
                break-inside: auto !important;
                page-break-inside: auto !important;
                -webkit-column-break-inside: auto !important;
            }
            .form-seccion-header {
                border: none !important;
                padding: 0 !important;
                margin: 2px 0 0 !important;
                display: block !important;
                break-after: avoid !important;
                page-break-after: avoid !important;
                -webkit-column-break-after: avoid !important;
            }
            .form-seccion-header h3 {
                color: #000 !important;
                font-size: 9pt !important;
                font-weight: 700 !important;
                letter-spacing: 0 !important;
                text-transform: none !important;
                font-family: "Times New Roman", Times, serif !important;
                margin: 0 !important;
                line-height: 1.2 !important;
            }
            .form-seccion-header h3 i { display: none !important; }
            .form-seccion-header p {
                font-size: 7pt !important;
                margin: 0 !important;
                color: #000 !important;
                line-height: 1.15 !important;
            }
            .form-seccion-body { padding: 0 !important; }

            /* Flujo inline tipo documento físico */
            .form-grid {
                display: block !important;
                gap: 0 !important;
            }
            .form-group {
                display: inline !important;
                margin: 0 4pt 0 0 !important;
                padding: 0 !important;
                page-break-inside: avoid;
            }
            .form-group.campo-completo {
                display: block !important;
                margin: 0 0 1px !important;
            }

            .form-group label {
                display: inline !important;
                font-weight: 400 !important;
                color: #000 !important;
                font-size: 8.5pt !important;
                text-transform: none !important;
                letter-spacing: 0 !important;
                margin: 0 !important;
                font-family: "Times New Roman", Times, serif !important;
            }
            .form-group label::after { content: ": "; }
            .campo-req { display: none !important; }

            .campo-valor {
                display: inline !important;
                background: transparent !important;
                border: none !important;
                border-bottom: 0.4pt solid #000 !important;
                border-radius: 0 !important;
                padding: 0 2pt !important;
                margin: 0 !important;
                font-size: 8.5pt !important;
                color: #000 !important;
                min-height: 0 !important;
                line-height: 1.25 !important;
                font-family: "Times New Roman", Times, serif !important;
            }
            .form-group.campo-completo > .campo-valor {
                display: block !important;
                border: none !important;
                margin: 0 0 1px !important;
                padding: 0 !important;
                text-align: justify;
                font-size: 8.5pt !important;
            }
            .campo-valor em {
                color: #000 !important;
                font-style: normal !important;
                font-size: 0 !important;
            }
            .campo-valor em::before {
                content: '______';
                font-size: 8.5pt;
                letter-spacing: -0.5pt;
            }

            /* Secciones par (Riñón Der/Izq) en flujo vertical */
            .seccion-par-grid {
                display: block !important;
                gap: 0 !important;
            }
            .seccion-par-col {
                border: none !important;
                padding: 0 !important;
                background: transparent !important;
                margin-bottom: 1px;
                break-inside: avoid !important;
                -webkit-column-break-inside: avoid !important;
            }
            .seccion-par-col-titulo {
                text-align: left !important;
                border: none !important;
                font-size: 8pt !important;
                font-weight: 700 !important;
                color: #000 !important;
                margin: 1px 0 0 !important;
                padding: 0 !important;
                text-transform: none !important;
                letter-spacing: 0 !important;
                break-after: avoid !important;
                page-break-after: avoid !important;
                -webkit-column-break-after: avoid !important;
            }
            .form-grid-par {
                display: block !important;
                grid-template-columns: none !important;
            }

            /* Sub-encabezados decorativos (tipo:info) */
            .form-group [style*="linear-gradient"] {
                background: transparent !important;
                border: none !important;
                padding: 0 !important;
                font-weight: 700 !important;
                font-style: italic !important;
                color: #000 !important;
                font-size: 8.5pt !important;
                font-family: "Times New Roman", Times, serif !important;
                display: block !important;
                margin: 1px 0 0 !important;
                line-height: 1.2 !important;
            }
            .form-group [style*="linear-gradient"] i { display: none !important; }

            /* Pie compacto */
            .print-footer {
                position: fixed;
                bottom: 3mm;
                left: 0;
                right: 0;
                text-align: center;
                font-size: 6.5pt;
                color: #555;
                font-style: italic;
                font-family: "Times New Roman", Times, serif;
            }

            /* Anti-saltos */
            h2, h3 { page-break-after: avoid; break-after: avoid; }
        }
    </style>
</head>
<body>
<div class="pagina">

    <div class="barra-acciones no-print">
        <a href="<?= eco_url('informes-estudio') ?>?paciente_id=<?php echo (int)$informe['paciente_id']; ?>" class="back-link">
            <i class="fa-solid fa-arrow-left"></i> Volver al historial de estudios
        </a>
        <div class="acciones">
            <a href="<?= eco_url('nuevo-informe') ?>?paciente_id=<?php echo (int)$informe['paciente_id']; ?>" class="btn btn-secundario">
                <i class="fa-solid fa-plus"></i> Nuevo Estudio
            </a>
            <a href="#" onclick="window.print(); return false;" class="btn btn-imprimir">
                <i class="fa-solid fa-print"></i> Imprimir / PDF
            </a>
        </div>
    </div>

    <div class="documento">

        <!-- ════════════════════════════════════════════════════════
             BLOQUES SOLO-IMPRESIÓN (réplica del documento físico)
             ════════════════════════════════════════════════════════ -->

        <header class="print-only print-membrete">
            <h1>Dra. Madelleine Toro</h1>
            <div class="credenciales">Ecografista Integral / Médico Epidemiólogo / Medicina general</div>
            <div class="mpps">M.P.P.S 84.399&nbsp;&nbsp;/CI 12065538&nbsp;&nbsp;/C.M.Y 3084&nbsp;&nbsp;Tlf:0412-8517770</div>
        </header>

        <div class="print-only print-ficha">
            <div class="col-left">
                <div class="linea">
                    <strong>Nombres y Apellidos:</strong>
                    <span class="valor"><?php echo htmlspecialchars($informe['paciente_nombre']); ?></span>
                </div>
                <div class="linea">
                    <strong>Edad:</strong>
                    <span class="valor" style="max-width:70px;flex:0 0 70px;">
                        <?php echo !empty($informe['paciente_edad']) ? (int)$informe['paciente_edad'] . ' años' : ''; ?>
                    </span>
                    <strong style="margin-left:10px;">CI:</strong>
                    <span class="valor">
                        <?php echo !empty($informe['paciente_cedula']) ? htmlspecialchars($informe['paciente_cedula']) : ''; ?>
                    </span>
                </div>
                <div class="linea">
                    <strong>Motivo de consulta:</strong>
                    <span class="valor">
                        <?php echo $motivo_consulta !== '' ? htmlspecialchars($motivo_consulta) : ''; ?>
                    </span>
                </div>
            </div>
            <div class="col-right">
                <div class="linea"><strong>FECHA:</strong></div>
                <div class="valor-fecha"><?php echo htmlspecialchars($fecha_estudio_fmt); ?></div>
                <div class="linea" style="margin-top:6px;font-size:8.5pt;color:#444;">
                    <strong style="font-weight:600;">N&deg; Informe:</strong>
                    <span style="margin-left:4px;"><?php echo htmlspecialchars($informe['numero_informe']); ?></span>
                </div>
            </div>
        </div>

        <div class="print-only print-titulo"><?php echo htmlspecialchars($titulo_print); ?></div>

        <!-- ════════════════════════════════════════════════════════
             BLOQUES SOLO-PANTALLA (vista web moderna)
             ════════════════════════════════════════════════════════ -->

        <header class="membrete">
            <div class="membrete-logo"><i class="fa-solid fa-wave-square"></i></div>
            <div class="membrete-texto">
                <h1>EcoMadelleine</h1>
                <div class="sub-clinica">Centro de Diagnóstico Ecográfico</div>
                <div class="dra">Dra. Madelleine Toro <small style="color:#5a6878;font-weight:400;font-size:11px;">&nbsp;·&nbsp; Médico Ecografista</small></div>
            </div>
            <div class="membrete-meta">
                <strong>Informe Ecográfico</strong>
                Fecha del estudio: <?php echo htmlspecialchars($fecha_estudio_fmt); ?><br>
                Emitido: <?php echo htmlspecialchars($fecha_creado); ?><br>
                <span style="display:inline-block;margin-top:4px;padding:2px 10px;border:1px solid var(--eco-azul);border-radius:12px;color:var(--eco-azul-oscuro);font-weight:600;font-size:11px;background:#f0f9ff;">N&deg; <?php echo htmlspecialchars($informe['numero_informe']); ?></span>
            </div>
        </header>

        <div class="titulo-estudio">
            <h2><?php echo htmlspecialchars($informe['tipo_nombre']); ?></h2>
            <div class="deco"><i class="<?php echo htmlspecialchars($informe['tipo_icono'] ?: 'fa-solid fa-wave-square'); ?>"></i></div>
        </div>

        <section class="ficha-paciente">
            <div class="item amplio">
                <span class="lbl">Paciente</span>
                <span class="val"><?php echo htmlspecialchars($informe['paciente_nombre']); ?></span>
            </div>
            <div class="item">
                <span class="lbl">Cédula</span>
                <span class="val"><?php echo !empty($informe['paciente_cedula']) ? htmlspecialchars($informe['paciente_cedula']) : '—'; ?></span>
            </div>
            <div class="item">
                <span class="lbl">Edad</span>
                <span class="val"><?php echo !empty($informe['paciente_edad']) ? (int)$informe['paciente_edad'] . ' años' : '—'; ?></span>
            </div>
            <?php if (!empty($informe['medico_solicitante'])): ?>
            <div class="item amplio">
                <span class="lbl">Médico solicitante</span>
                <span class="val"><?php echo htmlspecialchars($informe['medico_solicitante']); ?></span>
            </div>
            <?php endif; ?>
            <div class="item">
                <span class="lbl">Ecografista</span>
                <span class="val"><?php echo htmlspecialchars($informe['ecografista_nombre']); ?></span>
            </div>
            <div class="item">
                <span class="lbl">Estado</span>
                <span class="val">
                    <span class="badge badge-<?php echo htmlspecialchars($informe['estado']); ?>"><?php echo htmlspecialchars($informe['estado']); ?></span>
                </span>
            </div>
        </section>

        <!-- ════════════════════════════════════════════════════════
             CUERPO (compartido pantalla + impresión)
             ════════════════════════════════════════════════════════ -->
        <section class="cuerpo-informe">
            <?php echo eco_render_formulario($esquema, $datos, true); ?>
        </section>

        <!-- Pie solo-pantalla -->
        <footer class="pie-firma">
            <div class="firma-bloque">
                <div class="firma-linea"></div>
                <div class="nombre">Dra. Madelleine Toro</div>
                <div class="cargo">Médico Ecografista</div>
                <div class="registro">EcoMadelleine · Diagnóstico por Imagen</div>
            </div>
            <div class="firma-bloque">
                <div class="firma-linea"></div>
                <div class="nombre"><?php echo htmlspecialchars($informe['ecografista_nombre']); ?></div>
                <div class="cargo">Ecografista responsable del estudio</div>
                <?php if (!empty($informe['ecografista_cedula'])): ?>
                <div class="registro">C.I. <?php echo htmlspecialchars($informe['ecografista_cedula']); ?></div>
                <?php endif; ?>
            </div>
        </footer>

        <div class="pie-documento">
            <span><i class="fa-solid fa-shield-halved"></i> Documento clínico confidencial</span>
            <span class="marca">EcoMadelleine · Informe N&deg; <?php echo htmlspecialchars($informe['numero_informe']); ?></span>
        </div>

        <?php if (!empty($archivos)): ?>
        <section class="estudio-imagenes no-print">
            <h3 class="ei-title"><i class="fa-solid fa-images"></i> Imágenes del estudio</h3>
            <?php if (!empty($archivos_img)): ?>
            <div class="ei-grid">
                <?php foreach ($archivos_img as $a): $u = eco_url('api/descargar_archivo_informe.php') . '?id=' . (int)$a['id']; ?>
                    <a class="ei-item" href="<?php echo $u; ?>" data-img="<?php echo $u; ?>" target="_blank" rel="noopener">
                        <img src="<?php echo $u; ?>" alt="<?php echo htmlspecialchars($a['nombre_original'], ENT_QUOTES); ?>" loading="lazy">
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($archivos_adj)): ?>
            <div class="ei-adjuntos">
                <?php foreach ($archivos_adj as $a): ?>
                    <a class="ei-adj" href="<?= eco_url('api/descargar_archivo_informe.php') ?>?id=<?php echo (int)$a['id']; ?>" target="_blank" rel="noopener">
                        <i class="fa-solid fa-file-pdf"></i> <?php echo htmlspecialchars($a['nombre_original']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if ($esta_firmado): ?>
        <style>
            .firma-sello { margin:22px 0 6px; border:1px solid #000; border-radius:6px; padding:14px 16px; background:#fff;
                color:#000; break-inside:avoid; page-break-inside:avoid; }
            .firma-sello__titulo { font-weight:700; color:#000; font-size:12.5px; letter-spacing:.03em; margin-bottom:10px;
                display:flex; align-items:center; gap:8px; }
            .firma-sello__grid { display:grid; grid-template-columns:1fr 1fr; gap:7px 24px; margin:0; }
            .firma-sello__grid > div { display:flex; flex-direction:column; }
            .firma-sello__grid .firma-sello__full { grid-column:1 / -1; }
            .firma-sello__grid dt { font-size:10px; text-transform:uppercase; letter-spacing:.04em; color:#000; font-weight:600; }
            .firma-sello__grid dd { margin:0; font-size:12px; color:#000; }
            .firma-sello__grid dd.mono { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:11px;
                word-break:break-all; color:#000; }
            .firma-sello__nota { margin:10px 0 0; font-size:10px; color:#000; line-height:1.4; }
        </style>
        <div class="firma-sello">
            <div class="firma-sello__titulo"><i class="fa-solid fa-shield-halved"></i> DOCUMENTO FIRMADO ELECTRÓNICAMENTE</div>
            <dl class="firma-sello__grid">
                <div><dt>Firmado por</dt><dd><?php echo htmlspecialchars($firmante_nombre); ?></dd></div>
                <div><dt>Fecha y hora de firma</dt><dd><?php echo htmlspecialchars($fecha_firma_fmt); ?></dd></div>
                <?php if ($tiene_sello): ?>
                <div class="firma-sello__full"><dt>Algoritmo</dt><dd>SHA-256 + HMAC (sello del servidor)</dd></div>
                <div class="firma-sello__full"><dt>Huella SHA-256</dt><dd class="mono"><?php echo htmlspecialchars($informe['documento_sha256']); ?></dd></div>
                <div class="firma-sello__full"><dt>Sello</dt><dd class="mono"><?php echo htmlspecialchars(substr((string)$informe['sello_firma'], 0, 48)); ?>…</dd></div>
                <?php endif; ?>
            </dl>
            <?php if ($tiene_sello): ?>
            <p class="firma-sello__nota">La huella SHA-256 identifica de forma única el contenido del informe. Cualquier alteración posterior modifica la huella y delata el cambio.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Pie solo-impresión -->
        <div class="print-only print-footer">
            Dra. Madelleine Toro &middot; M.P.P.S 84.399 &middot; Informe N&deg; <?php echo htmlspecialchars($informe['numero_informe']); ?>
        </div>

    </div>
</div>

<?php if (isset($_GET['print']) && $_GET['print'] === '1'): ?>
<script>
    /* El evento load no espera a los ficheros de fuente: se descargan cuando
       hacen falta. Imprimir sin esperar sacaba el membrete con la letra de
       reserva del sistema. Se espera a la firma, con tope de 4 s para no
       dejar la impresión colgada si algo falla. */
    window.addEventListener('load', function () {
        function imprimir() { window.print(); }

        if (!document.fonts || !document.fonts.load) {
            setTimeout(imprimir, 500);
            return;
        }

        var firma = document.fonts.load('26pt "Great Vibes"', 'Dra. Madelleine Toro');
        var tope  = new Promise(function (r) { setTimeout(r, 4000); });

        Promise.race([firma.catch(function () {}), tope])
            .then(function () { setTimeout(imprimir, 120); });
    });
</script>
<?php endif; ?>

<?php if (!empty($archivos_img)): ?>
<script>
(function () {
    var grid = document.querySelector('.estudio-imagenes .ei-grid');
    if (!grid) return;
    var ov = null;
    function lightbox(url) {
        if (!ov) {
            ov = document.createElement('div');
            ov.className = 'ei-lightbox';
            ov.innerHTML = '<button type="button" class="ei-close" aria-label="Cerrar"><i class="fa-solid fa-xmark"></i></button><img alt="">';
            document.body.appendChild(ov);
            ov.addEventListener('click', function (e) {
                if (e.target === ov || e.target.closest('.ei-close')) ov.classList.remove('is-open');
            });
            document.addEventListener('keydown', function (e) { if (e.key === 'Escape') ov.classList.remove('is-open'); });
        }
        ov.querySelector('img').src = url;
        ov.classList.add('is-open');
    }
    grid.querySelectorAll('.ei-item').forEach(function (a) {
        a.addEventListener('click', function (e) {
            e.preventDefault();
            lightbox(a.getAttribute('data-img'));
        });
    });
})();
</script>
<?php endif; ?>

</body>
</html>
