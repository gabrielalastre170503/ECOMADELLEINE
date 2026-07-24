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

    /* URL canónica absoluta de la home, para SEO (canonical, Open Graph, JSON-LD) */
    $eco_scheme    = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off') ? 'https' : 'http';
    $eco_canonical = $eco_scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . eco_url('');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="EcoMadelleine · Centro de diagnóstico ecográfico premium. Dra. Madelleine Toro. Informes digitales en 24 horas.">
    <meta name="theme-color" content="#eaf3ff">
    <meta name="robots" content="index, follow">
    <link rel="canonical" href="<?= htmlspecialchars($eco_canonical, ENT_QUOTES) ?>">
    <title>EcoMadelleine · Diagnóstico Ecográfico Premium</title>

    <!-- Open Graph / Twitter Card -->
    <meta property="og:type" content="website">
    <meta property="og:locale" content="es_VE">
    <meta property="og:site_name" content="EcoMadelleine">
    <meta property="og:title" content="EcoMadelleine · Diagnóstico Ecográfico Premium">
    <meta property="og:description" content="Centro de diagnóstico ecográfico premium. Dra. Madelleine Toro. Informes digitales en 24 horas.">
    <meta property="og:url" content="<?= htmlspecialchars($eco_canonical, ENT_QUOTES) ?>">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="EcoMadelleine · Diagnóstico Ecográfico Premium">
    <meta name="twitter:description" content="Centro de diagnóstico ecográfico premium. Dra. Madelleine Toro. Informes digitales en 24 horas.">

    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 24 24%22><rect width=%2224%22 height=%2224%22 rx=%226%22 fill=%22%23014a82%22/><path d=%22M4 13c1.5 0 1.5-4 3-4s1.5 8 3 8 1.5-12 3-12 1.5 8 3 8 1.5-4 3-4%22 fill=%22none%22 stroke=%22%23ffffff%22 stroke-width=%221.6%22 stroke-linecap=%22round%22 stroke-linejoin=%22round%22/></svg>">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

    <link rel="stylesheet" href="assets/css/landing/landing.css?v=<?= @filemtime(__DIR__ . '/assets/css/landing/landing.css') ?: '1' ?>">

    <script type="application/ld+json">
    <?= json_encode([
        '@context'  => 'https://schema.org',
        '@type'     => 'MedicalBusiness',
        'name'      => 'EcoMadelleine',
        'description' => 'Centro de diagnóstico ecográfico premium dirigido por la Dra. Madelleine Toro.',
        'url'       => $eco_canonical,
        'telephone' => '+58-412-8517770',
        'email'     => 'madelleine.toro8@gmail.com',
        'openingHoursSpecification' => [
            '@type'    => 'OpeningHoursSpecification',
            'dayOfWeek' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'opens'    => '08:00',
            'closes'   => '17:00',
        ],
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    </script>
</head>
<body>

<a href="#main-content" class="skip-link">Saltar al contenido principal</a>

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

<main id="main-content">

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
            <ul class="nosotros-highlights">
                <li><i class="fa-solid fa-circle-check"></i> Estudios realizados personalmente por la doctora</li>
                <li><i class="fa-solid fa-circle-check"></i> Informes digitales detallados en 24 horas</li>
                <li><i class="fa-solid fa-circle-check"></i> Firma electrónica verificable en cada informe</li>
                <li><i class="fa-solid fa-circle-check"></i> Datos clínicos tratados con total confidencialidad</li>
            </ul>
            <div class="nosotros-signature">
                <span class="ns-line"></span>
                <span>Dra. Madelleine Toro</span>
            </div>
            <a href="#contacto" class="btn btn-primary nosotros-cta">Agendar estudio <i class="fa-solid fa-arrow-right"></i></a>
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
                        <div class="val">madelleine.toro8@gmail.com</div>
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
                            <input type="text" name="name" placeholder="Nombre y Apellido" aria-label="Nombre y Apellido" required>
                        </div>
                        <div class="input-container">
                            <i class="fa-solid fa-calendar-day"></i>
                            <input type="text" id="fecha_nacimiento_flatpickr" name="fecha_nacimiento" placeholder="Fecha de nacimiento" aria-label="Fecha de nacimiento" required>
                        </div>
                        <div class="input-container cedula-group">
                            <select name="nacionalidad" class="cedula-select" aria-label="Nacionalidad del documento" required>
                                <option value="V">V</option>
                                <option value="E">E</option>
                                <option value="P">P</option>
                            </select>
                            <input type="text" name="cedula_numero" class="cedula-input" placeholder="Número de documento" aria-label="Número de documento" required pattern="\d{7,8}" title="Ingresa entre 7 y 8 números" oninput="this.value = this.value.replace(/[^0-9]/g, '')">
                        </div>
                        <div class="input-container">
                            <i class="fa-solid fa-phone"></i>
                            <input type="tel" name="telefono" placeholder="Teléfono" aria-label="Teléfono" required pattern="[0-9()+\-\s]{7,20}" title="Número de teléfono (7 a 20 caracteres)">
                        </div>
                        <div class="input-container">
                            <i class="fa-solid fa-location-dot"></i>
                            <input type="text" name="direccion" placeholder="Dirección" aria-label="Dirección" required maxlength="255">
                        </div>
                        <div class="input-container">
                            <i class="fa-regular fa-envelope"></i>
                            <input type="email" name="email" placeholder="Correo electrónico" aria-label="Correo electrónico" required>
                        </div>
                        <div class="input-container">
                            <i class="fa-solid fa-lock"></i>
                            <input type="password" name="password" placeholder="Crea una contraseña" aria-label="Crea una contraseña" required
                                   pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[\W_]).{8,}"
                                   title="Mínimo 8 caracteres, una mayúscula, una minúscula, un número y un símbolo.">
                        </div>
                        <button type="submit" name="send" class="btn-submit">
                            Registrarme y solicitar estudio <i class="fa-solid fa-arrow-right"></i>
                        </button>
                        <label class="form-check">
                            <input type="checkbox" name="acepto_privacidad" value="1" required>
                            <span>He leído y acepto el <a href="<?= eco_url('privacidad') ?>" target="_blank" rel="noopener">aviso de privacidad</a> y los <a href="<?= eco_url('terminos') ?>" target="_blank" rel="noopener">términos y condiciones</a>.</span>
                        </label>
                    </div>
                </form>
            </div>
        </div>
    </div>
</section>

</main>

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
                    <li><i class="fa-regular fa-envelope"></i> madelleine.toro8@gmail.com</li>
                    <li><i class="fa-regular fa-clock"></i> Lun a Vie · 8:00 a 17:00</li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?php echo date('Y'); ?> EcoMadelleine · Centro de Diagnóstico Ecográfico</span>
            <span class="footer-legal-links">
                <a href="<?= eco_url('privacidad') ?>">Aviso de privacidad</a>
                <a href="<?= eco_url('terminos') ?>">Términos y condiciones</a>
            </span>
            <span class="made">Diseñado con criterio clínico · Dra. Madelleine Toro</span>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://npmcdn.com/flatpickr/dist/l10n/es.js"></script>
<script src="assets/js/landing/landing.js?v=<?= @filemtime(__DIR__ . '/assets/js/landing/landing.js') ?: '1' ?>" defer></script>

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

        /* Estado de las citas: dona moderna con total al centro */
        var ec = document.getElementById('anChartCitas');
        if (ec) {
            var centerTotal = {
                id: 'centerTotal',
                afterDraw: function (chart) {
                    var m = chart.getDatasetMeta(0);
                    if (!m.data.length) return;
                    var c = chart.ctx, x = m.data[0].x, y = m.data[0].y;
                    c.save();
                    c.textAlign = 'center'; c.textBaseline = 'middle';
                    c.fillStyle = '#003a66';
                    c.font = '800 34px Inter, system-ui, sans-serif';
                    c.fillText('<?php echo $an_total_citas; ?>', x, y - 9);
                    c.fillStyle = '#94a3b8';
                    c.font = '600 12px Inter, system-ui, sans-serif';
                    c.fillText('citas totales', x, y + 18);
                    c.restore();
                }
            };
            new Chart(ec, {
                type: 'doughnut',
                data: { labels: <?php echo json_encode($an_citas_lbl); ?>,
                        datasets: [{ data: <?php echo json_encode($an_citas_val); ?>, backgroundColor: ['#02b1f4', '#014a82', '#38bdf8', '#0369a1', '#7dd3fc', '#075985'], borderColor: '#ffffff', borderWidth: 3, borderRadius: 8, hoverOffset: 12 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, cutout: '72%',
                    layout: { padding: 6 },
                    animation: { animateRotate: true, animateScale: true, duration: 1200, easing: 'easeOutQuart' },
                    plugins: {
                        legend: { position: 'bottom', labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, boxHeight: 8, padding: 16, color: '#475569', font: { size: 12.5, weight: '600' } } },
                        tooltip: { backgroundColor: '#014a82', padding: 11, cornerRadius: 12, usePointStyle: true, boxPadding: 6 }
                    }
                },
                plugins: [centerTotal]
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

</body>
</html>
