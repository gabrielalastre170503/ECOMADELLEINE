<?php
/**
 * lib/informes/archivos.php — Fase 3: archivos medicos del informe (imagenes / adjuntos).
 *
 * Los binarios viven en disco bajo /uploads/informes/<informe_id>/ (carpeta
 * protegida con .htaccess deny-all). La tabla informe_archivos es el indice +
 * metadatos + hash sha256 de integridad. Se sirven SOLO via handler PHP con
 * control de acceso (nunca por URL directa).
 *
 * Las funciones de escritura lanzan RuntimeException con mensajes seguros para
 * el usuario; el endpoint los traduce a JSON.
 */

if (!function_exists('eco_uploads_base')) {

    /** Ruta absoluta de la carpeta base de uploads (la crea si no existe). */
    function eco_uploads_base(): string
    {
        // __DIR__ es lib/informes/, asi que hacen falta DOS niveles para llegar a
        // la raiz del proyecto. Con uno solo apuntaba a lib/uploads: los adjuntos
        // quedaban fuera de uploads/ y sin el .htaccess que impide servirlos por HTTP.
        $base = __DIR__ . '/../../uploads';
        if (!is_dir($base)) {
            @mkdir($base, 0775, true);
        }
        return $base;
    }

    /** Whitelist de tipos permitidos: mime real => extension. */
    function eco_archivo_tipos_permitidos(): array
    {
        return [
            'image/jpeg'      => 'jpg',
            'image/png'       => 'png',
            'image/webp'      => 'webp',
            'application/pdf' => 'pdf',
        ];
    }

    /** Maximo de bytes por archivo (15 MB). */
    function eco_archivo_max_bytes(): int
    {
        return 15 * 1024 * 1024;
    }

    /**
     * Valida un archivo de $_FILES por CONTENIDO (no por nombre/extension del
     * cliente). Devuelve [mime, ext] o lanza RuntimeException.
     */
    function eco_archivo_validar(array $file): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new RuntimeException('Parametro de archivo invalido.');
        }
        switch ($file['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('No se selecciono ningun archivo.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('El archivo excede el tamano permitido.');
            default:
                throw new RuntimeException('Error al subir el archivo.');
        }
        if ($file['size'] <= 0 || $file['size'] > eco_archivo_max_bytes()) {
            throw new RuntimeException('El archivo esta vacio o supera el limite de 15 MB.');
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new RuntimeException('Origen de archivo no valido.');
        }
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = (string)$finfo->file($file['tmp_name']);
        $permitidos = eco_archivo_tipos_permitidos();
        if (!isset($permitidos[$mime])) {
            throw new RuntimeException('Tipo de archivo no permitido. Solo JPG, PNG, WEBP o PDF.');
        }
        return [$mime, $permitidos[$mime]];
    }

    /**
     * Mueve el archivo a /uploads/informes/<informe_id>/ con nombre aleatorio e
     * inserta el registro. Devuelve el id de informe_archivos.
     *
     * @throws RuntimeException
     */
    function eco_archivo_guardar(mysqli $conex, int $informeId, array $file, string $categoria, ?int $subidoPor): int
    {
        [$mime, $ext] = eco_archivo_validar($file);
        $categoria = in_array($categoria, ['imagen', 'adjunto', 'pdf_firmado'], true) ? $categoria : 'imagen';

        $dirRel = 'informes/' . $informeId;
        $dirAbs = eco_uploads_base() . '/' . $dirRel;
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0775, true)) {
            throw new RuntimeException('No se pudo preparar el almacenamiento.');
        }

        $nombreGuardado = bin2hex(random_bytes(16)) . '.' . $ext;
        $rutaAbs = $dirAbs . '/' . $nombreGuardado;
        if (!move_uploaded_file($file['tmp_name'], $rutaAbs)) {
            throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
        }
        @chmod($rutaAbs, 0644);

        $sha            = hash_file('sha256', $rutaAbs) ?: null;
        $rutaRel        = $dirRel . '/' . $nombreGuardado;
        $nombreOriginal = substr((string)($file['name'] ?? 'archivo'), 0, 180);
        $tam            = (int)$file['size'];

        $sql = "INSERT INTO informe_archivos
                    (informe_id, categoria, nombre_original, nombre_guardado, ruta_rel, mime, tamano, sha256, subido_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        if (!($st = $conex->prepare($sql))) {
            @unlink($rutaAbs);
            throw new RuntimeException('Error de base de datos.');
        }
        $st->bind_param('isssssisi', $informeId, $categoria, $nombreOriginal, $nombreGuardado, $rutaRel, $mime, $tam, $sha, $subidoPor);
        if (!$st->execute()) {
            $st->close();
            @unlink($rutaAbs);
            throw new RuntimeException('No se pudo registrar el archivo.');
        }
        $id = (int)$st->insert_id;
        $st->close();
        return $id;
    }

    /**
     * Guarda un archivo a partir de un string en memoria (NO de $_FILES). Util
     * para artefactos generados por el servidor, p.ej. el PDF firmado.
     * Devuelve el id de informe_archivos.
     *
     * @throws RuntimeException
     */
    function eco_archivo_guardar_contenido(mysqli $conex, int $informeId, string $bytes, string $ext, string $mime, string $categoria, ?int $subidoPor, string $nombreOriginal): int
    {
        $categoria = in_array($categoria, ['imagen', 'adjunto', 'pdf_firmado'], true) ? $categoria : 'adjunto';

        $dirRel = 'informes/' . $informeId;
        $dirAbs = eco_uploads_base() . '/' . $dirRel;
        if (!is_dir($dirAbs) && !@mkdir($dirAbs, 0775, true)) {
            throw new RuntimeException('No se pudo preparar el almacenamiento.');
        }

        $nombreGuardado = bin2hex(random_bytes(16)) . '.' . $ext;
        $rutaAbs = $dirAbs . '/' . $nombreGuardado;
        if (file_put_contents($rutaAbs, $bytes) === false) {
            throw new RuntimeException('No se pudo guardar el archivo en el servidor.');
        }
        @chmod($rutaAbs, 0644);

        $sha            = hash('sha256', $bytes);
        $rutaRel        = $dirRel . '/' . $nombreGuardado;
        $nombreOriginal = substr($nombreOriginal, 0, 180);
        $tam            = strlen($bytes);

        $sql = "INSERT INTO informe_archivos
                    (informe_id, categoria, nombre_original, nombre_guardado, ruta_rel, mime, tamano, sha256, subido_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        if (!($st = $conex->prepare($sql))) {
            @unlink($rutaAbs);
            throw new RuntimeException('Error de base de datos.');
        }
        $st->bind_param('isssssisi', $informeId, $categoria, $nombreOriginal, $nombreGuardado, $rutaRel, $mime, $tam, $sha, $subidoPor);
        if (!$st->execute()) {
            $st->close();
            @unlink($rutaAbs);
            throw new RuntimeException('No se pudo registrar el archivo.');
        }
        $id = (int)$st->insert_id;
        $st->close();
        return $id;
    }

    /** Lista los archivos de un informe (opcionalmente filtrando por categoria). */
    function eco_archivos_de_informe(mysqli $conex, int $informeId, ?string $categoria = null): array
    {
        $out = [];
        if ($categoria !== null) {
            $st = $conex->prepare("SELECT id, categoria, nombre_original, mime, tamano, sha256, creado_en
                                   FROM informe_archivos WHERE informe_id = ? AND categoria = ?
                                   ORDER BY creado_en ASC, id ASC");
            if (!$st) return $out;
            $st->bind_param('is', $informeId, $categoria);
        } else {
            $st = $conex->prepare("SELECT id, categoria, nombre_original, mime, tamano, sha256, creado_en
                                   FROM informe_archivos WHERE informe_id = ?
                                   ORDER BY creado_en ASC, id ASC");
            if (!$st) return $out;
            $st->bind_param('i', $informeId);
        }
        $st->execute();
        $res = $st->get_result();
        while ($row = $res->fetch_assoc()) {
            $out[] = $row;
        }
        $st->close();
        return $out;
    }

    /** Una fila de informe_archivos con datos del informe (para control de acceso). */
    function eco_archivo_con_informe(mysqli $conex, int $archivoId): ?array
    {
        $st = $conex->prepare(
            "SELECT a.*, i.ecografista_id, i.paciente_id, i.estado AS informe_estado
             FROM informe_archivos a
             JOIN informes_estudios i ON i.id = a.informe_id
             WHERE a.id = ?"
        );
        if (!$st) {
            return null;
        }
        $st->bind_param('i', $archivoId);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();
        return $row ?: null;
    }

    /** Borra un archivo (registro en BD + binario en disco). */
    function eco_archivo_borrar(mysqli $conex, array $archivo): bool
    {
        $abs = eco_uploads_base() . '/' . $archivo['ruta_rel'];
        $id  = (int)$archivo['id'];
        if (!($st = $conex->prepare("DELETE FROM informe_archivos WHERE id = ?"))) {
            return false;
        }
        $st->bind_param('i', $id);
        $ok = $st->execute();
        $st->close();
        if ($ok && is_file($abs)) {
            @unlink($abs);
        }
        return (bool)$ok;
    }
}
