-- Iconos de los tipos de ecografía: limpieza para un juego más sobrio.
--
-- Criterio: un icono por región anatómica, sin metáforas que no significan
-- nada y sin pictogramas de personas. Los estudios de la misma región pueden
-- compartir icono; dos regiones distintas no.
--
-- Se corrigen cuatro problemas concretos:
--   1. "Abdominal Total" usaba fa-wave-square, que es el logotipo de la app.
--   2. Tiroides y Renal compartían fa-shield-halved: un escudo no dice nada
--      de ninguna de las dos, y encima eran regiones distintas.
--   3. Mamaria y Pélvica compartían fa-venus (símbolo de género).
--   4. Las musculoesqueléticas usaban personas andando, corriendo y huellas
--      de zapato: cuatro pictogramas distintos para una misma región.
--
-- Todos los iconos verificados contra Font Awesome 6.5.1 free (el que carga
-- layouts/shell.php). Reversión al final del archivo.

UPDATE tipos_ecografias SET icono = 'fa-solid fa-circle-dot'   WHERE id = 1;   -- Abdominal Total
UPDATE tipos_ecografias SET icono = 'fa-solid fa-circle-nodes' WHERE id = 3;   -- Tiroides
UPDATE tipos_ecografias SET icono = 'fa-solid fa-droplet'      WHERE id = 5;   -- Renal
UPDATE tipos_ecografias SET icono = 'fa-solid fa-ribbon'       WHERE id = 9;   -- Mamaria

-- Musculoesqueléticas: una sola región, un solo icono.
UPDATE tipos_ecografias SET icono = 'fa-solid fa-bone'
 WHERE id IN (14, 15, 16, 17, 18, 19);

-- Reversión:
-- UPDATE tipos_ecografias SET icono = 'fa-solid fa-wave-square'    WHERE id = 1;
-- UPDATE tipos_ecografias SET icono = 'fa-solid fa-shield-halved'  WHERE id = 3;
-- UPDATE tipos_ecografias SET icono = 'fa-solid fa-shield-halved'  WHERE id = 5;
-- UPDATE tipos_ecografias SET icono = 'fa-solid fa-venus'          WHERE id = 9;
-- UPDATE tipos_ecografias SET icono = 'fa-solid fa-person'         WHERE id = 14;
-- UPDATE tipos_ecografias SET icono = 'fa-solid fa-bone'           WHERE id = 15;
-- UPDATE tipos_ecografias SET icono = 'fa-solid fa-hand'           WHERE id = 16;
-- UPDATE tipos_ecografias SET icono = 'fa-solid fa-person-walking' WHERE id = 17;
-- UPDATE tipos_ecografias SET icono = 'fa-solid fa-person-running' WHERE id = 18;
-- UPDATE tipos_ecografias SET icono = 'fa-solid fa-shoe-prints'    WHERE id = 19;
