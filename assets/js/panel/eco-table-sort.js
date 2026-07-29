/**
 * Ordenamiento client-side para tablas .rx-pac-table (recepción / ecografista).
 */
(function () {
    'use strict';

    var sortState = { table: null, col: null, dir: 'asc' };

    function sortCompare(va, vb, type) {
        va = va == null ? '' : String(va);
        vb = vb == null ? '' : String(vb);
        if (type === 'number') {
            var na = parseFloat(va.replace(/[^\d.-]/g, '')) || 0;
            var nb = parseFloat(vb.replace(/[^\d.-]/g, '')) || 0;
            return na - nb;
        }
        if (type === 'date') {
            if (!va && !vb) return 0;
            if (!va) return 1;
            if (!vb) return -1;
            return va.localeCompare(vb);
        }
        return va.localeCompare(vb, 'es', { sensitivity: 'base', numeric: true });
    }

    function cellSortValue(cell) {
        if (!cell) return '';
        var v = cell.getAttribute('data-sort-value');
        if (v !== null && v !== '') return v;
        return (cell.textContent || '').trim().toLowerCase();
    }

    function ordenar(th) {
        var table = th.closest('table.rx-pac-table');
        var tbody = table && table.querySelector('tbody');
        if (!tbody) return;

        var col = parseInt(th.getAttribute('data-sort-col'), 10);
        var type = th.getAttribute('data-sort-type') || 'text';
        var dir = 'asc';
        if (sortState.table === table && sortState.col === col) {
            dir = sortState.dir === 'asc' ? 'desc' : 'asc';
        }
        sortState = { table: table, col: col, dir: dir };

        /* Una fila puede llevar detrás su fila de detalle desplegable
           ([data-rx-detalle]). Se ordenan como pareja: si se ordenaran sueltas,
           el detalle acabaria bajo otro paciente. */
        var todas = Array.prototype.slice.call(tbody.children);
        var pares = [];
        for (var i = 0; i < todas.length; i++) {
            if (todas[i].hasAttribute('data-rx-detalle')) continue;
            var siguiente = todas[i + 1];
            pares.push({
                fila: todas[i],
                detalle: (siguiente && siguiente.hasAttribute('data-rx-detalle')) ? siguiente : null
            });
        }

        pares.sort(function (pa, pb) {
            var ca = pa.fila.children[col];
            var cb = pb.fila.children[col];
            var cmp = sortCompare(cellSortValue(ca), cellSortValue(cb), type);
            return dir === 'asc' ? cmp : -cmp;
        });

        tbody.classList.add('rx-sorting');
        pares.forEach(function (p) {
            tbody.appendChild(p.fila);
            if (p.detalle) tbody.appendChild(p.detalle);
        });
        window.setTimeout(function () { tbody.classList.remove('rx-sorting'); }, 120);

        table.querySelectorAll('th.rx-sort-th').forEach(function (h) {
            h.classList.remove('rx-sort-th--asc', 'rx-sort-th--desc');
            h.setAttribute('aria-sort', 'none');
        });
        th.classList.add(dir === 'asc' ? 'rx-sort-th--asc' : 'rx-sort-th--desc');
        th.setAttribute('aria-sort', dir === 'asc' ? 'ascending' : 'descending');
    }

    function initContainer(container) {
        if (!container || container._ecoSortInited) return;
        container._ecoSortInited = true;

        container.addEventListener('click', function (e) {
            var th = e.target.closest('th.rx-sort-th');
            if (!th || !container.contains(th)) return;
            e.preventDefault();
            ordenar(th);
        });

        container.addEventListener('keydown', function (e) {
            if (e.key !== 'Enter' && e.key !== ' ') return;
            var th = e.target.closest('th.rx-sort-th');
            if (!th || !container.contains(th)) return;
            e.preventDefault();
            ordenar(th);
        });
    }

    window.EcoTableSort = {
        init: initContainer,
        reset: function () {
            sortState = { table: null, col: null, dir: 'asc' };
        }
    };

    window.rxResetTablaOrden = window.EcoTableSort.reset;
})();
