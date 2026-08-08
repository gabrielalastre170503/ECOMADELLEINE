<?php
/**
 * Listas de precios: juegos de tarifas que se activan de una vez.
 *
 * Ver database/migrations/2026_listas_precios.sql para el porqué del diseño.
 * En resumen: la verdad sigue siendo `tipos_ecografias.precio` y
 * `precios_servicios.precio`; una lista es un juego guardado y activarla los
 * copia allí. Nada más del sistema necesita saber que estas tablas existen.
 *
 * Todas las funciones degradan si la migración no se ha corrido todavía, igual
 * que hace eco_precios_catalogo(): el control de precios debe seguir abriendo.
 */

if (!function_exists('eco_listas_precios_disponibles')) {

    /** ¿Está creada la tabla? Se consulta una vez por petición. */
    function eco_listas_precios_disponibles(mysqli $conex): bool
    {
        static $hay = null;
        if ($hay !== null) {
            return $hay;
        }
        try {
            // En PHP 8 mysqli lanza excepción: el @ NO la silencia (ver core/conexion.php).
            $conex->query("SELECT 1 FROM listas_precios LIMIT 1");
            $hay = true;
        } catch (\Throwable $e) {
            $hay = false;
        }
        return $hay;
    }

    /**
     * Todas las listas, la activa primero, con cuántos precios guarda cada una.
     *
     * @return array<int,array{id:int,nombre:string,descripcion:string,es_activa:bool,
     *                         aplicada_en:?string,items:int,estudios:int,servicios:int}>
     */
    function eco_listas_precios(mysqli $conex): array
    {
        if (!eco_listas_precios_disponibles($conex)) {
            return [];
        }
        $sql = "SELECT l.id, l.nombre, l.descripcion, l.es_activa, l.aplicada_en,
                       COUNT(i.clave) AS items,
                       SUM(i.origen = 'estudio')  AS estudios,
                       SUM(i.origen = 'servicio') AS servicios
                  FROM listas_precios l
                  LEFT JOIN listas_precios_items i ON i.lista_id = l.id
                 GROUP BY l.id
                 ORDER BY l.es_activa DESC, l.nombre ASC";
        $out = [];
        try {
            $r = $conex->query($sql);
            while ($row = $r->fetch_assoc()) {
                $out[] = [
                    'id'          => (int)$row['id'],
                    'nombre'      => (string)$row['nombre'],
                    'descripcion' => (string)($row['descripcion'] ?? ''),
                    'es_activa'   => (bool)$row['es_activa'],
                    'aplicada_en' => $row['aplicada_en'] !== null ? (string)$row['aplicada_en'] : null,
                    'items'       => (int)$row['items'],
                    'estudios'    => (int)$row['estudios'],
                    'servicios'   => (int)$row['servicios'],
                ];
            }
            $r->free();
        } catch (\Throwable $e) {
            error_log('eco_listas_precios: ' . $e->getMessage());
        }
        return $out;
    }

    /** La lista activa, o null si no hay ninguna (o no está la tabla). */
    function eco_lista_precios_activa(mysqli $conex): ?array
    {
        foreach (eco_listas_precios($conex) as $l) {
            if ($l['es_activa']) {
                return $l;
            }
        }
        return null;
    }

    /**
     * Precios guardados en una lista.
     *
     * @return array{estudio:array<string,float>,servicio:array<string,float>}
     */
    function eco_lista_precios_items(mysqli $conex, int $listaId): array
    {
        $out = ['estudio' => [], 'servicio' => []];
        if ($listaId <= 0 || !eco_listas_precios_disponibles($conex)) {
            return $out;
        }
        $st = $conex->prepare("SELECT origen, clave, precio FROM listas_precios_items WHERE lista_id = ?");
        $st->bind_param('i', $listaId);
        $st->execute();
        $r = $st->get_result();
        while ($row = $r->fetch_assoc()) {
            $out[(string)$row['origen']][(string)$row['clave']] = (float)$row['precio'];
        }
        $st->close();
        return $out;
    }

    /**
     * Copia los precios que están puestos AHORA dentro de una lista.
     *
     * Es lo que hace que cambiar de tarifa no pierda ediciones: antes de activar
     * otra lista se guarda la actual, venga el cambio de donde venga (control de
     * precios, gestión de estudios, o un UPDATE a mano).
     *
     * @return int cuántos precios quedaron guardados
     */
    function eco_lista_precios_capturar(mysqli $conex, int $listaId): int
    {
        if ($listaId <= 0 || !eco_listas_precios_disponibles($conex)) {
            return 0;
        }
        $n = 0;
        $ins = $conex->prepare(
            "INSERT INTO listas_precios_items (lista_id, origen, clave, precio)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE precio = VALUES(precio)"
        );
        $origen = ''; $clave = ''; $precio = 0.0;
        $ins->bind_param('issd', $listaId, $origen, $clave, $precio);

        $origen = 'estudio';
        $r = $conex->query("SELECT id, precio FROM tipos_ecografias");
        while ($row = $r->fetch_assoc()) {
            $clave  = (string)$row['id'];
            $precio = (float)$row['precio'];
            $ins->execute();
            $n++;
        }
        $r->free();

        $origen = 'servicio';
        $r = $conex->query("SELECT clave, precio FROM precios_servicios");
        while ($row = $r->fetch_assoc()) {
            $clave  = (string)$row['clave'];
            $precio = (float)$row['precio'];
            $ins->execute();
            $n++;
        }
        $r->free();
        $ins->close();

        return $n;
    }

    /**
     * Crea una lista con los precios actuales. No la activa: se crea para
     * editarla y activarla cuando toque.
     *
     * @return array{ok:bool,message:string,id:int}
     */
    function eco_lista_precios_crear(mysqli $conex, string $nombre, string $descripcion, ?int $usuarioId): array
    {
        if (!eco_listas_precios_disponibles($conex)) {
            return ['ok' => false, 'message' => 'Falta correr la migración de listas de precios.', 'id' => 0];
        }
        $nombre = trim($nombre);
        if ($nombre === '') {
            return ['ok' => false, 'message' => 'Ponle un nombre a la lista.', 'id' => 0];
        }
        if (mb_strlen($nombre) > 80) {
            return ['ok' => false, 'message' => 'El nombre no puede pasar de 80 caracteres.', 'id' => 0];
        }
        $descripcion = mb_substr(trim($descripcion), 0, 255);

        try {
            $ins = $conex->prepare(
                "INSERT INTO listas_precios (nombre, descripcion, es_activa, creada_por_id) VALUES (?, ?, 0, ?)"
            );
            $ins->bind_param('ssi', $nombre, $descripcion, $usuarioId);
            $ins->execute();
            $id = (int)$ins->insert_id;
            $ins->close();
        } catch (\Throwable $e) {
            // 1062 = clave duplicada: el nombre es único a propósito, para que
            // no haya dos "Promoción Yumare" y se active la que no era.
            if ((int)$conex->errno === 1062) {
                return ['ok' => false, 'message' => 'Ya existe una lista con ese nombre.', 'id' => 0];
            }
            error_log('eco_lista_precios_crear: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'No se pudo crear la lista.', 'id' => 0];
        }

        $n = eco_lista_precios_capturar($conex, $id);
        return ['ok' => true, 'message' => 'Lista «' . $nombre . '» creada con ' . $n . ' precios.', 'id' => $id];
    }

    /**
     * Activa una lista: guarda antes los precios de la que estaba activa y
     * copia los de esta a las tablas que lee el resto del sistema.
     *
     * Va en transacción: una tarifa a medio aplicar cobraría mal.
     *
     * @return array{ok:bool,message:string,aplicados:int,sin_precio:int}
     */
    function eco_lista_precios_aplicar(mysqli $conex, int $listaId): array
    {
        $fallo = static fn(string $m) => ['ok' => false, 'message' => $m, 'aplicados' => 0, 'sin_precio' => 0];

        if (!eco_listas_precios_disponibles($conex)) {
            return $fallo('Falta correr la migración de listas de precios.');
        }
        $st = $conex->prepare("SELECT nombre, es_activa FROM listas_precios WHERE id = ?");
        $st->bind_param('i', $listaId);
        $st->execute();
        $lista = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$lista) {
            return $fallo('Esa lista no existe.');
        }

        // La activa se guarda ANTES de tocar nada: si alguien cambió un precio
        // desde otra pantalla, ese cambio queda dentro de su lista y no se pierde.
        $activa = eco_lista_precios_activa($conex);
        if ($activa && $activa['id'] !== $listaId) {
            eco_lista_precios_capturar($conex, $activa['id']);
        }

        $items = eco_lista_precios_items($conex, $listaId);
        if (!$items['estudio'] && !$items['servicio']) {
            return $fallo('La lista «' . $lista['nombre'] . '» no tiene precios guardados.');
        }

        // Precios que hay ahora, para tocar solo los que de verdad cambian y
        // poder decir cuántos cambiaron. affected_rows no sirve: devuelve 0
        // tanto si la fila no existe como si el valor ya era ese.
        $vivos = ['estudio' => [], 'servicio' => []];
        $r = $conex->query("SELECT id, precio FROM tipos_ecografias");
        while ($row = $r->fetch_assoc()) {
            $vivos['estudio'][(string)$row['id']] = (float)$row['precio'];
        }
        $r->free();
        $r = $conex->query("SELECT clave, precio FROM precios_servicios");
        while ($row = $r->fetch_assoc()) {
            $vivos['servicio'][(string)$row['clave']] = (float)$row['precio'];
        }
        $r->free();

        $conex->begin_transaction();
        try {
            $aplicados = 0;

            $upE = $conex->prepare("UPDATE tipos_ecografias SET precio = ? WHERE id = ?");
            foreach ($items['estudio'] as $id => $precio) {
                $k = (string)$id;
                // Un estudio borrado del catálogo sigue guardado en tarifas viejas.
                if (!isset($vivos['estudio'][$k]) || abs($vivos['estudio'][$k] - $precio) < 0.005) {
                    continue;
                }
                $idInt = (int)$id; $precio = (float)$precio;
                $upE->bind_param('di', $precio, $idInt);
                $upE->execute();
                $aplicados++;
            }
            $upE->close();

            $upS = $conex->prepare("UPDATE precios_servicios SET precio = ? WHERE clave = ?");
            foreach ($items['servicio'] as $clave => $precio) {
                $k = (string)$clave;
                if (!isset($vivos['servicio'][$k]) || abs($vivos['servicio'][$k] - $precio) < 0.005) {
                    continue;
                }
                $precio = (float)$precio;
                $upS->bind_param('ds', $precio, $k);
                $upS->execute();
                $aplicados++;
            }
            $upS->close();

            $conex->query("UPDATE listas_precios SET es_activa = 0 WHERE es_activa = 1");
            $act = $conex->prepare("UPDATE listas_precios SET es_activa = 1, aplicada_en = NOW() WHERE id = ?");
            $act->bind_param('i', $listaId);
            $act->execute();
            $act->close();

            $conex->commit();
        } catch (\Throwable $e) {
            $conex->rollback();
            error_log('eco_lista_precios_aplicar: ' . $e->getMessage());
            return $fallo('No se pudo aplicar la tarifa. No se cambió ningún precio.');
        }

        // Estudios activos que la lista no cubre: se quedan como estaban. Pasa
        // con los que se dieron de alta después de guardar la lista.
        $sinPrecio = 0;
        $r = $conex->query("SELECT id FROM tipos_ecografias WHERE activo = 1");
        while ($row = $r->fetch_assoc()) {
            if (!isset($items['estudio'][(string)(int)$row['id']])) {
                $sinPrecio++;
            }
        }
        $r->free();

        return [
            'ok'         => true,
            'message'    => 'Tarifa «' . $lista['nombre'] . '» aplicada.',
            'aplicados'  => $aplicados,
            'sin_precio' => $sinPrecio,
        ];
    }

    /** Borra una lista. La activa no se puede borrar: es la tarifa en uso. */
    function eco_lista_precios_eliminar(mysqli $conex, int $listaId): array
    {
        if (!eco_listas_precios_disponibles($conex)) {
            return ['ok' => false, 'message' => 'Falta correr la migración de listas de precios.'];
        }
        $st = $conex->prepare("SELECT nombre, es_activa FROM listas_precios WHERE id = ?");
        $st->bind_param('i', $listaId);
        $st->execute();
        $lista = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$lista) {
            return ['ok' => false, 'message' => 'Esa lista no existe.'];
        }
        if ((int)$lista['es_activa'] === 1) {
            return ['ok' => false, 'message' => 'No se puede borrar la tarifa en uso. Activa otra primero.'];
        }
        $del = $conex->prepare("DELETE FROM listas_precios WHERE id = ?");
        $del->bind_param('i', $listaId);
        $del->execute();
        $del->close();
        return ['ok' => true, 'message' => 'Lista «' . $lista['nombre'] . '» eliminada.'];
    }
}
