<?php
    include 'core/conexion.php';
    $contenido_web = [];
    $resultado = $conex->query("SELECT clave, valor FROM contenido_web");
    while ($fila = $resultado->fetch_assoc()) {
        $contenido_web[$fila['clave']] = $fila['valor'];
    }
    include __DIR__ . '/publico/send.php';

    /* ───────────────────────────────────────────────────────────────
       MÉTRICAS REALES desde la base de datos
       ─────────────────────────────────────────────────────────────── */

    // 1. Total de pacientes aprobados
    $r = $conex->query("SELECT COUNT(*) c FROM usuarios WHERE rol='paciente' AND estado='aprobado'");
    $total_pacientes = (int)($r->fetch_assoc()['c'] ?? 0);

    // 2. Tipos de estudio activos (excluyendo sub-categorías técnicas)
    $r = $conex->query("SELECT COUNT(*) c FROM tipos_ecografias
                        WHERE activo=1 AND (categoria IS NULL
                        OR categoria NOT IN ('Musculoesqueletica_Sub','Obstetrica_Sub','Partes_Blandas_Sub'))");
    $total_tipos = (int)($r->fetch_assoc()['c'] ?? 0);

    // 3. Promedio real de horas entre creación y firma del informe
    $r = $conex->query("SELECT ROUND(AVG(TIMESTAMPDIFF(HOUR, creado_en, actualizado_en))) h
                        FROM informes_estudios
                        WHERE estado IN ('finalizado','firmado')
                          AND TIMESTAMPDIFF(HOUR, creado_en, actualizado_en) BETWEEN 0 AND 720");
    $avg_horas = (int)($r->fetch_assoc()['h'] ?? 0);

    // 4. Tasa de conclusión real: % de citas completadas vs gestionadas
    $r = $conex->query("SELECT
            SUM(CASE WHEN estado='completada' THEN 1 ELSE 0 END) completadas,
            SUM(CASE WHEN estado IN ('completada','cancelada','reprogramada') THEN 1 ELSE 0 END) gestionadas
        FROM citas");
    $row = $r->fetch_assoc();
    $tasa_conclusion = ($row && (int)$row['gestionadas'] > 0)
        ? (int)round(((int)$row['completadas'] / (int)$row['gestionadas']) * 100)
        : 0;

    // 5. Total de informes firmados/finalizados (métrica adicional para el hero)
    $r = $conex->query("SELECT COUNT(*) c FROM informes_estudios WHERE estado IN ('finalizado','firmado')");
    $total_informes = (int)($r->fetch_assoc()['c'] ?? 0);

    /* Helpers de visualización honesta — si el dato real es 0, se muestra etiqueta de compromiso */
    $f_pac = [
        'value' => $total_pacientes > 0 ? number_format($total_pacientes, 0, ',', '.') . '+' : '-',
        'label' => $total_pacientes > 0 ? 'Pacientes registrados' : 'Próximos pacientes',
    ];
    $f_tip = [
        'value' => $total_tipos > 0 ? $total_tipos . '+' : '-',
        'label' => 'Tipos de estudio',
    ];
    $f_hrs = [
        'value' => $avg_horas > 0 ? $avg_horas . 'h' : '24h',
        'label' => $avg_horas > 0 ? 'Promedio de informe' : 'Compromiso de entrega',
    ];
    $f_tasa = [
        'value' => $tasa_conclusion > 0 ? $tasa_conclusion . '%' : '100%',
        'label' => $tasa_conclusion > 0 ? 'Tasa de conclusión' : 'Compromiso clínico',
    ];

    /* Paleta por categoría — coherente con eco_colores_shell (Renal / Abdominal / Pélvica / etc.) */
    $eco_palette = [
        'Abdominal'          => ['c1' => '#02b1f4', 'soft' => '#e0f5fe', 'text' => '#0284c7'],
        'Renal'              => ['c1' => '#0ea5e9', 'soft' => '#e0f2fe', 'text' => '#0369a1'],
        'Obstetrica'         => ['c1' => '#ec4899', 'soft' => '#fce7f3', 'text' => '#be185d'],
        'Cervical'           => ['c1' => '#14b8a6', 'soft' => '#ccfbf1', 'text' => '#0f766e'],
        'Pelvica'            => ['c1' => '#8b5cf6', 'soft' => '#ede9fe', 'text' => '#6d28d9'],
        'Musculoesqueletica' => ['c1' => '#22c55e', 'soft' => '#dcfce7', 'text' => '#15803d'],
        'Prostatica'         => ['c1' => '#3b82f6', 'soft' => '#dbeafe', 'text' => '#1d4ed8'],
        'Mamaria'            => ['c1' => '#f43f5e', 'soft' => '#ffe4e6', 'text' => '#be123c'],
        'Partes Blandas'     => ['c1' => '#f59e0b', 'soft' => '#fef3c7', 'text' => '#b45309'],
        'Testicular'         => ['c1' => '#6366f1', 'soft' => '#e0e7ff', 'text' => '#4338ca'],
        'Pulmonar'           => ['c1' => '#0891b2', 'soft' => '#cffafe', 'text' => '#0e7490'],
    ];
    $eco_palette_default = ['c1' => '#64748b', 'soft' => '#f1f5f9', 'text' => '#475569'];

    /* ── Datos para el panel de analíticas (gráficos reales) ─────────── */
    // Estudios (informes) por mes — últimos 6 meses con relleno de ceros
    $an_mp = [];
    $rq = $conex->query("SELECT DATE_FORMAT(creado_en,'%Y-%m') m, COUNT(*) c FROM informes_estudios GROUP BY m");
    while ($rq && $f = $rq->fetch_assoc()) { $an_mp[$f['m']] = (int)$f['c']; }
    $an_nom = [1=>'Ene',2=>'Feb',3=>'Mar',4=>'Abr',5=>'May',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Sep',10=>'Oct',11=>'Nov',12=>'Dic'];
    $an_meses_lbl = []; $an_meses_val = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("first day of -$i month");
        $an_meses_lbl[] = $an_nom[(int)date('n', $ts)];
        $an_meses_val[] = $an_mp[date('Y-m', $ts)] ?? 0;
    }
    $an_total_estudios = array_sum($an_meses_val);

    // Citas por estado — agrupadas en buckets legibles
    $an_estados = [];
    $rq = $conex->query("SELECT estado, COUNT(*) c FROM citas GROUP BY estado");
    while ($rq && $f = $rq->fetch_assoc()) { $an_estados[$f['estado']] = (int)$f['c']; }
    $an_bmap = [
        'confirmada'         => ['Confirmadas',   '#02b1f4'],
        'completada'         => ['Completadas',   '#22c55e'],
        'reprogramada'       => ['Reprogramadas', '#8b5cf6'],
        'pendiente'          => ['Pendientes',    '#f59e0b'],
        'pendiente_paciente' => ['Pendientes',    '#f59e0b'],
        'cancelada'          => ['Canceladas',    '#f43f5e'],
    ];
    $an_tmp = [];
    foreach ($an_estados as $e => $n) {
        $info = $an_bmap[$e] ?? [ucfirst(str_replace('_', ' ', $e)), '#94a3b8'];
        if (!isset($an_tmp[$info[0]])) $an_tmp[$info[0]] = ['v' => 0, 'c' => $info[1]];
        $an_tmp[$info[0]]['v'] += $n;
    }
    $an_citas_lbl = []; $an_citas_val = []; $an_citas_col = [];
    foreach ($an_tmp as $lab => $d) { $an_citas_lbl[] = $lab; $an_citas_val[] = $d['v']; $an_citas_col[] = $d['c']; }
    $an_total_citas = array_sum($an_citas_val);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="EcoMadelleine · Centro de diagnóstico ecográfico premium. Dra. Madelleine Toro. Informes digitales en 24 horas.">
    <meta name="theme-color" content="#eaf3ff">
    <title>EcoMadelleine · Diagnóstico Ecográfico Premium</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <style>
    /* ════════════════════════════════════════════════════════════════
       DESIGN TOKENS — Glass Clínico (Apple-style)
       ════════════════════════════════════════════════════════════════ */
    :root {
        /* Fondo cielo-clínico */
        --sky-1:        #eaf3ff;
        --sky-2:        #f5f9ff;
        --sky-3:        #dbeafe;
        --white:        #ffffff;

        /* Texto */
        --ink:          #0c1a2e;
        --ink-2:        #1e2a44;
        --gris:         #4a5870;
        --gris-soft:    #6b7689;
        --gris-mute:    #94a3b8;

        /* Brand */
        --azul:         #02b1f4;
        --azul-dark:    #014a82;
        --azul-deep:    #003a66;
        --azul-soft:    #e0f5fe;

        /* Bordes plata (truco glass) */
        --silver-top:   rgba(255, 255, 255, .85);
        --silver-bot:   rgba(12, 26, 46, .06);
        --silver-edge:  rgba(255, 255, 255, .55);

        /* Glass surfaces */
        --glass:        rgba(255, 255, 255, .55);
        --glass-2:      rgba(255, 255, 255, .42);
        --glass-strong: rgba(255, 255, 255, .72);

        /* Sombras (luz + brand glow) */
        --sh-soft:      0 1px 2px rgba(12, 26, 46, .04), 0 8px 24px rgba(12, 26, 46, .06);
        --sh-glow:      0 24px 60px rgba(2, 177, 244, .18);
        --sh-deep:      0 30px 80px rgba(12, 26, 46, .15);

        --r-sm:         12px;
        --r:            18px;
        --r-lg:         24px;
        --r-xl:         32px;
        --r-2xl:        40px;

        --ease:         cubic-bezier(.22, 1, .36, 1);
        --ease-spring:  cubic-bezier(.34, 1.56, .64, 1);

        --gutter:       28px;
        --max:          1280px;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { scroll-behavior: smooth; }
    html, body { overflow-x: hidden; }
    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: var(--sky-2);
        color: var(--ink);
        line-height: 1.55;
        font-size: 15.5px;
        font-weight: 400;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        position: relative;
        min-height: 100vh;
    }

    /* Fondo ambiental — gradient mesh estático + orbes muy sutiles */
    body::before {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -2;
        background:
            radial-gradient(ellipse 80% 60% at 85% 0%,  rgba(2, 177, 244, .18) 0%, transparent 55%),
            radial-gradient(ellipse 60% 50% at 0% 30%,  rgba(99, 179, 237, .14) 0%, transparent 55%),
            radial-gradient(ellipse 70% 50% at 50% 100%, rgba(167, 139, 250, .10) 0%, transparent 55%),
            linear-gradient(180deg, var(--sky-1) 0%, var(--sky-2) 100%);
    }
    body::after {
        content: '';
        position: fixed;
        inset: 0;
        z-index: -1;
        pointer-events: none;
        background-image:
            radial-gradient(circle at 1px 1px, rgba(12, 26, 46, .035) 1px, transparent 0);
        background-size: 28px 28px;
    }

    img, svg { display: block; max-width: 100%; }
    a { color: inherit; text-decoration: none; }
    button { font-family: inherit; }
    ::selection { background: var(--azul); color: #fff; }

    .container { max-width: var(--max); margin: 0 auto; padding: 0 var(--gutter); }

    /* Tipografía */
    h1, h2, h3, h4 {
        font-family: 'Inter', sans-serif;
        font-weight: 700;
        line-height: 1.08;
        letter-spacing: -0.025em;
        color: var(--ink);
    }
    .eyebrow {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1.6px;
        color: var(--azul-dark);
        background: var(--glass);
        backdrop-filter: blur(14px) saturate(1.6);
        -webkit-backdrop-filter: blur(14px) saturate(1.6);
        padding: 7px 14px;
        border-radius: 999px;
        border: 1px solid var(--silver-edge);
        box-shadow: inset 0 1px 0 var(--silver-top), 0 1px 2px var(--silver-bot);
    }
    .eyebrow i { font-size: 9px; color: var(--azul); }

    .section-title {
        font-size: clamp(32px, 4.6vw, 56px);
        font-weight: 700;
        margin-top: 18px;
        margin-bottom: 16px;
        max-width: 760px;
    }
    .section-title .grad {
        background: linear-gradient(120deg, var(--azul) 0%, var(--azul-deep) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .section-sub {
        font-size: 16.5px;
        line-height: 1.65;
        color: var(--gris);
        max-width: 600px;
        font-weight: 400;
    }
    .section-head { margin-bottom: 72px; max-width: 760px; }
    .section-head--center { text-align: center; margin-left: auto; margin-right: auto; }
    .section-head--center .section-title,
    .section-head--center .section-sub { margin-left: auto; margin-right: auto; }

    section { padding: 130px 0; position: relative; }

    /* ────────────────────────────────────────────────
       GLASS PRIMITIVE (reutilizable)
       ──────────────────────────────────────────────── */
    .glass {
        background: var(--glass);
        backdrop-filter: blur(24px) saturate(1.8);
        -webkit-backdrop-filter: blur(24px) saturate(1.8);
        border: 1px solid var(--silver-edge);
        border-radius: var(--r-lg);
        box-shadow:
            inset 0 1px 0 var(--silver-top),
            inset 0 -1px 0 var(--silver-bot),
            var(--sh-soft);
    }

    /* ────────────────────────────────────────────────
       SCROLL PROGRESS
       ──────────────────────────────────────────────── */
    #scroll-progress {
        position: fixed;
        top: 0; left: 0;
        height: 2px;
        width: 0;
        background: linear-gradient(90deg, var(--azul), var(--azul-deep));
        z-index: 2000;
        transition: width .12s linear;
    }

    /* ────────────────────────────────────────────────
       MENSAJES DE ESTADO
       ──────────────────────────────────────────────── */
    .mensaje-estado {
        position: fixed;
        top: 100px; left: 50%;
        transform: translateX(-50%);
        z-index: 1500;
        padding: 14px 22px;
        border-radius: var(--r);
        color: #fff;
        font-size: 14px;
        font-weight: 500;
        box-shadow: var(--sh-deep);
        animation: msgIn .5s var(--ease);
        backdrop-filter: blur(14px);
        -webkit-backdrop-filter: blur(14px);
    }
    .mensaje-exito { background: linear-gradient(135deg, #16a34a, #15803d); }
    .mensaje-error { background: linear-gradient(135deg, #ef4444, #b91c1c); }
    @keyframes msgIn {
        from { opacity: 0; transform: translate(-50%, -16px); }
        to   { opacity: 1; transform: translate(-50%, 0); }
    }

    /* ════════════════════════════════════════════════════════════════
       HEADER GLASS
       ════════════════════════════════════════════════════════════════ */
    .header {
        position: fixed;
        top: 16px; left: 50%;
        transform: translateX(-50%);
        z-index: 1000;
        width: calc(100% - 32px);
        max-width: 1250px;
        padding: 12px 16px 12px 20px;
        background: var(--glass-strong);
        backdrop-filter: blur(24px) saturate(1.8);
        -webkit-backdrop-filter: blur(24px) saturate(1.8);
        border: 1px solid var(--silver-edge);
        border-radius: 999px;
        box-shadow:
            inset 0 1px 0 var(--silver-top),
            inset 0 -1px 0 var(--silver-bot),
            0 10px 40px rgba(12, 26, 46, .08);
        transition: padding .3s var(--ease), transform .4s var(--ease);
    }
    .header.scrolled { padding: 9px 14px 9px 18px; }
    .header.hidden { transform: translateX(-50%) translateY(-130%); }

    .menu { display: flex; justify-content: space-between; align-items: center; gap: 24px; }

    .logo {
        display: inline-flex;
        align-items: center;
        gap: 11px;
        font-size: 17px;
        font-weight: 700;
        color: var(--ink);
        letter-spacing: -0.015em;
    }
    .logo-icon {
        width: 36px; height: 36px;
        border-radius: 12px;
        background: linear-gradient(135deg, var(--azul) 0%, var(--azul-dark) 100%);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 14px;
        box-shadow:
            inset 0 1px 0 rgba(255,255,255,.4),
            0 6px 16px rgba(2, 177, 244, .35);
        position: relative;
    }
    .logo-text { display: flex; flex-direction: column; line-height: 1; }
    .logo-text small {
        font-size: 8.5px;
        font-weight: 500;
        color: var(--gris-mute);
        text-transform: uppercase;
        letter-spacing: 1.8px;
        margin-top: 3px;
    }

    .navbar ul { list-style: none; display: flex; gap: 2px; align-items: center; }
    .navbar a {
        font-size: 13.5px;
        font-weight: 500;
        color: var(--gris);
        padding: 9px 14px;
        border-radius: 999px;
        transition: color .2s, background .25s var(--ease);
    }
    .navbar a:not(.nav-cta):hover { color: var(--ink); background: rgba(2, 177, 244, .08); }
    .navbar .nav-cta {
        background: var(--ink);
        color: #fff;
        padding: 10px 18px;
        margin-left: 8px;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 7px;
        box-shadow: 0 8px 20px rgba(12, 26, 46, .25);
        transition: transform .25s var(--ease), box-shadow .25s;
    }
    .navbar .nav-cta:hover { background: var(--azul-dark); transform: translateY(-1px); box-shadow: 0 12px 28px rgba(1, 74, 130, .35); }
    .navbar .nav-cta i { font-size: 10px; }

    .hamburger {
        display: none;
        background: transparent;
        border: 1px solid var(--silver-edge);
        border-radius: 999px;
        width: 38px; height: 38px;
        cursor: pointer;
        color: var(--ink);
        font-size: 14px;
        align-items: center;
        justify-content: center;
    }

    /* ════════════════════════════════════════════════════════════════
       HERO — Split 60/40 con visual abstracto + métricas glass
       ════════════════════════════════════════════════════════════════ */
    .hero {
        padding-top: 110px;
        padding-bottom: 100px;
        position: relative;
    }
    .hero-grid {
        display: grid;
        grid-template-columns: 0.9fr 1.1fr;
        gap: 48px;
        align-items: center;
    }

    .hero-copy { position: relative; }
    .hero-tag {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 8px 16px 8px 8px;
        background: var(--glass);
        backdrop-filter: blur(14px) saturate(1.6);
        -webkit-backdrop-filter: blur(14px) saturate(1.6);
        border: 1px solid var(--silver-edge);
        border-radius: 999px;
        font-size: 12.5px;
        font-weight: 500;
        color: var(--ink-2);
        margin-bottom: 10px;
        margin-top: -128px;
        box-shadow: inset 0 1px 0 var(--silver-top), 0 4px 12px rgba(12, 26, 46, .04);
    }
    .hero-tag .pill {
        background: linear-gradient(135deg, var(--azul), var(--azul-dark));
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        padding: 4px 10px;
        border-radius: 999px;
        box-shadow: 0 4px 10px rgba(2,177,244,.4);
    }

    .hero-copy h1 {
        font-size: clamp(42px, 5.6vw, 72px);
        font-weight: 800;
        line-height: 1.02;
        letter-spacing: -0.035em;
        margin-bottom: 24px;
    }
    .hero-copy h1 .grad {
        background: linear-gradient(120deg, var(--azul) 0%, var(--azul-deep) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
        display: inline-block;
    }
    .hero-copy .lead {
        font-size: 17.5px;
        line-height: 1.6;
        color: var(--gris);
        max-width: 500px;
        margin-bottom: 36px;
    }
    .hero-copy .lead strong { color: var(--ink); font-weight: 600; }

    .hero-ctas { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 40px; }
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 10px;
        padding: 15px 26px;
        border-radius: 999px;
        font-weight: 600;
        font-size: 14.5px;
        cursor: pointer;
        border: 1px solid transparent;
        transition: transform .35s var(--ease), background .25s, color .25s, border-color .25s, box-shadow .35s;
        will-change: transform;
        font-family: inherit;
        margin-top: -10px;
    }
    .btn i { font-size: 13px; transition: transform .3s var(--ease); }
    .btn-primary {
        background: linear-gradient(135deg, var(--azul) 0%, var(--azul-dark) 100%);
        color: #fff;
        box-shadow:
            inset 0 1px 0 rgba(255,255,255,.3),
            0 14px 30px rgba(2, 177, 244, .35);
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow:
            inset 0 1px 0 rgba(255,255,255,.3),
            0 22px 44px rgba(2, 177, 244, .45);
    }
    .btn-primary:hover i { transform: translateX(3px); }
    .btn-glass {
        background: var(--glass-strong);
        color: var(--ink);
        border-color: var(--silver-edge);
        backdrop-filter: blur(20px) saturate(1.6);
        -webkit-backdrop-filter: blur(20px) saturate(1.6);
        box-shadow: inset 0 1px 0 var(--silver-top), var(--sh-soft);
    }
    .btn-glass:hover { background: #fff; transform: translateY(-2px); }

    .hero-trust {
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .trust-avatars { display: flex; }
    .trust-avatars span {
        width: 34px; height: 34px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--azul), var(--azul-dark));
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 11px;
        font-weight: 600;
        margin-left: -8px;
        border: 2px solid #fff;
        box-shadow: 0 4px 10px rgba(12, 26, 46, .12);
    }
    .trust-avatars span:first-child { margin-left: 0; }
    .trust-avatars .sec { background: linear-gradient(135deg, #0ea5e9, #7dd3fc); }
    .trust-avatars .ter { background: linear-gradient(135deg, #8b5cf6, #c4b5fd); }
    .hero-trust .txt { font-size: 13px; color: var(--gris); line-height: 1.4; }
    .hero-trust .txt strong { color: var(--ink); font-weight: 600; }
    .hero-trust .stars { color: #f59e0b; font-size: 11px; letter-spacing: 1px; margin-bottom: 2px; display: block; }

    /* ── Visual hero: panel ecográfico abstracto ── */
    .hero-visual {
        position: relative;
        max-width: 500px;
        margin top: -40px auto 0;
    }
    /* ── Panel "en vivo" del hero (reemplaza el monitor + pills) ── */
    .hero-panel {
        position: relative; z-index: 2;
        margin-top: -100px;
        margin-right: -160px;
        background: var(--glass-strong);
        backdrop-filter: blur(30px) saturate(1.9); -webkit-backdrop-filter: blur(30px) saturate(1.9);
        border: 1px solid var(--silver-edge); border-radius: var(--r-2xl);
        box-shadow: inset 0 1px 0 var(--silver-top), inset 0 -1px 0 var(--silver-bot), var(--sh-deep);
        padding: 30px; display: flex; flex-direction: column; gap: 22px;
        animation: floatY 10s ease-in-out infinite;
    }
    .hp-top { display: flex; align-items: center; justify-content: space-between; }
    .hp-brand { display: flex; align-items: center; gap: 11px; }
    .hp-logo { width: 42px; height: 42px; border-radius: 13px; display: grid; place-items: center; color: #fff; font-size: 1.1rem; background: linear-gradient(135deg, #02b1f4, #0284c7); box-shadow: 0 8px 20px rgba(2,177,244,.42); }
    .hp-brand strong { display: block; font-size: .98rem; font-weight: 700; color: var(--azul-deep); letter-spacing: -.01em; }
    .hp-brand span { font-size: .74rem; color: #64748b; }
    .hp-live { display: inline-flex; align-items: center; gap: 7px; font-size: .72rem; font-weight: 700; color: #15803d; background: #dcfce7; padding: 5px 11px; border-radius: 999px; }
    .hp-live .dot { width: 7px; height: 7px; border-radius: 50%; background: #22c55e; animation: hpPing 1.8s infinite; }
    @keyframes hpPing { 0% { box-shadow: 0 0 0 0 rgba(34,197,94,.5); } 70% { box-shadow: 0 0 0 7px rgba(34,197,94,0); } 100% { box-shadow: 0 0 0 0 rgba(34,197,94,0); } }
    .hp-chart { background: rgba(255,255,255,.5); border: 1px solid var(--silver-edge); border-radius: 16px; padding: 16px 16px 14px; }
    .hp-chart-head { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 14px; font-size: .82rem; }
    .hp-chart-head span { color: #475569; font-weight: 600; } .hp-chart-head b { color: var(--azul-dark); }
    .hp-bars { display: flex; align-items: flex-end; gap: 11px; height: 150px; }
    .hp-bar { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; gap: 7px; height: 100%; }
    .hp-bar i { width: 100%; height: var(--h); min-height: 6px; border-radius: 7px 7px 4px 4px; background: linear-gradient(180deg, #02b1f4, rgba(2,177,244,.32)); transform-origin: bottom; animation: hpGrow .9s var(--ease-spring) backwards; }
    .hp-bar:nth-child(1) i { animation-delay: .05s; } .hp-bar:nth-child(2) i { animation-delay: .11s; } .hp-bar:nth-child(3) i { animation-delay: .17s; }
    .hp-bar:nth-child(4) i { animation-delay: .23s; } .hp-bar:nth-child(5) i { animation-delay: .29s; } .hp-bar:nth-child(6) i { animation-delay: .35s; }
    .hp-bar em { font-size: .64rem; color: #94a3b8; font-weight: 600; font-style: normal; }
    @keyframes hpGrow { from { transform: scaleY(0); } to { transform: scaleY(1); } }
    .hp-metrics { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .hp-metric { display: flex; align-items: center; gap: 11px; background: rgba(255,255,255,.5); border: 1px solid var(--silver-edge); border-radius: 14px; padding: 12px 13px; }
    .hp-metric .ic { width: 40px; height: 40px; border-radius: 12px; display: grid; place-items: center; font-size: 1.05rem; color: var(--c); background: var(--cb); flex-shrink: 0; }
    .hp-metric .t span { display: block; font-size: 1.2rem; font-weight: 800; color: var(--azul-deep); line-height: 1; }
    .hp-metric .t em { font-size: .68rem; color: #64748b; font-style: normal; font-weight: 600; }
    .hp-foot { display: flex; align-items: center; gap: 9px; font-size: .74rem; color: #475569; font-weight: 600; }
    .hp-foot i { color: #02b1f4; }
    .hv-glow {
        position: absolute;
        inset: -10%;
        background:
            radial-gradient(circle at 30% 30%, rgba(2, 177, 244, .35), transparent 55%),
            radial-gradient(circle at 80% 70%, rgba(139, 92, 246, .25), transparent 55%);
        filter: blur(60px);
        z-index: -1;
        animation: glowMove 8s ease-in-out infinite alternate;
    }
    @keyframes glowMove {
        from { transform: scale(1) translate(0, 0); }
        to   { transform: scale(1.1) translate(-3%, 3%); }
    }

    @keyframes floatY {
        0%, 100% { transform: translateY(0); }
        50%      { transform: translateY(-8px); }
    }

    /* ════════════════════════════════════════════════════════════════
       STATS — 4 métricas REALES en cards glass
       ════════════════════════════════════════════════════════════════ */
    .stats-section {
        padding: 60px 0 100px;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
    }
    .stat-card {
        position: relative;
        overflow: hidden;
        padding: 30px 26px 26px;
        border-radius: 24px;
        background: linear-gradient(168deg, rgba(255, 255, 255, .92), rgba(224, 245, 254, .5));
        border: 1px solid rgba(255, 255, 255, .85);
        backdrop-filter: blur(20px) saturate(1.7);
        -webkit-backdrop-filter: blur(20px) saturate(1.7);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .9),
            0 14px 34px rgba(2, 177, 244, .07),
            0 2px 8px rgba(12, 26, 46, .04);
        transition: transform .5s var(--ease), box-shadow .5s var(--ease), border-color .5s var(--ease);
    }
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0; left: 24px; right: 24px;
        height: 3px;
        border-radius: 0 0 3px 3px;
        background: linear-gradient(90deg, var(--azul), var(--azul-deep));
        transform: scaleX(0);
        transition: transform .55s var(--ease);
    }
    .stat-card:hover {
        transform: translateY(-7px);
        border-color: rgba(2, 177, 244, .25);
        box-shadow:
            inset 0 1px 0 rgba(255, 255, 255, .9),
            0 26px 50px rgba(2, 177, 244, .18),
            0 4px 12px rgba(12, 26, 46, .05);
    }
    .stat-card:hover::before { transform: scaleX(1); }
    .stat-card .ico {
        width: 48px; height: 48px;
        border-radius: 15px;
        background: linear-gradient(150deg, rgba(2, 177, 244, .18), rgba(2, 177, 244, .04));
        border: 1px solid rgba(2, 177, 244, .2);
        color: var(--azul-dark);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 19px;
        margin-bottom: 22px;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, .7), 0 6px 16px rgba(2, 177, 244, .14);
        position: relative;
        transition: transform .5s var(--ease-spring);
    }
    .stat-card:hover .ico { transform: translateY(-2px) scale(1.06) rotate(-3deg); }
    .stat-card .num {
        font-size: clamp(40px, 4.6vw, 54px);
        font-weight: 800;
        color: var(--ink);
        line-height: 1;
        letter-spacing: -0.04em;
        font-variant-numeric: tabular-nums;
        margin-bottom: 8px;
        position: relative;
    }
    .stat-card .num .grad {
        background: linear-gradient(120deg, var(--azul) 0%, var(--azul-deep) 100%);
        -webkit-background-clip: text;
        background-clip: text;
        color: transparent;
    }
    .stat-card .lbl {
        font-size: 13.5px;
        color: var(--ink);
        font-weight: 600;
        letter-spacing: -0.01em;
        position: relative;
    }
    .stat-card .sub-meta {
        font-size: 12px;
        color: var(--gris-mute);
        margin-top: 6px;
        font-weight: 500;
        position: relative;
    }

    /* ════════════════════════════════════════════════════════════════
       NOSOTROS — 3 cards glass con icono coloreado
       ════════════════════════════════════════════════════════════════ */
    #nosotros { padding-top: 80px; }
    .nosotros-split { display: grid; grid-template-columns: 0.9fr 1.1fr; gap: 60px; align-items: start; }
    .nosotros-aside { position: sticky; top: 110px; }
    .nosotros-aside .section-title { margin-top: 0; }
    .nosotros-signature { display: flex; align-items: center; gap: 14px; margin-top: 30px; font-weight: 600; color: var(--azul-dark); font-size: 15px; }
    .nosotros-signature .ns-line { width: 46px; height: 2px; background: linear-gradient(90deg, var(--azul), transparent); border-radius: 2px; }
    .nosotros-list { display: flex; flex-direction: column; gap: 18px; }
    .mvv-item {
        display: grid;
        grid-template-columns: auto 1fr;
        gap: 22px;
        align-items: start;
        background: var(--glass);
        backdrop-filter: blur(24px) saturate(1.8);
        -webkit-backdrop-filter: blur(24px) saturate(1.8);
        border: 1px solid var(--silver-edge);
        border-radius: var(--r-xl);
        padding: 28px 30px;
        position: relative;
        overflow: hidden;
        transition: transform .4s var(--ease), box-shadow .4s;
        box-shadow:
            inset 0 1px 0 var(--silver-top),
            inset 0 -1px 0 var(--silver-bot),
            var(--sh-soft);
    }
    .mvv-item:nth-child(2) { margin-left: 44px; }
    .mvv-item::before {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px; height: 100%;
        background: linear-gradient(180deg, var(--azul), var(--azul-dark));
        transform: scaleY(0);
        transform-origin: top;
        transition: transform .5s var(--ease);
    }
    .mvv-item:hover {
        transform: translateY(-4px);
        box-shadow:
            inset 0 1px 0 var(--silver-top),
            inset 0 -1px 0 var(--silver-bot),
            var(--sh-glow);
    }
    .mvv-item:hover::before { transform: scaleY(1); }
    .mvv-icon {
        width: 54px; height: 54px;
        border-radius: 16px;
        background: linear-gradient(135deg, rgba(2,177,244,.15), rgba(2,177,244,.05));
        border: 1px solid var(--silver-edge);
        color: var(--azul-dark);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 21px;
        box-shadow: inset 0 1px 0 var(--silver-top);
    }
    .mvv-body h3 {
        font-size: 21px;
        font-weight: 700;
        margin-bottom: 8px;
        letter-spacing: -0.02em;
    }
    .mvv-body p {
        font-size: 14.5px;
        color: var(--gris);
        line-height: 1.7;
    }

    /* ════════════════════════════════════════════════════════════════
       PROCESO CLÍNICO — 3 pasos conectados
       ════════════════════════════════════════════════════════════════ */
    #proceso { padding-bottom: 130px; }
    .proceso-timeline {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 36px;
        position: relative;
    }
    .proceso-timeline::before {
        content: '';
        position: absolute;
        top: 38px; left: 16%; right: 16%;
        height: 2px;
        background: linear-gradient(90deg, var(--azul-soft), var(--azul), var(--azul-soft));
        z-index: 0;
    }
    .proceso-step { position: relative; z-index: 1; text-align: center; padding: 0 8px; }
    .ps-node {
        width: 76px; height: 76px; border-radius: 50%;
        margin: 0 auto 24px;
        display: grid; place-items: center; position: relative;
        background: var(--glass-strong);
        backdrop-filter: blur(20px) saturate(1.8);
        -webkit-backdrop-filter: blur(20px) saturate(1.8);
        border: 1px solid var(--silver-edge);
        box-shadow: inset 0 1px 0 var(--silver-top), 0 12px 28px rgba(2, 177, 244, .18);
        transition: transform .4s var(--ease-spring), box-shadow .4s var(--ease);
    }
    .proceso-step:hover .ps-node { transform: translateY(-6px) scale(1.06); box-shadow: inset 0 1px 0 var(--silver-top), 0 20px 40px rgba(2, 177, 244, .32); }
    .ps-node i { font-size: 26px; color: var(--azul); }
    .ps-node span {
        position: absolute; top: -8px; right: -8px;
        width: 28px; height: 28px; border-radius: 50%;
        background: linear-gradient(135deg, var(--azul), var(--azul-dark));
        color: #fff; font-size: 12px; font-weight: 700;
        display: grid; place-items: center;
        box-shadow: 0 6px 14px rgba(2, 177, 244, .4);
    }
    .proceso-step h4 { font-size: 20px; font-weight: 700; margin-bottom: 10px; }
    .proceso-step p { font-size: 14.5px; color: var(--gris); line-height: 1.65; max-width: 300px; margin: 0 auto; }

    /* ════════════════════════════════════════════════════════════════
       SERVICIOS — Grid con glow por categoría
       ════════════════════════════════════════════════════════════════ */
    .servicios-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
        gap: 18px;
    }
    .service-card {
        --c1: var(--azul);
        --soft: var(--azul-soft);
        --tcolor: var(--azul-dark);
        background: var(--glass);
        backdrop-filter: blur(24px) saturate(1.8);
        -webkit-backdrop-filter: blur(24px) saturate(1.8);
        border: 1px solid var(--silver-edge);
        border-radius: var(--r-xl);
        padding: 28px 26px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        min-height: 240px;
        position: relative;
        overflow: hidden;
        text-decoration: none;
        color: inherit;
        transition: transform .4s var(--ease), box-shadow .4s, border-color .35s;
        box-shadow:
            inset 0 1px 0 var(--silver-top),
            inset 0 -1px 0 var(--silver-bot),
            var(--sh-soft);
    }
    .service-card::before {
        content: '';
        position: absolute;
        top: -40%; right: -40%;
        width: 180%; height: 180%;
        background: radial-gradient(circle, var(--c1) 0%, transparent 50%);
        opacity: 0;
        transition: opacity .5s var(--ease);
        pointer-events: none;
        filter: blur(40px);
    }
    .service-card:hover {
        transform: translateY(-8px);
        border-color: var(--c1);
        box-shadow:
            inset 0 1px 0 var(--silver-top),
            0 24px 50px color-mix(in srgb, var(--c1) 28%, transparent);
    }
    .service-card:hover::before { opacity: .25; }
    .service-icon {
        width: 50px; height: 50px;
        border-radius: 14px;
        background: linear-gradient(135deg, var(--soft) 0%, rgba(255,255,255,.5) 100%);
        border: 1px solid var(--silver-edge);
        color: var(--tcolor);
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        position: relative;
        z-index: 1;
        box-shadow: inset 0 1px 0 var(--silver-top);
        transition: transform .4s var(--ease-spring), background .35s, color .35s;
    }
    .service-card:hover .service-icon {
        transform: scale(1.08) rotate(-6deg);
        background: linear-gradient(135deg, var(--c1), color-mix(in srgb, var(--c1) 60%, #000 0%));
        color: #fff;
        box-shadow: 0 10px 24px color-mix(in srgb, var(--c1) 35%, transparent);
    }
    .service-cat {
        font-size: 10px;
        text-transform: uppercase;
        letter-spacing: 1.6px;
        font-weight: 700;
        color: var(--tcolor);
        position: relative;
        z-index: 1;
    }
    .service-card h3 {
        font-size: 17px;
        font-weight: 700;
        color: var(--ink);
        line-height: 1.3;
        margin: 0;
        position: relative;
        z-index: 1;
    }
    .service-card p {
        font-size: 13.5px;
        color: var(--gris-soft);
        line-height: 1.6;
        margin: 0;
        flex: 1;
        position: relative;
        z-index: 1;
    }
    .service-link {
        font-size: 12.5px;
        font-weight: 600;
        color: var(--ink);
        display: inline-flex;
        align-items: center;
        gap: 8px;
        position: relative;
        z-index: 1;
        transition: gap .3s var(--ease), color .25s;
    }
    .service-link i { font-size: 10px; transition: transform .3s var(--ease); }
    .service-card:hover .service-link { gap: 14px; color: var(--tcolor); }

    /* ════════════════════════════════════════════════════════════════
       BENEFICIOS — Features minimal
       ════════════════════════════════════════════════════════════════ */
    .beneficios-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 18px;
    }
    .beneficio {
        background: var(--glass);
        backdrop-filter: blur(22px) saturate(1.8);
        -webkit-backdrop-filter: blur(22px) saturate(1.8);
        border: 1px solid var(--silver-edge);
        border-radius: var(--r-lg);
        padding: 32px 26px;
        text-align: center;
        transition: transform .4s var(--ease), box-shadow .4s;
        box-shadow:
            inset 0 1px 0 var(--silver-top),
            inset 0 -1px 0 var(--silver-bot),
            var(--sh-soft);
    }
    .beneficio:hover {
        transform: translateY(-4px);
        box-shadow:
            inset 0 1px 0 var(--silver-top),
            inset 0 -1px 0 var(--silver-bot),
            var(--sh-glow);
    }
    .beneficio-icon {
        width: 58px; height: 58px;
        margin: 0 auto 18px;
        border-radius: 16px;
        background: linear-gradient(135deg, var(--azul) 0%, var(--azul-dark) 100%);
        color: #fff;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        box-shadow:
            inset 0 1px 0 rgba(255,255,255,.3),
            0 10px 22px rgba(2, 177, 244, .3);
    }
    .beneficio h4 {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 8px;
    }
    .beneficio p {
        font-size: 13.5px;
        color: var(--gris-soft);
        line-height: 1.6;
    }

    /* ════════════════════════════════════════════════════════════════
       CONTACTO — info glass + form glass
       ════════════════════════════════════════════════════════════════ */
    .contacto-grid {
        display: grid;
        grid-template-columns: 1fr 1.25fr;
        gap: 24px;
        align-items: stretch;
    }
    .contacto-info {
        background: linear-gradient(160deg, var(--ink) 0%, var(--azul-deep) 100%);
        color: #fff;
        border-radius: var(--r-xl);
        padding: 48px 40px;
        position: relative;
        overflow: hidden;
        box-shadow: var(--sh-deep);
    }
    .contacto-info::before {
        content: '';
        position: absolute;
        bottom: -80px; right: -80px;
        width: 280px; height: 280px;
        background: radial-gradient(circle, rgba(2,177,244,.4), transparent 60%);
        filter: blur(20px);
    }
    .contacto-info::after {
        content: '';
        position: absolute;
        top: -100px; left: -100px;
        width: 240px; height: 240px;
        background: radial-gradient(circle, rgba(139, 92, 246, .25), transparent 65%);
        filter: blur(30px);
    }
    .contacto-info h3 {
        font-size: 28px;
        margin-bottom: 12px;
        color: #fff;
        position: relative;
        font-weight: 700;
        letter-spacing: -0.02em;
    }
    .contacto-info > p {
        font-size: 14.5px;
        color: rgba(255,255,255,.75);
        margin-bottom: 36px;
        position: relative;
        max-width: 320px;
        line-height: 1.65;
    }
    .contacto-info-item {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 22px;
        position: relative;
    }
    .contacto-info-item i {
        width: 40px; height: 40px;
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 12px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 14px;
        color: rgba(255,255,255,.9);
        flex-shrink: 0;
        backdrop-filter: blur(10px);
    }
    .contacto-info-item .lbl {
        font-size: 10.5px;
        text-transform: uppercase;
        letter-spacing: 1.6px;
        color: rgba(255,255,255,.55);
        margin-bottom: 4px;
        font-weight: 500;
    }
    .contacto-info-item .val {
        font-size: 14.5px;
        font-weight: 600;
        color: #fff;
    }
    .contacto-socials {
        display: flex;
        gap: 10px;
        margin-top: 32px;
        position: relative;
    }
    .contacto-socials a {
        width: 42px; height: 42px;
        background: rgba(255,255,255,.08);
        border: 1px solid rgba(255,255,255,.14);
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        font-size: 14px;
        backdrop-filter: blur(10px);
        transition: background .25s, transform .25s var(--ease), border-color .25s;
    }
    .contacto-socials a:hover {
        background: var(--azul);
        border-color: var(--azul);
        transform: translateY(-2px);
    }

    .formulario {
        background: var(--glass-strong);
        backdrop-filter: blur(28px) saturate(1.8);
        -webkit-backdrop-filter: blur(28px) saturate(1.8);
        border: 1px solid var(--silver-edge);
        border-radius: var(--r-xl);
        padding: 48px 42px;
        box-shadow:
            inset 0 1px 0 var(--silver-top),
            inset 0 -1px 0 var(--silver-bot),
            var(--sh-soft);
    }
    .formulario h3 {
        font-size: 26px;
        font-weight: 700;
        margin-bottom: 6px;
        letter-spacing: -0.02em;
    }
    .formulario .form-sub {
        font-size: 14px;
        color: var(--gris-soft);
        margin-bottom: 28px;
    }
    .input-group { display: flex; flex-direction: column; gap: 14px; }
    .input-container {
        position: relative;
        display: flex;
        align-items: center;
    }
    .input-container > i {
        position: absolute;
        left: 16px;
        color: var(--gris-mute);
        font-size: 14px;
        pointer-events: none;
        z-index: 1;
        transition: color .25s;
    }
    .input-container input,
    .input-container textarea {
        width: 100%;
        padding: 14px 16px 14px 46px;
        border: 1px solid var(--silver-edge);
        border-radius: 14px;
        font-size: 14.5px;
        background: rgba(255,255,255,.6);
        backdrop-filter: blur(10px);
        color: var(--ink);
        font-family: inherit;
        transition: border-color .25s, background .25s, box-shadow .3s var(--ease);
    }
    .input-container input::placeholder { color: var(--gris-mute); }
    .input-container input:focus,
    .input-container textarea:focus {
        outline: none;
        border-color: var(--azul);
        background: #fff;
        box-shadow: 0 0 0 4px rgba(2, 177, 244, .14);
    }
    .input-container:focus-within > i { color: var(--azul-dark); }

    .cedula-group { padding: 0 !important; border: none !important; }
    .cedula-group .cedula-select {
        background: rgba(255,255,255,.6);
        backdrop-filter: blur(10px);
        border: 1px solid var(--silver-edge);
        border-right: none;
        border-radius: 14px 0 0 14px;
        padding: 14px 14px;
        font-weight: 700;
        color: var(--ink);
        cursor: pointer;
        font-family: inherit;
        font-size: 14px;
        outline: none;
    }
    .cedula-group .cedula-input {
        border-radius: 0 14px 14px 0 !important;
        padding-left: 16px !important;
    }
    .cedula-group:focus-within .cedula-select {
        border-color: var(--azul);
        background: #fff;
    }

    .btn-submit {
        width: 100%;
        padding: 16px;
        border: none;
        background: linear-gradient(135deg, var(--azul) 0%, var(--azul-dark) 100%);
        color: #fff;
        font-size: 14.5px;
        font-weight: 600;
        border-radius: 14px;
        cursor: pointer;
        margin-top: 10px;
        box-shadow:
            inset 0 1px 0 rgba(255,255,255,.3),
            0 14px 30px rgba(2, 177, 244, .35);
        transition: transform .25s var(--ease), box-shadow .3s;
        font-family: inherit;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
    }
    .btn-submit:hover {
        transform: translateY(-2px);
        box-shadow:
            inset 0 1px 0 rgba(255,255,255,.3),
            0 22px 44px rgba(2, 177, 244, .45);
    }
    .btn-submit i { transition: transform .3s var(--ease); }
    .btn-submit:hover i { transform: translateX(3px); }

    .form-legal {
        font-size: 11.5px;
        color: var(--gris-mute);
        text-align: center;
        margin-top: 16px;
        line-height: 1.5;
    }

    /* ════════════════════════════════════════════════════════════════
       FOOTER
       ════════════════════════════════════════════════════════════════ */
    .footer {
        background: linear-gradient(180deg, transparent 0%, rgba(12, 26, 46, .04) 100%);
        padding: 70px 0 24px;
        margin-top: 60px;
        border-top: 1px solid var(--silver-edge);
    }
    .footer-grid {
        display: grid;
        grid-template-columns: 1.8fr 1fr 1fr 1fr;
        gap: 48px;
        margin-bottom: 48px;
        padding-bottom: 36px;
        border-bottom: 1px solid var(--silver-edge);
    }
    .footer-brand .logo { margin-bottom: 16px; }
    .footer-brand p {
        font-size: 13.5px;
        color: var(--gris);
        max-width: 340px;
        line-height: 1.7;
    }
    .footer h5 {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: var(--ink);
        margin-bottom: 18px;
        font-weight: 700;
    }
    .footer ul { list-style: none; }
    .footer ul li { margin-bottom: 11px; font-size: 13.5px; }
    .footer ul a {
        color: var(--gris);
        transition: color .25s, padding-left .25s var(--ease);
        display: inline-block;
    }
    .footer ul a:hover { color: var(--azul-dark); padding-left: 4px; }
    .footer ul li i { color: var(--gris-mute); margin-right: 8px; font-size: 11px; }
    .footer-bottom {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 10px;
        font-size: 12.5px;
        color: var(--gris-soft);
    }
    .footer-bottom .made { font-weight: 500; color: var(--azul-dark); }

    /* ════════════════════════════════════════════════════════════════
       REVEAL ANIMATIONS
       ════════════════════════════════════════════════════════════════ */
    .reveal {
        opacity: 0;
        transform: translateY(28px);
        transition: opacity .9s var(--ease), transform .9s var(--ease);
    }
    .reveal.in { opacity: 1; transform: none; }
    .reveal[data-delay="1"] { transition-delay: .08s; }
    .reveal[data-delay="2"] { transition-delay: .16s; }
    .reveal[data-delay="3"] { transition-delay: .24s; }
    .reveal[data-delay="4"] { transition-delay: .32s; }

    @media (prefers-reduced-motion: reduce) {
        *, *::before, *::after {
            animation-duration: .01ms !important;
            transition-duration: .01ms !important;
        }
        .reveal { opacity: 1; transform: none; filter: none; }
        .scroll-progress, .hero-aurora { display: none; }
    }

    /* ════════════════════════════════════════════════════════════════
       PREMIUM ENHANCE — capa de pulido (movimiento + materialidad)
       Aditiva: conserva azul/glass. Respeta prefers-reduced-motion.
       ════════════════════════════════════════════════════════════════ */

    /* Barra de progreso de scroll (CSS scroll-driven, sin JS) */
    .scroll-progress {
        position: fixed; top: 0; left: 0; height: 3px; width: 100%;
        transform: scaleX(0); transform-origin: 0 50%; z-index: 1200;
        background: linear-gradient(90deg, var(--azul), var(--azul-deep));
        box-shadow: 0 0 12px rgba(2, 177, 244, .5);
        animation: scrollGrow linear both;
        animation-timeline: scroll(root);
    }
    @keyframes scrollGrow { to { transform: scaleX(1); } }

    /* Aurora azul viva detrás del hero */
    .hero { overflow: hidden; }
    .hero > .container { position: relative; z-index: 1; }
    .hero-aurora { position: absolute; inset: -25% -12% auto -12%; height: 135%; z-index: 0; pointer-events: none; opacity: .85; }
    .hero-aurora::before, .hero-aurora::after { content: ""; position: absolute; border-radius: 50%; filter: blur(10px); }
    .hero-aurora::before {
        width: 54vw; height: 54vw; top: -12vw; right: -4vw;
        background: radial-gradient(circle at center, rgba(2, 177, 244, .26), transparent 62%);
        animation: auroraDrift 20s ease-in-out infinite;
    }
    .hero-aurora::after {
        width: 42vw; height: 42vw; bottom: -14vw; left: -2vw;
        background: radial-gradient(circle at center, rgba(1, 74, 130, .16), transparent 60%);
        animation: auroraDrift 28s ease-in-out infinite reverse;
    }
    @keyframes auroraDrift {
        0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
        33%      { transform: translate3d(3vw, 2vw, 0) scale(1.08); }
        66%      { transform: translate3d(-2vw, 1vw, 0) scale(.95); }
    }

    /* Sheen (brillo) que cruza el CTA primario al hover */
    .btn-primary { position: relative; overflow: hidden; }
    .btn-primary::after {
        content: ""; position: absolute; top: 0; bottom: 0; left: -120%; width: 55%;
        background: linear-gradient(100deg, transparent, rgba(255, 255, 255, .5), transparent);
        transform: skewX(-18deg); transition: left .75s var(--ease); pointer-events: none;
    }
    .btn-primary:hover::after { left: 150%; }

    /* Spotlight-follow en tarjetas (vars --mx/--my desde JS) */
    .stat-card, .beneficio { position: relative; }
    .stat-card::after, .beneficio::after {
        content: ""; position: absolute; inset: 0; border-radius: inherit; pointer-events: none;
        opacity: 0; transition: opacity .45s var(--ease);
        background: radial-gradient(360px circle at var(--mx, 50%) var(--my, 0%), rgba(2, 177, 244, .15), transparent 60%);
    }
    .stat-card:hover::after, .beneficio:hover::after { opacity: 1; }

    /* Subrayado animado en enlaces del nav */
    .navbar a:not(.nav-cta) { position: relative; }
    .navbar a:not(.nav-cta)::after {
        content: ""; position: absolute; left: 12px; right: 12px; bottom: 5px; height: 2px;
        background: var(--azul); border-radius: 2px; transform: scaleX(0); transform-origin: 0 50%;
        transition: transform .35s var(--ease); pointer-events: none;
    }
    .navbar a:not(.nav-cta):hover::after { transform: scaleX(1); }

    /* Reveal mejorado: blur-in con el mismo easing */
    .reveal { filter: blur(7px); transition: opacity .9s var(--ease), transform .9s var(--ease), filter .9s var(--ease); }
    .reveal.in { filter: blur(0); }

    /* ════════════════════════════════════════════════════════════════
       RESPONSIVE
       ════════════════════════════════════════════════════════════════ */
    @media (max-width: 1080px) {
        section { padding: 100px 0; }
        .hero-grid { gap: 48px; }
    }
    @media (max-width: 960px) {
        .hero { padding-top: 100px; padding-bottom: 70px; }
        .hero-grid { grid-template-columns: 1fr; gap: 60px; }
        .hero-visual { margin: 0 auto; max-width: 440px; }
        .stats-grid { grid-template-columns: repeat(2, 1fr); }
        .nosotros-split { grid-template-columns: 1fr; gap: 40px; }
        .nosotros-aside { position: static; }
        .mvv-item:nth-child(2) { margin-left: 0; }
        .proceso-timeline { grid-template-columns: 1fr; gap: 30px; }
        .proceso-timeline::before { display: none; }
        .beneficios-grid { grid-template-columns: repeat(2, 1fr); }
        .contacto-grid { grid-template-columns: 1fr; gap: 22px; }
        .footer-grid { grid-template-columns: 1fr 1fr; gap: 36px; }
    }
    @media (max-width: 640px) {
        :root { --gutter: 20px; }
        section { padding: 80px 0; }
        .navbar ul { display: none; }
        .navbar ul.open {
            display: flex;
            position: absolute;
            top: calc(100% + 10px); left: 0; right: 0;
            background: var(--glass-strong);
            backdrop-filter: blur(24px) saturate(1.8);
            -webkit-backdrop-filter: blur(24px) saturate(1.8);
            border: 1px solid var(--silver-edge);
            flex-direction: column;
            padding: 14px;
            border-radius: var(--r-lg);
            box-shadow: var(--sh-deep);
            gap: 4px;
        }
        .navbar ul.open a { padding: 12px 16px; width: 100%; }
        .navbar ul.open .nav-cta { margin-left: 0; margin-top: 6px; }
        .hamburger { display: inline-flex; }
        .servicios-grid { grid-template-columns: 1fr 1fr; }
        .beneficios-grid { grid-template-columns: 1fr; }
        .footer-grid { grid-template-columns: 1fr; gap: 32px; }
        .formulario, .contacto-info { padding: 36px 26px; }
    }
    </style>
</head>
<body>

<div id="scroll-progress"></div>

<?php if (isset($_GET['status'])): ?>
    <?php
    $mensaje = ''; $clase_css = '';
    if ($_GET['status'] == 'success') { $mensaje = '¡Solicitud enviada con éxito! Nos pondremos en contacto pronto.'; $clase_css = 'mensaje-exito'; }
    elseif ($_GET['status'] == 'error') { $mensaje = 'Hubo un error al enviar tu consulta. Inténtalo de nuevo.'; $clase_css = 'mensaje-error'; }
    if ($mensaje) { echo "<div class='mensaje-estado $clase_css' id='msg-estado'>$mensaje</div>"; }
    ?>
<?php endif; ?>

<!-- ══════════ HEADER ══════════ -->
<header id="inicio" class="header">
    <div class="menu">
        <a href="#inicio" class="logo">
            <span class="logo-icon"><i class="fa-solid fa-wave-square"></i></span>
            <span class="logo-text">
                EcoMadelleine
                <small>Centro de Diagnóstico</small>
            </span>
        </a>
        <nav class="navbar">
            <ul id="nav-list">
                <li><a href="#nosotros">Nosotros</a></li>
                <li><a href="#proceso">Proceso</a></li>
                <li><a href="#servicios">Estudios</a></li>
                <li><a href="#beneficios">Beneficios</a></li>
                <li><a href="#contacto">Contacto</a></li>
                <li><a href="<?= eco_url('login') ?>" class="nav-cta"><i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión</a></li>
            </ul>
            <button type="button" class="hamburger" id="hamburger" aria-label="Menú">
                <i class="fa-solid fa-bars"></i>
            </button>
        </nav>
    </div>
</header>

<!-- ══════════ HERO ══════════ -->
<div class="scroll-progress" aria-hidden="true"></div>
<section class="hero">
    <div class="hero-aurora" aria-hidden="true"></div>
    <div class="container hero-grid">
        <div class="hero-copy reveal">
            <span class="hero-tag">
                Centro de Diagnóstico por Ultrasonido
            </span>
            <h1>
                Imagen clínica<br>
                <span class="grad">de alta resolución</span><br>
                con criterio humano.
            </h1>
            <p class="lead">
                Estudios ecográficos realizados personalmente por la doctora, con
                <strong>informes digitales detallados</strong> y agenda en línea.
                Tecnología de punta al servicio de tu salud.
            </p>
            <div class="hero-ctas">
                <a href="#contacto" class="btn btn-primary">
                    Agendar estudio <i class="fa-solid fa-arrow-right"></i>
                </a>
                <a href="<?= eco_url('login') ?>" class="btn btn-glass">
                    <i class="fa-solid fa-right-to-bracket"></i> Iniciar sesión
                </a>
            </div>
            <div class="hero-trust">
                <div class="trust-avatars">
                    <span>MT</span>
                    <span class="sec">EM</span>
                    <span class="ter">+</span>
                </div>
                <div class="txt">
                    <span class="stars"><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i></span>
                    <?php if ($total_pacientes > 0): ?>
                        <strong><?php echo number_format($total_pacientes, 0, ',', '.'); ?></strong> pacientes confiaron en nosotros
                    <?php else: ?>
                        Centro <strong>recién inaugurado</strong> · sé uno de los primeros
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="hero-visual reveal" data-delay="2">
            <div class="hv-glow"></div>
                        <div class="hero-panel">
                <div class="hp-top">
                    <div class="hp-brand">
                        <span class="hp-logo"><i class="fa-solid fa-wave-square"></i></span>
                        <div><strong>Panel EcoMadelleine</strong><span>Resumen del centro</span></div>
                    </div>
                    <span class="hp-live"><span class="dot"></span> En vivo</span>
                </div>
                <div class="hp-chart">
                    <div class="hp-chart-head"><span>Estudios por mes</span><b><?php echo $an_total_estudios; ?> en 6 m</b></div>
                    <div class="hp-bars">
                        <?php $hp_max = max(1, max($an_meses_val)); foreach ($an_meses_val as $hp_i => $hp_v): $hp_h = max(8, (int)round($hp_v / $hp_max * 100)); ?>
                        <div class="hp-bar"><i style="--h: <?php echo $hp_h; ?>%"></i><em><?php echo htmlspecialchars($an_meses_lbl[$hp_i]); ?></em></div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="hp-metrics">
                    <div class="hp-metric">
                        <div class="ic" style="--c: #0284c7; --cb: #e0f5fe;"><i class="fa-solid fa-file-signature"></i></div>
                        <div class="t"><span><?php echo $total_informes; ?></span><em>Informes firmados</em></div>
                    </div>
                    <div class="hp-metric">
                        <div class="ic" style="--c: #15803d; --cb: #dcfce7;"><i class="fa-solid fa-circle-check"></i></div>
                        <div class="t"><span><?php echo htmlspecialchars($f_tasa['value']); ?></span><em><?php echo htmlspecialchars($f_tasa['label']); ?></em></div>
                    </div>
                </div>
                <div class="hp-foot"><i class="fa-solid fa-shield-halved"></i> Datos clínicos confidenciales · firma verificable</div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════ STATS REALES ══════════ -->
<section class="stats-section">
    <div class="container">
        <div class="section-head section-head--center reveal" style="margin-bottom:48px;">
            <span class="eyebrow"><i class="fa-solid fa-chart-line"></i> Datos en tiempo real</span>
            <h2 class="section-title">Cifras del centro,<br><span class="grad">extraídas del sistema.</span></h2>
        </div>
        <div class="stats-grid">
            <div class="stat-card reveal">
                <div class="ico"><i class="fa-solid fa-user-group"></i></div>
                <div class="num"><span class="grad" data-counter="<?php echo $total_pacientes; ?>" data-suffix="<?php echo $total_pacientes > 0 ? '+' : ''; ?>"><?php echo $total_pacientes > 0 ? '0' : '-'; ?></span></div>
                <div class="lbl"><?php echo htmlspecialchars($f_pac['label']); ?></div>
                <?php if ($total_pacientes == 0): ?>
                    <div class="sub-meta">Sistema recién activo</div>
                <?php endif; ?>
            </div>
            <div class="stat-card reveal" data-delay="1">
                <div class="ico"><i class="fa-solid fa-wave-square"></i></div>
                <div class="num"><span class="grad" data-counter="<?php echo $total_tipos; ?>" data-suffix="<?php echo $total_tipos > 0 ? '+' : ''; ?>"><?php echo $total_tipos > 0 ? '0' : '-'; ?></span></div>
                <div class="lbl"><?php echo htmlspecialchars($f_tip['label']); ?></div>
                <div class="sub-meta">Esquema clínico dinámico</div>
            </div>
            <div class="stat-card reveal" data-delay="2">
                <div class="ico"><i class="fa-solid fa-clock"></i></div>
                <div class="num"><span class="grad" data-counter="<?php echo $avg_horas > 0 ? $avg_horas : 24; ?>" data-suffix="h"><?php echo $avg_horas > 0 ? '0' : '24'; ?></span></div>
                <div class="lbl"><?php echo htmlspecialchars($f_hrs['label']); ?></div>
                <?php if ($avg_horas > 0): ?>
                    <div class="sub-meta"><?php echo $total_informes; ?> informe<?php echo $total_informes !== 1 ? 's' : ''; ?> medidos</div>
                <?php else: ?>
                    <div class="sub-meta">SLA garantizado</div>
                <?php endif; ?>
            </div>
            <div class="stat-card reveal" data-delay="3">
                <div class="ico"><i class="fa-solid fa-heart-pulse"></i></div>
                <div class="num"><span class="grad" data-counter="<?php echo $tasa_conclusion > 0 ? $tasa_conclusion : 100; ?>" data-suffix="%"><?php echo $tasa_conclusion > 0 ? '0' : '100'; ?></span></div>
                <div class="lbl"><?php echo htmlspecialchars($f_tasa['label']); ?></div>
                <?php if ($tasa_conclusion > 0): ?>
                    <div class="sub-meta">Sobre <?php echo (int)$row['gestionadas']; ?> citas gestionadas</div>
                <?php else: ?>
                    <div class="sub-meta">Excelencia en cada estudio</div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>

<!-- ══════════ PANEL DE ANALÍTICAS ══════════ -->
<style>
    .analytics-section { padding: 80px 0; position: relative; }
    .analytics-grid { display: grid; grid-template-columns: 1.35fr 1fr; gap: 24px; align-items: stretch; }
    .chart-card { padding: 30px 30px 26px; border-radius: var(--r-lg); display: flex; flex-direction: column; }
    .chart-card .cc-head { display: flex; align-items: baseline; justify-content: space-between; gap: 14px; margin-bottom: 22px; }
    .chart-card .cc-head h3 { font-size: 1.18rem; font-weight: 700; color: var(--azul-deep); letter-spacing: -.01em; }
    .chart-card .cc-sub { font-size: .76rem; font-weight: 700; color: var(--azul-dark); background: var(--azul-soft); padding: 5px 12px; border-radius: 999px; white-space: nowrap; }
    .chart-card .cc-canvas { position: relative; flex: 1; min-height: 290px; }
    @media (max-width: 880px) {
        .analytics-grid { grid-template-columns: 1fr; }
        .chart-card .cc-canvas { min-height: 250px; }
    }
</style>
<section id="analiticas" class="analytics-section">
    <div class="container">
        <div class="section-head section-head--center reveal" style="margin-bottom:48px;">
            <h2 class="section-title">El pulso del centro,<br><span class="grad">en datos reales.</span></h2>
        </div>
        <div class="analytics-grid">
            <div class="chart-card glass reveal">
                <div class="cc-head">
                    <h3>Estudios por mes</h3>
                    <span class="cc-sub"><?php echo $an_total_estudios; ?> en 6 meses</span>
                </div>
                <div class="cc-canvas"><canvas id="anChartMeses"></canvas></div>
            </div>
            <div class="chart-card glass reveal" data-delay="1">
                <div class="cc-head">
                    <h3>Estado de las citas</h3>
                    <span class="cc-sub"><?php echo $an_total_citas; ?> citas</span>
                </div>
                <div class="cc-canvas"><canvas id="anChartCitas"></canvas></div>
            </div>
        </div>
    </div>
</section>

<!-- ══════════ NOSOTROS ══════════ -->
<section id="nosotros">
    <div class="container nosotros-split">
        <div class="nosotros-aside reveal">
            <h2 class="section-title">Compromiso clínico,<br><span class="grad">criterio humano.</span></h2>
            <p class="section-sub">Un centro especializado donde cada estudio es realizado por la doctora y entregado con el detalle que tu salud merece.</p>
            <div class="nosotros-signature">
                <span class="ns-line"></span>
                <span>Dra. Madelleine Toro</span>
            </div>
        </div>

        <div class="nosotros-list">
            <article class="mvv-item reveal" data-delay="1">
                <div class="mvv-icon"><i class="fa-solid fa-bullseye"></i></div>
                <div class="mvv-body">
                    <h3>Misión</h3>
                    <p><?php echo nl2br(htmlspecialchars($contenido_web['mision'] ?? 'Brindar diagnóstico ecográfico de excelencia con calidez humana y precisión médica, acompañando a cada paciente desde el agendamiento hasta la entrega del informe.')); ?></p>
                </div>
            </article>
            <article class="mvv-item reveal" data-delay="2">
                <div class="mvv-icon"><i class="fa-solid fa-eye"></i></div>
                <div class="mvv-body">
                    <h3>Visión</h3>
                    <p><?php echo nl2br(htmlspecialchars($contenido_web['vision'] ?? 'Ser referencia regional en diagnóstico por imagen, integrando tecnología, criterio clínico y un trato profundamente humano en cada estudio.')); ?></p>
                </div>
            </article>
            <article class="mvv-item reveal" data-delay="3">
                <div class="mvv-icon"><i class="fa-solid fa-heart"></i></div>
                <div class="mvv-body">
                    <h3>Valores</h3>
                    <p><?php echo nl2br(htmlspecialchars($contenido_web['valores'] ?? 'Integridad. Precisión. Confidencialidad. Empatía. Excelencia en cada informe que firmamos.')); ?></p>
                </div>
            </article>
        </div>
    </div>
</section>

<!-- ══════════ PROCESO ══════════ -->
<section id="proceso">
    <div class="container">
        <div class="section-head section-head--center reveal">
            <h2 class="section-title">Tres pasos.<br><span class="grad">Cero fricción.</span></h2>
            <p class="section-sub">Desde el agendamiento hasta el informe firmado, el proceso está pensado para que tu única preocupación sea tu salud.</p>
        </div>

        <div class="proceso-timeline">
            <div class="proceso-step reveal" data-delay="1">
                <div class="ps-node"><span>01</span><i class="fa-regular fa-calendar-check"></i></div>
                <h4>Agendas en línea</h4>
                <p>Reserva tu cita 24/7 desde el panel. Recibes confirmación por correo y recordatorio antes del estudio.</p>
            </div>
            <div class="proceso-step reveal" data-delay="2">
                <div class="ps-node"><span>02</span><i class="fa-solid fa-wave-square"></i></div>
                <h4>Estudio con la doctora</h4>
                <p>La Dra. Madelleine Toro realiza personalmente la ecografía y captura los hallazgos en el formulario clínico estructurado.</p>
            </div>
            <div class="proceso-step reveal" data-delay="3">
                <div class="ps-node"><span>03</span><i class="fa-regular fa-file-lines"></i></div>
                <h4>Informe en 24 horas</h4>
                <p>Recibes el informe en PDF profesional listo para tu médico tratante, con esquema clínico detallado por tipo de estudio.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══════════ SERVICIOS ══════════ -->
<section id="servicios">
    <div class="container">
        <div class="section-head section-head--center reveal">
            <span class="eyebrow"><i class="fa-solid fa-wave-square"></i> Cartera clínica</span>
            <h2 class="section-title">Nuestros estudios<br><span class="grad">ecográficos.</span></h2>
            <p class="section-sub">Esquemas dinámicos por tipo de estudio (Renal, Abdominal, Pélvica, Obstétrica y más) con captura estructurada e informes listos para impresión profesional.</p>
        </div>

        <div class="servicios-grid">
            <?php
            $tipos_publicos = $conex->query("SELECT codigo, nombre, categoria, descripcion, icono FROM tipos_ecografias WHERE activo = 1 AND (categoria IS NULL OR categoria NOT IN ('Musculoesqueletica_Sub', 'Obstetrica_Sub', 'Partes_Blandas_Sub')) ORDER BY categoria, nombre");
            $idx = 0;
            if ($tipos_publicos && $tipos_publicos->num_rows > 0):
                while ($t = $tipos_publicos->fetch_assoc()):
                    $cat = $t['categoria'] ?? '';
                    $pal = $eco_palette[$cat] ?? $eco_palette_default;
                    $icono = htmlspecialchars($t['icono'] ?: 'fa-solid fa-wave-square');
                    $desc  = htmlspecialchars(mb_strimwidth($t['descripcion'] ?? 'Estudio ecográfico clínico con informe detallado.', 0, 95, '…', 'UTF-8'));
                    $delay = ($idx % 4) + 1;
                    $idx++;
            ?>
                <a href="<?= eco_url('login') ?>" class="service-card reveal" data-delay="<?php echo $delay; ?>"
                   style="--c1:<?php echo $pal['c1']; ?>;--soft:<?php echo $pal['soft']; ?>;--tcolor:<?php echo $pal['text']; ?>;">
                    <div class="service-icon"><i class="<?php echo $icono; ?>"></i></div>
                    <?php if ($cat !== ''): ?>
                        <span class="service-cat"><?php echo htmlspecialchars($cat); ?></span>
                    <?php endif; ?>
                    <h3><?php echo htmlspecialchars($t['nombre']); ?></h3>
                    <p><?php echo $desc; ?></p>
                    <span class="service-link">Ver detalles <i class="fa-solid fa-arrow-right"></i></span>
                </a>
            <?php
                endwhile;
            else:
            ?>
                <a href="<?= eco_url('login') ?>" class="service-card"><div class="service-icon"><i class="fa-solid fa-wave-square"></i></div><h3>Ecografía Abdominal</h3></a>
                <a href="<?= eco_url('login') ?>" class="service-card"><div class="service-icon"><i class="fa-solid fa-baby"></i></div><h3>Ecografía Obstétrica</h3></a>
                <a href="<?= eco_url('login') ?>" class="service-card"><div class="service-icon"><i class="fa-solid fa-user-doctor"></i></div><h3>Ecografía de Tiroides</h3></a>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- ══════════ BENEFICIOS ══════════ -->
<section id="beneficios">
    <div class="container">
        <div class="section-head section-head--center reveal">
            <h2 class="section-title">La diferencia<br><span class="grad">está en el detalle.</span></h2>
            <p class="section-sub">Cuatro pilares que separan nuestro enfoque del de un centro tradicional.</p>
        </div>

        <div class="beneficios-grid">
            <div class="beneficio reveal" data-delay="1">
                <div class="beneficio-icon"><i class="fa-solid fa-user-doctor"></i></div>
                <h4>Atención personalizada</h4>
                <p>Cada estudio es realizado e interpretado directamente por la doctora.</p>
            </div>
            <div class="beneficio reveal" data-delay="2">
                <div class="beneficio-icon"><i class="fa-solid fa-file-waveform"></i></div>
                <h4>Informes digitales</h4>
                <p>Formularios estructurados y descarga PDF lista para imprimir.</p>
            </div>
            <div class="beneficio reveal" data-delay="3">
                <div class="beneficio-icon"><i class="fa-solid fa-bolt"></i></div>
                <h4>Entrega en 24h</h4>
                <p>Resultados rápidos sin sacrificar el detalle clínico necesario.</p>
            </div>
            <div class="beneficio reveal" data-delay="4">
                <div class="beneficio-icon"><i class="fa-solid fa-shield-halved"></i></div>
                <h4>Datos confidenciales</h4>
                <p>Tu historial protegido en un sistema seguro y privado.</p>
            </div>
        </div>
    </div>
</section>

<!-- ══════════ CONTACTO ══════════ -->
<section id="contacto">
    <div class="container">
        <div class="section-head section-head--center reveal">
            <h2 class="section-title">Crea tu cuenta<br><span class="grad">y solicita tu estudio.</span></h2>
            <p class="section-sub">Te contactaremos en menos de 24 horas para confirmar el día y la hora de tu ecografía.</p>
        </div>

        <div class="contacto-grid">
            <aside class="contacto-info reveal">
                <h3>Conversemos.</h3>
                <p>Resolvemos cualquier duda sobre tu estudio antes y después de la consulta.</p>

                <div class="contacto-info-item">
                    <i class="fa-solid fa-phone"></i>
                    <div>
                        <div class="lbl">Teléfono</div>
                        <div class="val">0412-8517770</div>
                    </div>
                </div>
                <div class="contacto-info-item">
                    <i class="fa-regular fa-envelope"></i>
                    <div>
                        <div class="lbl">Correo</div>
                        <div class="val">contacto@ecomadelleine.com</div>
                    </div>
                </div>
                <div class="contacto-info-item">
                    <i class="fa-solid fa-location-dot"></i>
                    <div>
                        <div class="lbl">Consultorio</div>
                        <div class="val">Centro de Diagnóstico EcoMadelleine</div>
                    </div>
                </div>
                <div class="contacto-info-item">
                    <i class="fa-regular fa-clock"></i>
                    <div>
                        <div class="lbl">Horario</div>
                        <div class="val">Lun a Vie · 8:00 a 17:00</div>
                    </div>
                </div>

                <div class="contacto-socials">
                    <a href="#" aria-label="Instagram"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#" aria-label="WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
                    <a href="#" aria-label="Facebook"><i class="fa-brands fa-facebook-f"></i></a>
                </div>
            </aside>

            <div class="formulario reveal" data-delay="1">
                <h3>Crea tu cuenta</h3>
                <p class="form-sub">Regístrate para agendar tu próxima ecografía.</p>
                <form method="post" autocomplete="off">
                    <div class="input-group">
                        <div class="input-container">
                            <i class="fa-solid fa-user"></i>
                            <input type="text" name="name" placeholder="Nombre y Apellido" required>
                        </div>
                        <div class="input-container">
                            <i class="fa-solid fa-calendar-day"></i>
                            <input type="text" id="fecha_nacimiento_flatpickr" name="fecha_nacimiento" placeholder="Fecha de nacimiento" required>
                        </div>
                        <div class="input-container cedula-group">
                            <select name="nacionalidad" class="cedula-select" required>
                                <option value="V">V</option>
                                <option value="E">E</option>
                                <option value="P">P</option>
                            </select>
                            <input type="text" name="cedula_numero" class="cedula-input" placeholder="Número de documento" required pattern="\d{7,8}" title="Ingresa entre 7 y 8 números" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="input-container">
                            <i class="fa-regular fa-envelope"></i>
                            <input type="email" name="email" placeholder="Correo electrónico" required>
                        </div>
                        <div class="input-container">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" placeholder="Crea una contraseña" required
                                   pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                                   title="Mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.">
                        </div>
                        <button type="submit" name="send" class="btn-submit">
                            Registrarme y solicitar estudio <i class="fa-solid fa-arrow-right"></i>
                        </button>
                        <p class="form-legal">Al registrarte aceptas el tratamiento confidencial de tus datos clínicos.</p>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- ══════════ FOOTER ══════════ -->
<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="#inicio" class="logo">
                    <span class="logo-icon"><i class="fa-solid fa-wave-square"></i></span>
                    <span class="logo-text">EcoMadelleine<small>Centro de Diagnóstico</small></span>
                </a>
                <p>Centro de diagnóstico ecográfico premium dirigido por la Dra. Madelleine Toro. Tecnología, criterio clínico y atención humana en un solo lugar.</p>
            </div>
            <div>
                <h5>Navegación</h5>
                <ul>
                    <li><a href="#inicio">Inicio</a></li>
                    <li><a href="#nosotros">Nosotros</a></li>
                    <li><a href="#proceso">Proceso</a></li>
                    <li><a href="#servicios">Estudios</a></li>
                    <li><a href="#beneficios">Beneficios</a></li>
                </ul>
            </div>
            <div>
                <h5>Acceso</h5>
                <ul>
                    <li><a href="<?= eco_url('login') ?>">Iniciar sesión</a></li>
                    <li><a href="#contacto">Crear cuenta</a></li>
                    <li><a href="#contacto">Agendar estudio</a></li>
                </ul>
            </div>
            <div>
                <h5>Contacto</h5>
                <ul>
                    <li><i class="fa-solid fa-phone"></i> 0412-8517770</li>
                    <li><i class="fa-regular fa-envelope"></i> contacto@ecomadelleine.com</li>
                    <li><i class="fa-regular fa-clock"></i> Lun a Vie · 8:00 a 17:00</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> EcoMadelleine · Centro de Diagnóstico Ecográfico</span>
            <span class="made">Diseñado con criterio clínico · Dra. Madelleine Toro</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>

<script>
document.addEventListener('DOMContentLoaded', () => {

    /* Flatpickr */
    if (window.flatpickr) {
        flatpickr("#fecha_nacimiento_flatpickr", {
            locale: "es",
            dateFormat: "d-m-Y",
            maxDate: "today",
            altInput: true,
            altFormat: "j F, Y",
        });
    }

    /* Header scroll state + hide-on-scroll-down */
    const header = document.querySelector('.header');
    let lastY = window.scrollY;
    const onScroll = () => {
        const y = window.scrollY;
        header.classList.toggle('scrolled', y > 24);
        if (y > 140 && y - lastY > 6)      header.classList.add('hidden');
        else if (lastY - y > 4 || y < 80)  header.classList.remove('hidden');
        lastY = y;

        const h = document.documentElement;
        const total = h.scrollHeight - h.clientHeight;
        const pct = total > 0 ? (y / total) * 100 : 0;
        const bar = document.getElementById('scroll-progress');
        if (bar) bar.style.width = pct + '%';
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    /* Hamburger */
    const ham = document.getElementById('hamburger');
    const navList = document.getElementById('nav-list');
    if (ham && navList) {
        ham.addEventListener('click', () => navList.classList.toggle('open'));
        navList.querySelectorAll('a').forEach(a => a.addEventListener('click', () => navList.classList.remove('open')));
    }

    /* Reveal on scroll */
    const reveals = document.querySelectorAll('.reveal');
    const io = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('in');
                io.unobserve(e.target);
            }
        });
    }, { threshold: 0.12, rootMargin: '0px 0px -50px 0px' });
    reveals.forEach(el => io.observe(el));

    /* Stat counters — solo si hay valor numérico real */
    const counters = document.querySelectorAll('[data-counter]');
    const ioCount = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (!e.isIntersecting) return;
            const el = e.target;
            const target = parseInt(el.getAttribute('data-counter'), 10);
            const suffix = el.getAttribute('data-suffix') || '';
            if (isNaN(target) || target === 0) { ioCount.unobserve(el); return; }
            const duration = 1600;
            const start = performance.now();
            const step = (now) => {
                const t = Math.min((now - start) / duration, 1);
                const eased = 1 - Math.pow(1 - t, 3);
                const val = Math.round(target * eased);
                el.textContent = val.toLocaleString('es-VE') + (t === 1 ? suffix : '');
                if (t < 1) requestAnimationFrame(step);
            };
            requestAnimationFrame(step);
            ioCount.unobserve(el);
        });
    }, { threshold: 0.4 });
    counters.forEach(el => ioCount.observe(el));

    /* Magnetic CTA */
    document.querySelectorAll('.btn-primary, .btn-submit').forEach(btn => {
        btn.addEventListener('mousemove', (e) => {
            const r = btn.getBoundingClientRect();
            const x = e.clientX - r.left - r.width / 2;
            const y = e.clientY - r.top - r.height / 2;
            btn.style.transform = `translate(${x * 0.12}px, ${y * 0.18}px)`;
        });
        btn.addEventListener('mouseleave', () => { btn.style.transform = ''; });
    });

    /* Auto-hide status message */
    const msg = document.getElementById('msg-estado');
    if (msg) {
        setTimeout(() => {
            msg.style.transition = 'opacity .4s, transform .4s';
            msg.style.opacity = '0';
            msg.style.transform = 'translate(-50%, -20px)';
            setTimeout(() => msg.remove(), 500);
        }, 5500);
    }
});
</script>

<!-- Panel de analíticas — Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<script>
(function () {
    if (!window.Chart) return;
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#475569';
    var built = false;

    function build() {
        if (built) return; built = true;

        /* Estudios por mes — barras con degradado azul */
        var em = document.getElementById('anChartMeses');
        if (em) {
            var ctx = em.getContext('2d');
            var g = ctx.createLinearGradient(0, 0, 0, 300);
            g.addColorStop(0, 'rgba(2,177,244,.92)');
            g.addColorStop(1, 'rgba(2,177,244,.28)');
            new Chart(em, {
                type: 'bar',
                data: { labels: <?php echo json_encode($an_meses_lbl); ?>,
                        datasets: [{ data: <?php echo json_encode($an_meses_val); ?>, backgroundColor: g, borderRadius: 10, maxBarThickness: 48 }] },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    animation: { duration: 1100, easing: 'easeOutQuart' },
                    plugins: { legend: { display: false },
                               tooltip: { backgroundColor: '#014a82', padding: 10, cornerRadius: 10, displayColors: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { precision: 0, color: '#94a3b8', font: { weight: '600' } }, grid: { color: 'rgba(148,163,184,.16)' }, border: { display: false } },
                        x: { ticks: { color: '#475569', font: { weight: '700' } }, grid: { display: false }, border: { display: false } }
                    }
                }
            });
        }

        /* Estado de las citas — dona */
        var ec = document.getElementById('anChartCitas');
        if (ec) {
            new Chart(ec, {
                type: 'doughnut',
                data: { labels: <?php echo json_encode($an_citas_lbl); ?>,
                        datasets: [{ data: <?php echo json_encode($an_citas_val); ?>, backgroundColor: <?php echo json_encode($an_citas_col); ?>, borderWidth: 0, hoverOffset: 10 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '62%',
                    animation: { animateRotate: true, duration: 1100 },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 14, color: '#475569', font: { size: 12, weight: '600' } } },
                        tooltip: { backgroundColor: '#014a82', padding: 10, cornerRadius: 10, usePointStyle: true }
                    }
                }
            });
        }
    }

    /* Anima cuando la sección entra en viewport */
    var sec = document.getElementById('analiticas');
    if (sec && 'IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (es) {
            es.forEach(function (e) { if (e.isIntersecting) { build(); io.disconnect(); } });
        }, { threshold: .25 });
        io.observe(sec);
    } else { build(); }
})();
</script>

<!-- Pulido premium: spotlight en tarjetas + CTA magnético -->
<script>
(function () {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
    if (window.matchMedia('(hover: none)').matches) return; // sin efectos en táctil

    document.querySelectorAll('.stat-card, .beneficio').forEach(function (card) {
        card.addEventListener('pointermove', function (e) {
            var r = card.getBoundingClientRect();
            card.style.setProperty('--mx', ((e.clientX - r.left) / r.width * 100).toFixed(1) + '%');
            card.style.setProperty('--my', ((e.clientY - r.top) / r.height * 100).toFixed(1) + '%');
        });
    });

    var mag = document.querySelector('.hero-ctas .btn-primary');
    if (mag) {
        mag.addEventListener('pointermove', function (e) {
            var r = mag.getBoundingClientRect();
            var x = (e.clientX - r.left - r.width / 2) * 0.16;
            var y = (e.clientY - r.top - r.height / 2) * 0.26;
            mag.style.transform = 'translate(' + x.toFixed(2) + 'px,' + (y - 2).toFixed(2) + 'px)';
        });
        mag.addEventListener('pointerleave', function () { mag.style.transform = ''; });
    }
})();
</script>
</body>
</html>
