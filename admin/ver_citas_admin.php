<?php
session_start();
include __DIR__ . '/../core/conexion.php';

// Seguridad: Solo los administradores pueden acceder a esta página
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] != 'administrador') {
    header('Location: ' . eco_url('login'));
    exit();
}

// Consulta actualizada para incluir la cédula del paciente
$sql = "SELECT 
            c.id, 
            c.fecha_cita, 
            c.estado,
            paciente.nombre_completo as paciente_nombre,
            paciente.cedula as paciente_cedula, -- <-- Campo añadido
            psicologo.nombre_completo as psicologo_nombre
        FROM citas c
        JOIN usuarios paciente ON c.paciente_id = paciente.id
        LEFT JOIN usuarios psicologo ON c.ecografista_id = psicologo.id
        ORDER BY c.fecha_solicitud DESC";

$resultado = $conex->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Citas - EcoMadelleine</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
        <style>
        /* (Tus estilos existentes para la página) */
        body { background-color: #f0f2f5; font-family: "Poppins", sans-serif; margin: 0; padding: 30px; }
        .main-container { max-width: 1200px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); }
        h1 { margin-top: 0; }
        .back-link { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #555; font-weight: 500; }
        .search-container { position: relative; margin: 25px 0; }
        .search-container i { position: absolute; left: 15px; top: 50%; transform: translateY(-50%); color: #aaa; }
        .search-container input { width: 100%; padding: 12px 20px 12px 45px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; box-sizing: border-box; }
        .users-table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        .users-table th, .users-table td { padding: 15px; text-align: left; border-bottom: 1px solid #e9e9e9; }
        .users-table th { background-color: #fafafa; font-weight: 600; color: #555; text-transform: uppercase; font-size: 12px; }
        .users-table tr:hover { background-color: #f7f7f7; }
        .status-badge { padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 500; color: white; }
        .status-pendiente { background-color: #ffc107; color: #333; }
        .status-confirmada { background-color: #17a2b8; }
        .status-cancelada { background-color: #dc3545; }
        .status-completada { background-color: #28a745; }
        .status-reprogramada { background-color: #fd7e14; }
        .action-links a, .action-links button { text-decoration: none; padding: 6px 12px; border-radius: 5px; font-weight: 500; color: white !important; display: inline-block; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.2s; font-size: 13px; margin-right: 8px; border: none; cursor: pointer; font-family: inherit; }
        .action-links a:last-child { margin-right: 0; }
        .action-links a:hover, .action-links button:hover { transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
        .action-links a.reject, .action-links button.reject { background-color: #dc3545; }
        
        /* --- ESTILOS PARA LA TABLA ORDENABLE --- */
        .sortable-header {
            cursor: pointer;
            position: relative;
            user-select: none; /* Evita que el texto se seleccione al hacer clic */
        }
        .sortable-header::after {
            content: ' \2195'; /* Flecha arriba y abajo */
            font-size: 14px;
            color: #ccc;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
        }
        .sortable-header.sort-asc::after {
            content: ' \25B2'; /* Flecha hacia arriba */
            color: #02b1f4;
        }
        .sortable-header.sort-desc::after {
            content: ' \25BC'; /* Flecha hacia abajo */
            color: #02b1f4;
        }

        /* --- PAGINACIÓN --- */
        .eco-pager { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 18px; flex-wrap: wrap; }
        .eco-pager__btn { background: #02b1f4; color: #fff; border: none; padding: 8px 16px; border-radius: 6px; font: inherit; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: background .2s; }
        .eco-pager__btn:hover:not(:disabled) { background: #0299d6; }
        .eco-pager__btn:disabled { background: #cbd5e1; cursor: not-allowed; }
        .eco-pager__info { color: #555; font-size: 13px; }
    </style>
</head>
<body>
    <div class="main-container">
        <a href="<?= eco_url('dashboard') ?>" class="back-link"><i class="fa-solid fa-arrow-left"></i> Volver al Panel</a>
        <h1>Historial Completo de Citas</h1>

        <!-- BARRA DE BÚSQUEDA AÑADIDA -->
<div class="search-container">
    <i class="fa-solid fa-search"></i>
    <input type="text" id="buscador-citas-admin" placeholder="Buscar por paciente o profesional...">
</div>

<!-- Contenedor para la tabla de resultados -->
<div id="tabla-citas-admin-container">
    <!-- El código PHP que genera tu tabla actual va aquí dentro -->
        <?php if ($resultado && $resultado->num_rows > 0): ?>
            <table class="users-table" id="citas-table"> <!-- Añadimos un ID a la tabla -->
        <thead>
            <tr>
                <th class="sortable-header" data-sort="paciente">Paciente</th>
                <th class="sortable-header" data-sort="cedula">Cédula</th>
                <th class="sortable-header" data-sort="profesional">Profesional Asignado</th>
                <th class="sortable-header" data-sort="fecha">Fecha Programada</th>
                <th class="sortable-header" data-sort="estado">Estado</th>
                <th>Acciones</th> <!-- La columna de acciones no se ordena -->
            </tr>
        </thead>
    <tbody>
        <?php while($cita = $resultado->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($cita['paciente_nombre']); ?></td>
                <td><?php echo htmlspecialchars($cita['paciente_cedula']); ?></td> <!-- Nuevo campo -->
                <td><?php echo htmlspecialchars($cita['psicologo_nombre'] ?? 'No Asignado'); ?></td>
                <td><?php echo $cita['fecha_cita'] ? htmlspecialchars(date('d/m/Y h:i A', strtotime($cita['fecha_cita']))) : 'N/A'; ?></td>
                <td><span class="status-badge status-<?php echo htmlspecialchars($cita['estado']); ?>"><?php echo htmlspecialchars(ucfirst($cita['estado'])); ?></span></td>
                <td class="action-links">
                    <form method="post" action="<?= eco_url('api/borrar_cita_admin.php') ?>" style="display:inline"
                          onsubmit="return confirm('¿Estás seguro de que quieres eliminar esta cita permanentemente?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?php echo (int)$cita['id']; ?>">
                        <button type="submit" class="reject"><i class="fa-solid fa-trash"></i> Eliminar</button>
                    </form>
                </td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>
        <?php else: ?>
            <p>No hay ninguna cita registrada en el sistema.</p>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const buscador = document.getElementById('buscador-citas-admin');
        const contenedorTabla = document.getElementById('tabla-citas-admin-container');

        // --- FUNCIÓN PARA BUSCAR Y RECARGAR LA TABLA ---
        let queryActualCitas = '';
        function buscarCitas(query, page = 1) {
            queryActualCitas = query;
            fetch((window.ECO_BASE || '') + 'api/buscar_citas_admin.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `query=${encodeURIComponent(query)}&page=${page}`
            })
            .then(response => response.text())
            .then(data => {
                contenedorTabla.innerHTML = data;
            })
            .catch(error => console.error('Error en la búsqueda:', error));
        }

        // Carga inicial de la tabla
        buscarCitas('');

        // Búsqueda en tiempo real al escribir (vuelve a la página 1)
        buscador.addEventListener('keyup', function() {
            buscarCitas(this.value, 1);
        });

        // Paginación: los botones del footer reenvían la búsqueda con el mismo término.
        contenedorTabla.addEventListener('click', function(event) {
            const btn = event.target.closest('.eco-pager__btn');
            if (!btn || btn.disabled) return;
            const page = parseInt(btn.dataset.page, 10);
            if (page >= 1) buscarCitas(queryActualCitas, page);
        });

        // --- LÓGICA CORREGIDA PARA ORDENAR LA TABLA (DELEGACIÓN DE EVENTOS) ---
        // Escuchamos los clics en el contenedor de la tabla, que siempre existe.
        contenedorTabla.addEventListener('click', function(event) {
            const th = event.target.closest('.sortable-header');
            
            // Si el clic no fue en un encabezado ordenable, no hacemos nada.
            if (!th) return;

            const table = th.closest('table');
            const tbody = table.querySelector('tbody');
            const columnIndex = Array.from(th.parentNode.children).indexOf(th);
            const isAsc = !th.classList.contains('sort-asc');

            // Quitar clases de ordenamiento de otros encabezados
            table.querySelectorAll('.sortable-header').forEach(header => {
                if (header !== th) {
                    header.classList.remove('sort-asc', 'sort-desc');
                }
            });

            // Aplicar clases al encabezado actual
            th.classList.toggle('sort-asc', isAsc);
            th.classList.toggle('sort-desc', !isAsc);

            // Lógica de ordenamiento
            const getCellValue = (tr, idx) => tr.children[idx].innerText || tr.children[idx].textContent;
            const comparer = (idx, asc) => (a, b) => {
                const vA = getCellValue(asc ? a : b, idx);
                const vB = getCellValue(asc ? b : a, idx);
                // Intentar comparar como fechas si es la columna de fecha
                if (idx === 3) { // Asumiendo que la fecha es la 4ª columna (índice 3)
                    const dateA = new Date(vA.split(' ')[0].split('/').reverse().join('-') + ' ' + vA.split(' ')[1]);
                    const dateB = new Date(vB.split(' ')[0].split('/').reverse().join('-') + ' ' + vB.split(' ')[1]);
                    if (!isNaN(dateA) && !isNaN(dateB)) return dateA - dateB;
                }
                return vA.toString().localeCompare(vB, 'es', {numeric: true});
            };

            // Ordenar y volver a insertar las filas
            Array.from(tbody.querySelectorAll('tr'))
                .sort(comparer(columnIndex, isAsc))
                .forEach(tr => tbody.appendChild(tr));
        });
    });
</script>
</body>
</html>