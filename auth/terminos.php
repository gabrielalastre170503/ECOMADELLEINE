<?php
/**
 * terminos.php — Terminos y condiciones de uso (publico).
 * Enlazado desde el registro y el pie de pagina. No requiere sesion.
 *
 * El texto es una BASE: el centro debe revisarlo y ajustarlo legalmente antes
 * de publicarlo en produccion.
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminos y condiciones · EcoMadelleine</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --azul:#02b1f4; --azul-dark:#014a82; --ink:#0c1a2e; --gris:#4a5870; }
        * { box-sizing:border-box; margin:0; padding:0; }
        body { font-family:'Inter',system-ui,sans-serif; background:linear-gradient(180deg,#eaf3ff 0%,#f5f9ff 100%);
               color:var(--ink); min-height:100vh; padding:32px 18px; }
        .wrap { max-width:820px; margin:0 auto; }
        .back { display:inline-flex; align-items:center; gap:8px; color:var(--azul-dark); font-weight:600;
                text-decoration:none; font-size:14px; margin-bottom:18px; }
        .back:hover { color:var(--azul); }
        .card { background:#fff; border:1px solid rgba(2,177,244,.16); border-radius:22px; padding:38px 42px;
                box-shadow:0 30px 80px rgba(12,26,46,.10); }
        .head { display:flex; align-items:center; gap:16px; margin-bottom:8px; }
        .ic { width:58px; height:58px; border-radius:16px; background:rgba(2,177,244,.12); color:var(--azul-dark);
              display:flex; align-items:center; justify-content:center; font-size:25px; flex-shrink:0; }
        h1 { font-size:24px; font-weight:800; letter-spacing:-.02em; }
        .sub { color:var(--gris); font-size:13.5px; margin-top:2px; }
        h2 { font-size:16px; color:var(--azul-dark); margin:26px 0 8px; }
        p, li { font-size:14px; line-height:1.65; color:#243043; }
        ul { margin:6px 0 6px 20px; }
        a { color:var(--azul-dark); font-weight:600; }
        .note { margin-top:24px; padding:12px 16px; border-radius:12px; background:rgba(2,177,244,.07);
                border:1px solid rgba(2,177,244,.2); font-size:12.5px; color:var(--azul-dark); }
        .meta { margin-top:22px; font-size:12px; color:#94a3b8; text-align:right; }
    </style>
</head>
<body>
<div class="wrap">
    <a href="<?= htmlspecialchars(eco_url('')) ?>" class="back"><i class="fa-solid fa-arrow-left"></i> Volver al inicio</a>
    <div class="card">
        <div class="head">
            <div class="ic"><i class="fa-solid fa-file-contract"></i></div>
            <div>
                <h1>Terminos y condiciones</h1>
                <p class="sub">EcoMadelleine · Centro de Diagnostico</p>
            </div>
        </div>

        <p>Estos terminos regulan el uso de la plataforma y los servicios de <strong>EcoMadelleine</strong>.
        Al crear una cuenta o solicitar un estudio aceptas las condiciones descritas a continuacion.</p>

        <h2>1. Objeto</h2>
        <p>EcoMadelleine ofrece servicios de diagnostico por ultrasonido y una plataforma para agendar citas,
        gestionar tu cuenta y recibir informes digitales realizados por la doctora.</p>

        <h2>2. Registro y cuenta</h2>
        <ul>
            <li>Debes proporcionar datos veraces, completos y actualizados (nombre, documento, telefono, direccion y correo).</li>
            <li>Eres responsable de la confidencialidad de tu contrasena y de la actividad de tu cuenta.</li>
            <li>El registro es personal; no debe usarse para suplantar a otra persona.</li>
        </ul>

        <h2>3. Citas y estudios</h2>
        <p>Las solicitudes de cita quedan sujetas a confirmacion del centro segun disponibilidad. Te
        contactaremos para confirmar el dia y la hora. Te pedimos llegar con la preparacion indicada para
        cada tipo de estudio.</p>

        <h2>4. Cancelaciones y reprogramacion</h2>
        <p>Si no puedes asistir, avisa con la mayor antelacion posible para reprogramar tu cita y liberar el
        cupo a otros pacientes.</p>

        <h2>5. Informes y resultados</h2>
        <p>Los informes ecograficos son un apoyo diagnostico y no sustituyen la valoracion de tu medico
        tratante. Se entregan en formato digital y pueden incluir firma electronica verificable.</p>

        <h2>6. Uso correcto de la plataforma</h2>
        <p>No esta permitido intentar acceder a cuentas ajenas, alterar el funcionamiento del sistema, ni
        usar la plataforma con fines ilicitos. Nos reservamos el derecho de suspender cuentas que incumplan
        estas condiciones.</p>

        <h2>7. Privacidad</h2>
        <p>El tratamiento de tus datos personales y de salud se rige por nuestro
        <a href="<?= htmlspecialchars(eco_url('privacidad')) ?>">aviso de privacidad</a>, que forma parte de
        estos terminos.</p>

        <h2>8. Limitacion de responsabilidad</h2>
        <p>El centro presta sus servicios con criterio clinico y diligencia profesional. No nos hacemos
        responsables de interrupciones del servicio ajenas a nuestro control ni del uso indebido de los
        informes por parte de terceros.</p>

        <h2>9. Cambios en los terminos</h2>
        <p>Podemos actualizar estos terminos. Los cambios relevantes se comunicaran a traves de la
        plataforma. El uso continuado de los servicios implica la aceptacion de la version vigente.</p>

        <h2>10. Contacto</h2>
        <p>Para cualquier consulta sobre estos terminos, escribe a
        <a href="mailto:madelleine.toro8@gmail.com">madelleine.toro8@gmail.com</a>.</p>

        <div class="note"><i class="fa-solid fa-circle-info"></i> Documento base. El centro debe revisarlo
        legalmente y ajustarlo a la normativa aplicable antes de su publicacion en produccion.</div>

        <div class="meta">Ultima actualizacion: <?= date('d/m/Y') ?></div>
    </div>
</div>
</body>
</html>
