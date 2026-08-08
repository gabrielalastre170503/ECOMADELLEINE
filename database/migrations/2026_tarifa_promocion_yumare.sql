-- =============================================================================
-- Tarifa «Promoción Yumare» — precios del flyer de la jornada
--
-- Crea la tarifa GUARDADA, sin activarla: los precios que se están cobrando no
-- cambian hasta que alguien pulse «Activar» en Control de precios.
--
-- Los estudios se buscan por `codigo` (clave única), no por id: los ids pueden
-- no coincidir con los de este entorno.
--
-- De dónde sale cada precio
-- -------------------------
-- Del flyer, literal:
--   Abdominal/Renal 20 · Abdominal 10 · Renal 10 · Pélvica 10
--   Musculoesquelética 15 · Próstata 10 · Mamaria 10 · Tiroides 10
--   Obstétrica 15 · Partes Blandas 10 · Testicular 10 · Cuello 10
--   Transvaginal 15 · Consulta 10 · Citología+procesamiento+eco pélvica 25
--   Eco + Consulta 20
--
-- Inferido, porque el flyer da UN precio para un grupo que aquí está abierto en
-- varios estudios (revisar si no era la intención):
--   · Obstétrica I Trim y II-III Trim   -> 15   (el flyer dice «Obstetrica 15$»)
--   · Hombro, codo, muñeca, cadera,
--     rodilla y tobillo                 -> 15   (del «Musculoesqueletic 15$»)
--   · Partes blandas general, de cuello
--     y región inguinal                 -> 10   (del «Partes Blandas 10$»)
--
-- Sin cambio respecto a la tarifa normal, por no aparecer en el flyer:
--   · Ecografía Pulmonar        20
--   · Citología suelta          20   (el flyer solo trae el paquete de 25)
--   · Procesamiento de muestra   3
--
-- Requiere 2026_listas_precios.sql.
-- =============================================================================

USE db_clinica_ecografias;

-- Ver la nota de 2026_listas_precios.sql: sin esto los acentos del nombre y la
-- descripción se guardan rotos al correr la migración desde Windows.
SET NAMES utf8mb4;

INSERT INTO listas_precios (nombre, descripcion, es_activa)
SELECT 'Promoción Yumare', 'Jornada en Yumare: precios del flyer. No incluye pulmonar ni citología suelta.', 0
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT nombre FROM listas_precios) AS x WHERE x.nombre = 'Promoción Yumare');

-- Entran TODOS los estudios: el precio del flyer si lo fija, y si no el que
-- tengan ahora. Así ninguno se queda fuera de la tarifa.
--
-- Una sola pasada y con guarda a propósito. Con dos INSERT encadenados y
-- ON DUPLICATE KEY, reejecutar la migración pisaría la tarifa con los precios
-- que hubiera en ese momento y se perdería lo que hubieran editado en ella.
INSERT INTO listas_precios_items (lista_id, origen, clave, precio)
SELECT l.id, 'estudio', t.id, COALESCE(f.precio, t.precio)
  FROM listas_precios l
  CROSS JOIN tipos_ecografias t
  LEFT JOIN (
        SELECT 'ECO_ABD_REN'         AS codigo, 20.00 AS precio UNION ALL
        SELECT 'eco_abdominal',              10.00 UNION ALL
        SELECT 'ECO_RENAL',                  10.00 UNION ALL
        SELECT 'ECO_PELVICA',                10.00 UNION ALL
        SELECT 'ECO_PROST',                  10.00 UNION ALL
        SELECT 'ECO_MAMA',                   10.00 UNION ALL
        SELECT 'eco_tiroides',               10.00 UNION ALL
        SELECT 'ECO_TEST',                   10.00 UNION ALL
        SELECT 'ECO_CUELLO',                 10.00 UNION ALL
        SELECT 'ECO_PBLANCAS',               10.00 UNION ALL
        SELECT 'ECO_PBL_GENERAL',            10.00 UNION ALL
        SELECT 'ECO_PBL_CUELLO',             10.00 UNION ALL
        SELECT 'ECO_PBL_INGUINAL',           10.00 UNION ALL
        SELECT 'ECO_MUSCU',                  15.00 UNION ALL
        SELECT 'ECO_MUSCU_HOMBRO',           15.00 UNION ALL
        SELECT 'ECO_MUSCU_CODO',             15.00 UNION ALL
        SELECT 'ECO_MUSCU_MUNECA',           15.00 UNION ALL
        SELECT 'ECO_MUSCU_CADERA',           15.00 UNION ALL
        SELECT 'ECO_MUSCU_RODILLA',          15.00 UNION ALL
        SELECT 'ECO_MUSCU_TOBILLO',          15.00 UNION ALL
        SELECT 'eco_obstetrica',             15.00 UNION ALL
        SELECT 'ECO_OBS_I_TRIM',             15.00 UNION ALL
        SELECT 'ECO_OBS_II_III_TRIM',        15.00 UNION ALL
        SELECT 'ECO_TRANSV',                 15.00
  ) AS f ON f.codigo = t.codigo
 WHERE l.nombre = 'Promoción Yumare'
   AND NOT EXISTS (
        SELECT 1 FROM (SELECT lista_id, origen FROM listas_precios_items) AS i
         WHERE i.lista_id = l.id AND i.origen = 'estudio');

INSERT INTO listas_precios_items (lista_id, origen, clave, precio)
SELECT l.id, 'servicio', p.clave, COALESCE(f.precio, p.precio)
  FROM listas_precios l
  CROSS JOIN precios_servicios p
  LEFT JOIN (
        SELECT 'consulta'           AS clave, 10.00 AS precio UNION ALL
        SELECT 'combo_cito',                  25.00 UNION ALL
        SELECT 'promo_eco_consulta',          20.00
  ) AS f ON f.clave = p.clave
 WHERE l.nombre = 'Promoción Yumare'
   AND NOT EXISTS (
        SELECT 1 FROM (SELECT lista_id, origen FROM listas_precios_items) AS i
         WHERE i.lista_id = l.id AND i.origen = 'servicio');
