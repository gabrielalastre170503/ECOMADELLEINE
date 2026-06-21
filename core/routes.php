<?php
/**
 * routes.php — Tabla de rutas limpias → handlers existentes (alias aditivo).
 *
 * Cada ruta incluye el .php existente; ese handler corre sus propios chequeos
 * de sesión/rol y su lógica, sin cambios. Las URLs `.php?query` viejas siguen
 * funcionando en paralelo (fallback). Migración de referencias = gradual.
 *
 * @var EcoRouter $r  (definido en router.php)
 */

/* ── Públicas / identidad ── */
$r->any('/login',         'auth/login.php');
$r->any('/registro',      'auth/registro.php');
$r->any('/recuperar',     'auth/recuperar.php');
$r->any('/verificar-2fa', 'auth/verificar_2fa.php');
$r->get('/privacidad',    'auth/privacidad.php');
$r->get('/logout',        'auth/logout.php');

/* ── Comunes ── */
$r->get('/dashboard', 'common/dashboard_v2.php');
$r->get('/perfil',    'common/perfil.php');
$r->get('/reportes',  'common/reportes.php');
$r->get('/agenda',    'common/agenda_general.php');
$r->get('/facturacion', 'common/facturacion.php');
$r->any('/consentimiento', 'common/consentimiento.php');

/* ── Informes ── */
$r->get('/informe/{informe_id}', 'informes/ver_informe_estudio.php');
$r->get('/informes-estudio',     'informes/ver_informes_estudios.php');
$r->get('/nuevo-informe',        'informes/nuevo_informe_estudio.php');
$r->get('/gestionar-paciente',   'informes/gestionar_paciente.php');
$r->get('/mis-informes',         'paciente/mis_informes_paciente.php');

/* ── Administrador ── */
$r->get('/personal',      'admin/admin_personal.php');
$r->get('/usuarios',      'common/ver_usuarios.php');
$r->any('/especialidades', 'admin/admin_especialidades.php');
$r->any('/repositorio',   'admin/admin_documentos.php');
$r->get('/contenido',     'admin/admin_contenido.php');
$r->get('/auditoria',     'common/auditoria.php');
$r->get('/notas-rapidas', 'common/notas_rapidas.php');
$r->get('/citas-admin',    'admin/ver_citas_admin.php');
$r->get('/gestionar-faq',   'admin/gestionar_faq.php');
$r->get('/gestionar-textos', 'admin/gestionar_textos.php');
$r->get('/gestionar-estudios', 'admin/gestionar_estudios_ecograficos.php');

/* ── Ecografista ── */
$r->get('/mis-pacientes',  'ecografista/mis_pacientes.php');
$r->get('/proximas-citas', 'ecografista/mis_proximas_citas.php');
$r->get('/solicitudes',    'ecografista/mis_solicitudes.php');
$r->get('/mi-agenda',      'ecografista/mi_agenda.php');
$r->get('/disponibilidad', 'ecografista/gestionar_disponibilidad.php');
$r->get('/historial-citas', 'ecografista/mi_historial_citas.php');
$r->get('/notas-sesion',   'ecografista/mis_notas_sesion.php');

/* ── Recepcionista ── */
$r->get('/citas-pendientes',    'recepcion/recepcion_citas_pendientes.php');
$r->get('/gestion-pacientes',   'recepcion/recepcion_gestion_pacientes.php');
$r->get('/historial-recepcion', 'recepcion/recepcion_historial_citas.php');
$r->get('/directorio',          'recepcion/recepcion_directorio.php');
$r->get('/ficha-paciente',      'recepcion/recepcion_ficha_paciente.php');

/* ── Paciente ── */
$r->get('/mis-citas',       'paciente/mis_citas_paciente.php');
$r->get('/solicitar-cita',  'paciente/solicitar_cita_paciente.php');
$r->get('/ecografistas',    'paciente/ecografistas_paciente.php');
$r->get('/preparacion',     'paciente/preparacion_estudios_paciente.php');
$r->get('/precios',         'paciente/precios_ecografias_paciente.php');
$r->get('/faq',             'paciente/paciente_faq.php');
$r->get('/ayuda',           'paciente/paciente_ayuda.php');

/* Términos y condiciones (público) */
$r->get('/terminos',      'auth/terminos.php');
