<?php
/**
 * Filtro "atendidos en" por rango de fechas, compartido por los dos listados de
 * pacientes que lo ofrecen:
 *   - api/buscar_pacientes_secretaria.php   (Gestión de pacientes, recepción)
 *   - api/mis_pacientes_ecografista.php     (Mis Pacientes, ecografista)
 *
 * Vive aquí para que ambos entiendan lo mismo por "hoy" o "últimos 7 días": si
 * cada uno calculara sus fechas, acabarían discrepando en los bordes del día.
 */

if (!function_exists('eco_rango_atencion')) {

    /**
     * Convierte el filtro elegido en un rango de datetime.
     *
     * @param string $rango 'hoy' | 'ayer' | 'semana' | 'fecha' | cualquier otro
     * @param string $fecha 'Y-m-d', solo se usa cuando $rango === 'fecha'
     * @return array{0:string,1:string}|null  [desde, hasta], o null para "sin filtrar"
     */
    function eco_rango_atencion(string $rango, string $fecha = ''): ?array
    {
        $hoy = new DateTimeImmutable('today');
        switch ($rango) {
            case 'hoy':
                $desde = $hoy;
                $hasta = $hoy;
                break;
            case 'ayer':
                $desde = $hoy->modify('-1 day');
                $hasta = $desde;
                break;
            case 'semana': // hoy incluido: 7 días en total
                $desde = $hoy->modify('-6 days');
                $hasta = $hoy;
                break;
            case 'fecha':
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                    return null;
                }
                try {
                    $desde = new DateTimeImmutable($fecha);
                } catch (Exception $e) {
                    return null;
                }
                $hasta = $desde;
                break;
            default:
                return null;
        }
        return [$desde->format('Y-m-d') . ' 00:00:00', $hasta->format('Y-m-d') . ' 23:59:59'];
    }

    /**
     * Mensaje de lista vacía acorde al filtro activo.
     */
    function eco_rango_atencion_vacio(string $rango, ?array $rangoFechas, bool $hayBusqueda = false): string
    {
        if ($rangoFechas === null) {
            return 'No se encontraron pacientes que coincidan con tu búsqueda.';
        }
        if ($hayBusqueda) {
            return 'Ningún paciente coincide con tu búsqueda en ese rango de fechas.';
        }
        return [
            'hoy'    => 'Todavía no hay pacientes atendidos hoy.',
            'ayer'   => 'No se atendió a ningún paciente ayer.',
            'semana' => 'No hay pacientes atendidos en los últimos 7 días.',
            'fecha'  => 'No se atendió a ningún paciente el ' . date('d/m/Y', strtotime($rangoFechas[0])) . '.',
        ][$rango] ?? 'No se encontraron pacientes en ese rango de fechas.';
    }
}
