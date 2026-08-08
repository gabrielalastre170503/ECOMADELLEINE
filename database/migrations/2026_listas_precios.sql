-- =============================================================================
-- Listas de precios — tarifas alternas que se activan de una vez
--
-- El centro trabaja con una tarifa normal y, algunos días o semanas, con una
-- tarifa de promoción (jornadas fuera de la sede). Hasta ahora cambiar de una a
-- otra obligaba a editar los ~23 estudios y los 5 servicios uno por uno, y a
-- recordar de memoria los importes viejos para volver atrás.
--
-- Cómo funciona
-- -------------
-- La verdad sigue estando en `tipos_ecografias.precio` y `precios_servicios.
-- precio`: TODO el sistema (facturación, catálogos, portal del paciente) lee de
-- ahí y no se entera de que existen listas. Una lista es un juego de precios
-- guardado; activarla los COPIA a esas dos tablas. Así:
--   · no hay que tocar ninguna de las decenas de consultas que leen `precio`;
--   · las citas ya cobradas conservan su importe (guardan su propio monto);
--   · si algún día se borran estas tablas, el sistema sigue funcionando.
--
-- Antes de activar otra lista, la aplicación guarda los precios que están
-- puestos en la lista activa. Por eso cambiar de tarifa nunca pierde una
-- edición, venga del control de precios o de la gestión de estudios.
-- =============================================================================

USE db_clinica_ecografias;

-- Imprescindible en Windows: `mysql.exe < archivo.sql` interpreta los bytes con
-- la página de códigos de la consola, no como UTF-8, y los acentos se guardan
-- rotos («Promoci├│n» en vez de «Promoción»). Esto fija la conexión a utf8mb4.
SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS listas_precios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(80)  NOT NULL,
    descripcion   VARCHAR(255) NULL,
    es_activa     TINYINT(1)   NOT NULL DEFAULT 0,
    creada_por_id INT          NULL,
    creada_en     TIMESTAMP    NOT NULL DEFAULT current_timestamp(),
    aplicada_en   DATETIME     NULL,
    UNIQUE KEY uk_listas_precios_nombre (nombre),
    KEY idx_listas_precios_activa (es_activa)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- `clave` guarda el id del estudio o la clave del servicio, según `origen`.
-- Se borra en cascada: una lista eliminada no deja precios huérfanos.
CREATE TABLE IF NOT EXISTS listas_precios_items (
    lista_id INT           NOT NULL,
    origen   ENUM('estudio','servicio') NOT NULL,
    clave    VARCHAR(40)   NOT NULL,
    precio   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    PRIMARY KEY (lista_id, origen, clave),
    CONSTRAINT fk_lpi_lista FOREIGN KEY (lista_id) REFERENCES listas_precios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Semilla ────────────────────────────────────────────────────────────────
-- Los precios que están cargados AHORA se guardan como «Tarifa normal» y esa
-- queda activa. Sin esto, la primera vez que alguien activase una promoción no
-- habría a dónde volver.
INSERT INTO listas_precios (nombre, descripcion, es_activa, aplicada_en)
SELECT 'Tarifa normal', 'Precios habituales en sede, de lunes a sábado.', 1, NOW()
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM (SELECT id FROM listas_precios) AS x);

-- El NOT EXISTS sobre una tabla derivada es lo que permite consultar la propia
-- tabla destino: sin él, reejecutar la migración sobrescribiría la tarifa
-- guardada con la que estuviera puesta en ese momento (quizá la de promoción).
INSERT INTO listas_precios_items (lista_id, origen, clave, precio)
SELECT l.id, 'estudio', t.id, t.precio
  FROM listas_precios l
  CROSS JOIN tipos_ecografias t
 WHERE l.nombre = 'Tarifa normal'
   AND NOT EXISTS (SELECT 1 FROM (SELECT lista_id FROM listas_precios_items) AS i WHERE i.lista_id = l.id);

INSERT INTO listas_precios_items (lista_id, origen, clave, precio)
SELECT l.id, 'servicio', p.clave, p.precio
  FROM listas_precios l
  CROSS JOIN precios_servicios p
 WHERE l.nombre = 'Tarifa normal'
   AND NOT EXISTS (
        SELECT 1 FROM (SELECT lista_id, origen FROM listas_precios_items) AS i
         WHERE i.lista_id = l.id AND i.origen = 'servicio');
