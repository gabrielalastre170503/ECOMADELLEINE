-- =============================================================================
-- Control de precios — servicios adicionales y promociones
--
-- Hasta ahora los precios de ecografía vivían en `tipos_ecografias.precio`
-- (editables), pero los de consulta / citología / procesamiento y los de las
-- promociones estaban ESCRITOS A FUEGO en lib/facturacion/facturacion.php.
-- Recepción no podía tocarlos sin editar código.
--
-- Esta tabla los saca del código. Las claves son las mismas que ya usaba
-- eco_servicios_adicionales(), para que el texto de motivo_principal de las
-- citas históricas se siga interpretando igual.
--
-- `tipo`:
--   servicio   → aparece como casilla marcable al registrar/programar
--   promocion  → no se marca; su precio SUSTITUYE al de sus componentes
--                cuando se dan juntos (lo aplica eco_calcular_bundle_multi).
-- =============================================================================

USE db_clinica_ecografias;

CREATE TABLE IF NOT EXISTS precios_servicios (
    clave       VARCHAR(40)  NOT NULL,
    etiqueta    VARCHAR(120) NOT NULL,
    descripcion VARCHAR(255) NULL,
    precio      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    icono       VARCHAR(60)  NOT NULL DEFAULT 'fa-solid fa-tag',
    tipo        ENUM('servicio','promocion') NOT NULL DEFAULT 'servicio',
    posicion    SMALLINT     NOT NULL DEFAULT 99,
    activo      TINYINT(1)   NOT NULL DEFAULT 1,
    actualizado TIMESTAMP    NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
    PRIMARY KEY (clave),
    KEY idx_precios_tipo (tipo, posicion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Semilla con los MISMOS importes que tenía el código, para que el cambio no
-- altere ningún cálculo hasta que alguien edite un precio a propósito.
INSERT INTO precios_servicios (clave, etiqueta, descripcion, precio, icono, tipo, posicion) VALUES
    ('consulta',      'Consulta médica',                       'Consulta con el especialista.',                    15.00, 'fa-solid fa-stethoscope', 'servicio', 1),
    ('citologia',     'Citología médica',                      'Toma de muestra citológica.',                      20.00, 'fa-solid fa-vial',        'servicio', 2),
    ('procesamiento', 'Procesamiento de muestra',              'Procesamiento de laboratorio de la muestra.',       3.00, 'fa-solid fa-microscope',  'servicio', 3),
    ('combo_cito',    'Procesamiento, Citologia + Eco pélvico', 'Paquete: sustituye el precio de sus tres partes.', 25.00, 'fa-solid fa-flask-vial',  'promocion', 1),
    ('promo_eco_consulta', 'Promoción Eco + Consulta',         'La ecografía más cara junto con la consulta.',      25.00, 'fa-solid fa-tags',        'promocion', 2)
ON DUPLICATE KEY UPDATE etiqueta = VALUES(etiqueta);
