<?php
/**
 * Vista universal de Mi Perfil (diseño original del panel).
 * Variables requeridas: $nombre_usuario, $rol_usuario, $correoUsuario,
 * $telefonoUsuario, $fechaRegistroTexto, $ultimaActividadTexto, $avatarInicial
 */
$nombreUsuarioPlano = (string)($nombre_usuario ?? '');
if (!isset($avatarInicial) || $avatarInicial === '') {
    $avatarInicial = $nombreUsuarioPlano !== '' ? strtoupper(substr($nombreUsuarioPlano, 0, 1)) : '?';
}
?>
<div class="perfil-page-wrap">

<?php if (!empty($_GET['status']) && $_GET['status'] === 'perfil_actualizado'): ?>
    <div class="alert-box success">
        <span><strong>¡Éxito!</strong> Tu contraseña ha sido actualizada.</span>
    </div>
<?php elseif (!empty($_GET['status']) && $_GET['status'] === '2fa_activado'): ?>
    <div class="alert-box success">
        <span><strong>2FA activado.</strong> A partir de ahora te pediremos un código al iniciar sesión.</span>
    </div>
<?php elseif (!empty($_GET['status']) && $_GET['status'] === '2fa_desactivado'): ?>
    <div class="alert-box success">
        <span><strong>2FA desactivado.</strong> Ya no se te pedirá el código de verificación.</span>
    </div>
<?php elseif (!empty($_GET['error'])): ?>
    <?php
    $error_msg = 'Ocurrió un error. Inténtalo de nuevo.';
    if ($_GET['error'] === 'pass_no_coincide') {
        $error_msg = 'La nueva contraseña y su confirmación no coinciden.';
    } elseif ($_GET['error'] === 'pass_no_segura') {
        $error_msg = 'La nueva contraseña no cumple con los requisitos de seguridad.';
    } elseif ($_GET['error'] === 'actualizacion_fallida') {
        $error_msg = 'No se pudo actualizar la contraseña. Inténtalo nuevamente.';
    }
    ?>
    <div class="alert-box error">
        <span><strong>Error:</strong> <?= htmlspecialchars($error_msg) ?></span>
    </div>
<?php endif; ?>

<div class="perfil-hero">
    <div class="perfil-hero-start">
        <div class="perfil-hero-icon">
            <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
        </div>
        <div class="perfil-hero-texto">
            <h2>Hola, <?= htmlspecialchars($nombre_usuario) ?></h2>
            <p>Gestiona tu información personal y la seguridad de tu cuenta.</p>
        </div>
    </div>
    <div class="perfil-hero-estado">
        <span class="perfil-estado-badge"><i class="fa-solid fa-circle-check"></i> Perfil activo</span>
        <span class="perfil-hero-meta">Rol: <?= htmlspecialchars(ucfirst($rol_usuario)) ?></span>
        <span class="perfil-hero-meta">Miembro desde: <?= htmlspecialchars($fechaRegistroTexto) ?></span>
    </div>
</div>

<div class="perfil-detalle">
    <div class="perfil-summary">
        <div class="perfil-avatar">
            <span><?= htmlspecialchars($avatarInicial) ?></span>
        </div>
        <div class="perfil-summary-text">
            <h3><?= htmlspecialchars($nombre_usuario) ?></h3>
            <p><?= htmlspecialchars($correoUsuario) ?></p>
        </div>
        <div class="perfil-summary-meta">
            <div>
                <span class="meta-label">Rol</span>
                <span class="meta-value"><?= htmlspecialchars(ucfirst($rol_usuario)) ?></span>
            </div>
            <div>
                <span class="meta-label">Miembro desde</span>
                <span class="meta-value"><?= htmlspecialchars($fechaRegistroTexto) ?></span>
            </div>
            <div>
                <span class="meta-label">Estado</span>
                <span class="meta-badge activo"><i class="fa-solid fa-circle-check"></i> Activo</span>
            </div>
        </div>
    </div>

    <div class="profile-grid">
        <div class="profile-card">
            <h4><i class="fa-solid fa-address-card"></i> Información de contacto</h4>
            <div class="form-group">
                <label><i class="fa-solid fa-user"></i> Nombre completo</label>
                <input type="text" value="<?= htmlspecialchars($nombre_usuario) ?>" readonly>
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-envelope"></i> Correo electrónico</label>
                <input type="email" value="<?= htmlspecialchars($correoUsuario) ?>" readonly>
            </div>
            <div class="form-group">
                <label><i class="fa-solid fa-phone"></i> Teléfono registrado</label>
                <input type="text" value="<?= htmlspecialchars($telefonoUsuario ?? 'No registrado') ?>" readonly>
            </div>
        </div>

        <div class="profile-card">
            <h4><i class="fa-solid fa-lock"></i> Seguridad de la cuenta</h4>
            <ul class="perfil-checklist">
                <li><i class="fa-solid fa-check-circle"></i> Evita compartir tus datos.</li>
            </ul>
            <form action="<?= eco_url('api/actualizar_perfil.php') ?>" method="POST" class="perfil-form">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="cambiar_contrasena">
                <div class="form-group">
                    <label for="nueva_contrasena_perfil"><i class="fa-solid fa-shield-halved"></i> Nueva contraseña</label>
                    <input type="password" name="nueva_contrasena" id="nueva_contrasena_perfil" required minlength="8"
                           pattern="(?=.*[A-Z])(?=.*[\W_]).{8,}"
                           oninvalid="this.setCustomValidity('Requiere mínimo 8 caracteres, una mayúscula y un símbolo.')"
                           oninput="this.setCustomValidity('')">
                </div>
                <div class="form-group">
                    <label for="confirmar_contrasena_perfil"><i class="fa-solid fa-shield-heart"></i> Confirmar nueva contraseña</label>
                    <input type="password" name="confirmar_nueva_contrasena" id="confirmar_contrasena_perfil" required>
                </div>
                <button type="submit" class="btn-primary perfil-btn-submit">Actualizar contraseña</button>
            </form>
        </div>

        <?php if (in_array($rol_usuario, ['administrador', 'ecografista', 'recepcionista', 'paciente'], true)): ?>
        <div class="profile-card">
            <h4><i class="fa-solid fa-shield-halved"></i> Verificación en dos pasos (2FA)</h4>
            <?php if (!empty($dosFactorActivo)): ?>
                <p style="font-size:13px;color:var(--text-secondary,#64748b);line-height:1.55;margin:0 0 12px;">
                    <span style="display:inline-flex;align-items:center;gap:6px;color:#15803d;font-weight:600;">
                        <i class="fa-solid fa-circle-check"></i> Activada
                    </span><br>
                    Al iniciar sesión te pediremos un código de 6 dígitos enviado a tu correo.
                </p>
                <ul class="perfil-checklist">
                    <li><i class="fa-solid fa-envelope-open-text"></i> El código llega a tu correo registrado.</li>
                    <li><i class="fa-solid fa-clock"></i> Caduca a los pocos minutos por seguridad.</li>
                    <li><i class="fa-solid fa-toggle-off"></i> Puedes desactivarla cuando quieras desde aquí.</li>
                </ul>
            <?php else: ?>
                <p style="font-size:13px;color:var(--text-secondary,#64748b);line-height:1.55;margin:0 0 12px;">
                    Añade una capa extra de seguridad: un código de un solo uso enviado a tu correo en cada inicio de sesión.
                </p>
                <ul class="perfil-checklist" style="margin-top:10px;">
                    <li><i class="fa-solid fa-envelope" style="color:var(--accent-text);"></i> Recibirás un código de 6 dígitos en tu correo.</li>
                </ul>
            <?php endif; ?>
            <form action="<?= eco_url('api/actualizar_perfil.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="accion" value="toggle_2fa">
                <input type="hidden" name="activar" value="<?= !empty($dosFactorActivo) ? '0' : '1' ?>">
                <button type="submit" class="btn-primary perfil-btn-submit"
                        style="<?= !empty($dosFactorActivo) ? 'background:#ef4444;border-color:#ef4444;' : '' ?>">
                    <i class="fa-solid <?= !empty($dosFactorActivo) ? 'fa-toggle-off' : 'fa-toggle-on' ?>"></i>
                    <?= !empty($dosFactorActivo) ? 'Desactivar 2FA' : 'Activar 2FA' ?>
                </button>
            </form>
        </div>
        <?php endif; ?>

        <div class="profile-card perfil-actividad">
            <h4><i class="fa-solid fa-timeline"></i> Actividad reciente</h4>
            <ul>
                <li><i class="fa-solid fa-clock-rotate-left"></i> Último acceso: <?= htmlspecialchars($ultimaActividadTexto) ?></li>
                <li><i class="fa-solid fa-calendar-check"></i> En el sistema desde: <?= htmlspecialchars($fechaRegistroTexto) ?><?php if (!empty($antiguedadTexto)): ?> <span style="color:var(--text-muted);">(<?= htmlspecialchars($antiguedadTexto) ?>)</span><?php endif; ?>.</li>
                <li><i class="fa-solid fa-user-tag"></i> Tipo de cuenta: <?= htmlspecialchars(ucfirst($rol_usuario)) ?>.</li>
                <li><i class="fa-solid fa-circle-check" style="color:#15803d;"></i> Estado de la cuenta: <?= htmlspecialchars($estadoCuentaTexto ?? 'Activa') ?>.</li>
                <?php if (!empty($correoVerificado)): ?>
                    <li><i class="fa-solid fa-envelope-circle-check" style="color:#15803d;"></i> Correo verificado.</li>
                <?php else: ?>
                    <li><i class="fa-solid fa-envelope" style="color:#d97706;"></i> Correo sin verificar — <a href="<?= eco_url('publico/reenviar_verificacion.php') ?>" style="color:var(--accent,#02b1f4);font-weight:600;">reenviar enlace</a>.</li>
                <?php endif; ?>
                <li><i class="fa-solid fa-shield<?= !empty($dosFactorActivo) ? '-halved' : '' ?>"></i> 2FA: <?= !empty($dosFactorActivo) ? 'activada' : 'desactivada' ?>.</li>
            </ul>
            <?php if ($rol_usuario === 'paciente'): ?>
            <a href="<?= eco_url('api/descargar_historial.php') ?>" class="perfil-action-link"><i class="fa-solid fa-download"></i> Descargar historial</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="perfil-consejos">
        <div>
            <h4><i class="fa-solid fa-lightbulb"></i> Buenas prácticas</h4>
            <p>Actualiza tu contraseña periódicamente y evita reutilizarla en otros sistemas.</p>
        </div>
        <div>
            <h4><i class="fa-solid fa-headset"></i> Soporte</h4>
            <p>¿Necesitas ayuda? Escríbenos a <a href="mailto:soporte@ecomadelleine.com">soporte@ecomadelleine.com</a>.</p>
        </div>
    </div>
</div>

</div>
