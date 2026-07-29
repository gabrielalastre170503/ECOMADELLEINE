<?php
session_start();
include __DIR__ . '/../core/conexion.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: ' . eco_url('login'));
    exit;
}
if ($_SESSION['rol'] !== 'paciente') {
    header('Location: ' . eco_url('dashboard'));
    exit;
}

$usuario_id = (int)$_SESSION['usuario_id'];
$nombre_paciente = $_SESSION['nombre_completo'] ?? 'Paciente';

/* Correo del paciente para indicar dónde recibirá respuesta */
$correo_paciente = '';
if ($stmt = $conex->prepare('SELECT correo FROM usuarios WHERE id = ?')) {
    $stmt->bind_param('i', $usuario_id);
    $stmt->execute();
    if ($row = $stmt->get_result()->fetch_assoc()) {
        $correo_paciente = (string)$row['correo'];
    }
    $stmt->close();
}

$msg_ok = $msg_err = '';
if (isset($_GET['status']) && $_GET['status'] === 'mensaje_enviado') {
    $msg_ok = 'Tu mensaje fue recibido. El equipo te responderá a la brevedad.';
}
if (isset($_GET['error'])) {
    $msg_err = match ($_GET['error']) {
        'campos_vacios' => 'Completa el asunto y el mensaje antes de enviar.',
        'config_correo' => 'El envío de correo aún no está configurado. Avisa al administrador o usa el contacto directo.',
        'envio_fallido' => 'No se pudo enviar el mensaje en este momento. Intenta de nuevo o usa el contacto directo.',
        default => 'No se pudo enviar el mensaje.',
    };
}

/* Accesos de autoayuda */
$autoayuda = [
    ['paciente_faq.php',                  'fa-solid fa-robot',          'Asistente y FAQ',       'Resuelve dudas al instante'],
    ['preparacion_estudios_paciente.php', 'fa-solid fa-clipboard-list', 'Preparación',           'Cómo prepararte para tu estudio'],
    ['mis_citas_paciente.php',            'fa-solid fa-calendar-check', 'Mis Citas',             'Estado y detalles de tus citas'],
    ['mis_informes_paciente.php',         'fa-solid fa-file-medical',   'Mis Informes',          'Consulta tus resultados'],
    ['perfil.php',                        'fa-solid fa-user-gear',      'Mi Perfil',             'Actualiza tus datos y acceso'],
];

$page_title    = 'Centro de ayuda';
$page_subtitle = 'Estamos para ayudarte con cualquier duda';
$active_section = 'ayuda';

ob_start();
?>

<style>
.ah-grid-self { display:grid; grid-template-columns:repeat(auto-fill,minmax(215px,1fr)); gap:14px; margin-bottom:20px; }
.ah-self { display:flex; align-items:center; gap:12px; padding:16px; text-decoration:none; background:var(--bg-surface); border:1px solid var(--border); border-radius:var(--radius-lg); transition:box-shadow .2s ease, transform .2s ease, border-color .2s ease; }
.ah-self:hover { box-shadow:var(--shadow); transform:translateY(-3px); border-color:rgba(2,177,244,.3); }
.ah-self__icon { width:42px; height:42px; border-radius:11px; flex-shrink:0; display:flex; align-items:center; justify-content:center; font-size:17px; color:#fff; background:linear-gradient(135deg,var(--accent),#38bdf8); }
.ah-self__t { min-width:0; }
.ah-self__t strong { display:block; font-size:13.5px; font-weight:700; color:var(--text-primary); }
.ah-self__t span { font-size:11.5px; color:var(--text-muted); }

.ah-layout { display:grid; grid-template-columns:1fr; gap:18px; align-items:start; }
@media (min-width:900px){ .ah-layout { grid-template-columns:minmax(0,1fr) 340px; } }

.ah-card-title { margin:0 0 16px; font-size:15px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:9px; }
.ah-card-title i { color:var(--accent); }

.ah-field { margin-bottom:15px; }
.ah-field:last-of-type { margin-bottom:0; }
.ah-field > label { display:block; font-size:12.5px; font-weight:600; color:var(--text-secondary); margin-bottom:7px; }
.ah-input, .ah-textarea { width:100%; padding:11px 13px; border:1.5px solid var(--border); border-radius:10px; font-family:inherit; font-size:13.5px; background:var(--bg-surface); color:var(--text-primary); box-sizing:border-box; transition:border-color .18s ease, box-shadow .18s ease; }
.ah-input:focus, .ah-textarea:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px rgba(2,177,244,.12); }
.ah-chips { display:flex; gap:7px; flex-wrap:wrap; margin-bottom:8px; }
.ah-chip { padding:6px 12px; border-radius:999px; font-size:12px; font-weight:600; cursor:pointer; background:var(--bg-surface); color:var(--text-secondary); border:1px solid var(--border); transition:all .18s ease; }
.ah-chip:hover { border-color:var(--accent); color:var(--accent-text); background:var(--accent-soft); }
.ah-counter { font-size:11.5px; color:var(--text-muted); text-align:right; margin-top:6px; }
.ah-sender { font-size:12px; color:var(--text-secondary); display:flex; align-items:flex-start; gap:8px; margin:16px 0 0; padding:12px 14px; border-radius:10px; background:var(--bg-muted); }
.ah-sender i { color:var(--accent); margin-top:2px; }
.ah-sender strong { color:var(--text-primary); }

.ah-contact__row { display:flex; align-items:center; gap:13px; padding:12px 0; border-bottom:1px dashed var(--border); }
.ah-contact__row:last-of-type { border-bottom:none; }
.ah-contact__icon { width:36px; height:36px; border-radius:10px; flex-shrink:0; background:var(--accent-soft); color:var(--accent-text); display:flex; align-items:center; justify-content:center; font-size:14px; }
.ah-contact__t { min-width:0; }
.ah-contact__label { font-size:10.5px; text-transform:uppercase; letter-spacing:.4px; color:var(--text-muted); font-weight:600; }
.ah-contact__t a, .ah-contact__t span { font-size:13.5px; font-weight:600; color:var(--text-primary); text-decoration:none; word-break:break-word; }
.ah-contact__t a:hover { color:var(--accent-text); }
.ah-urgency { margin-top:16px; padding:14px 16px; border-radius:12px; border:1px solid rgba(245,158,11,.4); background:rgba(245,158,11,.1); }
.ah-urgency strong { font-size:13px; color:#b45309; display:flex; align-items:center; gap:7px; }
.ah-urgency p { margin:8px 0 0; font-size:12.5px; color:var(--text-secondary); line-height:1.5; }
</style>

<?php if ($msg_ok): ?>
    <div class="card" style="border-left:4px solid var(--success);background:rgba(34,197,94,.06);margin-bottom:18px;">
        <strong style="color:#15803d;"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($msg_ok) ?></strong>
    </div>
<?php endif; ?>
<?php if ($msg_err): ?>
    <div class="card" style="border-left:4px solid var(--danger);background:rgba(239,68,68,.06);margin-bottom:18px;">
        <strong style="color:#b91c1c;"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($msg_err) ?></strong>
    </div>
<?php endif; ?>

<p style="font-size:12.5px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-secondary);margin:0 0 12px;">
    <i class="fa-solid fa-bolt" style="color:var(--accent);"></i> Antes de escribirnos, quizás esto te ayude
</p>
<div class="ah-grid-self">
    <?php foreach ($autoayuda as [$href, $icon, $titulo, $sub]): ?>
        <a href="<?= htmlspecialchars($href) ?>" class="ah-self">
            <span class="ah-self__icon"><i class="<?= htmlspecialchars($icon) ?>"></i></span>
            <span class="ah-self__t"><strong><?= htmlspecialchars($titulo) ?></strong><span><?= htmlspecialchars($sub) ?></span></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="ah-layout">

    <!-- Formulario -->
    <div class="card">
        <h3 class="ah-card-title"><i class="fa-solid fa-paper-plane"></i> Enviar un mensaje</h3>
        <form action="<?= eco_url('api/enviar_ayuda.php') ?>" method="post" id="ah-form">
            <div class="ah-field">
                <label for="asunto_ayuda">Asunto</label>
                <div class="ah-chips" id="ah-chips">
                    <button type="button" class="ah-chip">Duda sobre mi cita</button>
                    <button type="button" class="ah-chip">Preparación del estudio</button>
                    <button type="button" class="ah-chip">Acceso a mi informe</button>
                    <button type="button" class="ah-chip">Problema con mi cuenta</button>
                </div>
                <input type="text" name="asunto" id="asunto_ayuda" class="ah-input" required maxlength="120"
                       placeholder="Ej.: duda sobre la preparación del estudio">
            </div>
            <div class="ah-field">
                <label for="mensaje_ayuda">Mensaje</label>
                <textarea name="mensaje" id="mensaje_ayuda" class="ah-textarea" rows="7" required maxlength="1000"
                          placeholder="Cuéntanos tu consulta con el mayor detalle posible…"></textarea>
                <div class="ah-counter"><span id="ah-count">0</span> / 1000</div>
            </div>
            <button type="submit" class="btn-primary"><i class="fa-solid fa-paper-plane"></i> Enviar mensaje</button>

            <p class="ah-sender">
                <i class="fa-solid fa-circle-info"></i>
                <span>Enviando como <strong><?= htmlspecialchars($nombre_paciente) ?></strong><?php if ($correo_paciente !== ''): ?>. Te responderemos a <strong><?= htmlspecialchars($correo_paciente) ?></strong><?php endif; ?>.</span>
            </p>
        </form>
    </div>

    <!-- Contacto -->
    <aside>
        <div class="card">
            <h3 class="ah-card-title"><i class="fa-solid fa-address-book"></i> Contacto directo</h3>

            <div class="ah-contact__row">
                <span class="ah-contact__icon"><i class="fa-solid fa-phone"></i></span>
                <div class="ah-contact__t">
                    <div class="ah-contact__label">Teléfono</div>
                    <a href="tel:+584128517770">0412 851 7770</a>
                </div>
            </div>
            <div class="ah-contact__row">
                <span class="ah-contact__icon"><i class="fa-solid fa-envelope"></i></span>
                <div class="ah-contact__t">
                    <div class="ah-contact__label">Correo</div>
                    <a href="mailto:madelleine.toro8@gmail.com">madelleine.toro8@gmail.com</a>
                </div>
            </div>
            <div class="ah-contact__row">
                <span class="ah-contact__icon"><i class="fa-solid fa-location-dot"></i></span>
                <div class="ah-contact__t">
                    <div class="ah-contact__label">Dirección</div>
                    <span>San Felipe, Edo. Yaracuy</span>
                </div>
            </div>
            <div class="ah-contact__row">
                <span class="ah-contact__icon"><i class="fa-solid fa-clock"></i></span>
                <div class="ah-contact__t">
                    <div class="ah-contact__label">Horario de atención</div>
                    <span>Lun a Vie · 8:00 a.m. – 5:00 p.m.</span>
                </div>
            </div>

            <div class="ah-urgency">
                <strong><i class="fa-solid fa-triangle-exclamation"></i> Urgencia médica</strong>
                <p>Si tienes una emergencia clínica, acude a un servicio de urgencias o contacta a tu médico tratante. Este formulario no reemplaza la atención de emergencia.</p>
            </div>
        </div>
    </aside>

</div>

<?php
$page_content = ob_get_clean();

$page_scripts_extra = <<<'HTML'
<script>
(function () {
    var asunto = document.getElementById('asunto_ayuda');
    document.querySelectorAll('#ah-chips .ah-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            if (asunto) { asunto.value = chip.textContent.trim(); asunto.focus(); }
        });
    });

    var msg = document.getElementById('mensaje_ayuda');
    var count = document.getElementById('ah-count');
    if (msg && count) {
        var update = function () { count.textContent = msg.value.length; };
        msg.addEventListener('input', update);
        update();
    }
})();
</script>
HTML;

include __DIR__ . '/../layouts/shell.php';
