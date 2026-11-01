import './bootstrap';
import Alpine from 'alpinejs';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";
Chart.defaults.font.size = 12;
Chart.defaults.color = '#52514e';
Chart.defaults.plugins.legend.labels.usePointStyle = true;
Chart.defaults.plugins.legend.labels.boxWidth = 8;
Chart.defaults.plugins.tooltip.backgroundColor = '#0b1d3a';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
window.Chart = Chart;

// Selector dependiente: al elegir un proyecto se cargan sus garantías y equipos,
// y se sugiere la cobertura (garantía vigente, contrato de servicio o sin cobertura).
Alpine.data('opcionesDeProyecto', (urlBase, garantiaInicial, equiposIniciales = [], coberturaInicial = '') => ({
    proyecto: '',
    garantia: garantiaInicial || '',
    // Equipos con falla: puede ser uno o varios de los equipos instalados
    seleccionados: (equiposIniciales || []).map(String),
    buscarEquipo: '',
    cobertura: coberturaInicial || 'garantia',
    coberturaManual: false,
    garantias: [],
    equipos: [],
    contratoVigente: false,
    cargando: false,
    async cargar() {
        this.garantias = [];
        this.equipos = [];
        if (!this.proyecto) return;
        this.cargando = true;
        const r = await fetch(urlBase.replace('__ID__', this.proyecto), { headers: { Accept: 'application/json' } });
        const d = r.ok ? await r.json() : { garantias: [], equipos: [] };
        // Se conserva la garantía indicada; si no es de este proyecto, se propone la primera activa.
        const elegida = d.garantias.find((o) => String(o.id) === String(this.garantia))
            || d.garantias.find((o) => o.estado !== 'vencida' && o.estado !== 'anulada');
        this.garantias = d.garantias;
        this.equipos = d.equipos || [];
        this.contratoVigente = !!d.contrato_vigente;
        this.cargando = false;
        this.garantia = '';
        // Solo quedan marcados los equipos que pertenecen al proyecto elegido
        this.seleccionados = this.seleccionados.filter((id) => this.equipos.some((o) => String(o.id) === id));
        // Se asigna después de que Alpine dibuje las opciones, para que el select la muestre.
        this.$nextTick(() => {
            this.garantia = elegida ? String(elegida.id) : '';
            this.sugerirCobertura();
        });
    },
    get equiposFiltrados() {
        const t = this.buscarEquipo.trim().toLowerCase();
        return t ? this.equipos.filter((o) => [o.texto, o.serie, o.ubicacion].join(' ').toLowerCase().includes(t)) : this.equipos;
    },
    get equiposElegidos() {
        return this.equipos.filter((o) => this.seleccionados.includes(String(o.id)));
    },
    marcarEquipo(id) {
        id = String(id);
        this.seleccionados = this.seleccionados.includes(id) ? this.seleccionados.filter((x) => x !== id) : [...this.seleccionados, id];
        this.alElegirEquipo();
    },
    // Si el primer equipo marcado tiene una garantía propia, se usa esa.
    alElegirEquipo() {
        const e = this.equiposElegidos[0];
        if (e && e.garantia_id && this.garantias.some((g) => String(g.id) === String(e.garantia_id))) {
            this.garantia = String(e.garantia_id);
        }
        this.sugerirCobertura();
    },
    sugerirCobertura() {
        if (this.coberturaManual) return;
        const g = this.seleccion;
        this.cobertura = g && g.estado !== 'vencida' && g.estado !== 'anulada' ? 'garantia' : (this.contratoVigente ? 'contrato' : 'sin_cobertura');
    },
    get seleccion() {
        return this.garantias.find((o) => String(o.id) === String(this.garantia));
    },
}));

// Garantía: el vencimiento se calcula con la fecha de inicio y la duración en meses.
Alpine.data('duracionGarantia', (inicio, meses, vence) => ({
    inicio: inicio || '',
    meses: meses || '',
    vence: vence || '',
    calcular() {
        if (!this.inicio || !this.meses) return;
        const [a, m, d] = this.inicio.split('-').map(Number);
        const destino = new Date(a, m - 1 + Number(this.meses), 1);
        // Sin desbordar: 31/01 + 1 mes = 28/02 (o 29 en bisiesto).
        const ultimo = new Date(destino.getFullYear(), destino.getMonth() + 1, 0).getDate();
        destino.setDate(Math.min(d, ultimo));
        const dos = (n) => String(n).padStart(2, '0');
        this.vence = `${destino.getFullYear()}-${dos(destino.getMonth() + 1)}-${dos(destino.getDate())}`;
    },
}));

// Filas dinámicas (equipos intervenidos, materiales usados).
Alpine.data('filas', (iniciales, vacia) => ({
    filas: iniciales && iniciales.length ? iniciales : [],
    agregar() {
        this.filas.push({ ...vacia });
    },
    quitar(i) {
        this.filas.splice(i, 1);
    },
}));

// Equipos de la hoja de servicio: además de las filas, arma el checklist según la categoría del equipo.
// Resultado de cada equipo de la incidencia (la tarjeta «Resultado de la visita» lo resume).
Alpine.store('visita', {
    reparados: 0,
    pendientes: 0,
    actualizar(filas, deIncidencia) {
        const propias = filas.filter((f) => deIncidencia.includes(Number(f.equipo_id)) && f.resultado);
        this.pendientes = propias.filter((f) => ['parcial', 'pendiente_repuesto'].includes(f.resultado)).length;
        this.reparados = propias.length - this.pendientes;
    },
});

Alpine.data('filasEquipos', (iniciales, vacia, checklists, deIncidencia = []) => ({
    filas: iniciales && iniciales.length ? iniciales : [],
    deIncidencia: (deIncidencia || []).map(Number),
    init() {
        this.filas.forEach((_, i) => this.sincronizar(i));
        this.$watch('filas', () => Alpine.store('visita').actualizar(this.filas, this.deIncidencia), { deep: true });
        Alpine.store('visita').actualizar(this.filas, this.deIncidencia);
    },
    // Equipo con falla de la incidencia: lleva su propio diagnóstico, resultado y fotos
    esDeIncidencia(f) {
        return this.deIncidencia.includes(Number(f.equipo_id));
    },
    agregar() {
        this.filas.push({ ...vacia, checklist: [] });
    },
    // Equipo elegido del inventario del cliente
    agregarInventario(e) {
        this.filas = this.filas.filter((f) => f.equipo || f.equipo_id);
        this.filas.push({ ...vacia, checklist: [], equipo_id: e.id, codigo: e.texto.split(' · ')[0], equipo: e.categoria,
            marca: e.marca || '', modelo: e.modelo || '', serie: e.serie || '', ubicacion: e.ubicacion || '' });
        this.sincronizar(this.filas.length - 1);
    },
    quitar(i) {
        this.filas.splice(i, 1);
    },
    clave(texto) {
        return (texto || '').normalize('NFD').replace(/[̀-ͯ]/g, '').trim().toLowerCase();
    },
    // Arma el checklist de la fila según la categoría; conserva los puntos ya marcados.
    sincronizar(i) {
        const f = this.filas[i];
        if (!f) return;
        const items = checklists[this.clave(f.equipo)] || [];
        const previo = f.checklist || [];
        if (JSON.stringify(previo.map((p) => p.item)) === JSON.stringify(items)) return;
        this.filas[i].checklist = items.map((item) => previo.find((p) => p.item === item) || { item, resultado: '', nota: '' });
    },
}));

// Mediciones de la hoja: tensiones y corrientes según el sistema (monofásico o trifásico).
const PUNTOS_MEDICION = {
    tension: {
        monofasico: ['L-N', 'L-T', 'N-T'],
        trifasico: ['R-S', 'S-T', 'T-R', 'R-N', 'S-N', 'T-N', 'N-T'],
    },
    corriente: {
        monofasico: ['L', 'N'],
        trifasico: ['R', 'S', 'T', 'N'],
    },
};
Alpine.data('mediciones', (iniciales) => ({
    filas: iniciales && iniciales.length ? iniciales : [],
    agregar() {
        this.filas.push({ parametro: '', valor: '', unidad: '' });
    },
    quitar(i) {
        this.filas.splice(i, 1);
    },
    // Reemplaza las tensiones (o corrientes) anteriores por las del sistema elegido
    usarSistema(tipo, sistema) {
        const nombre = tipo === 'tension' ? 'Tensión' : 'Corriente';
        const unidad = tipo === 'tension' ? 'V' : 'A';
        const sufijo = sistema === 'monofasico' ? 'monofásica' : 'trifásica';
        this.filas = this.filas.filter((f) => !(f.parametro || '').startsWith(nombre) || f.valor);
        PUNTOS_MEDICION[tipo][sistema].forEach((p) => this.filas.push({ parametro: `${nombre} ${sufijo} ${p}`, valor: '', unidad }));
    },
}));

// Fotos de la hoja de servicio: vista previa y leyenda de cada una (sale en el PDF).
Alpine.data('fotosConLeyenda', () => ({
    fotos: [],
    elegir(e) {
        this.fotos.forEach((f) => URL.revokeObjectURL(f.url));
        this.fotos = [...e.target.files].map((file) => ({
            url: URL.createObjectURL(file),
            leyenda: file.name.replace(/\.[^.]+$/, '').replace(/[_-]+/g, ' '),
        }));
    },
}));

// Lista de evidencias a subir con vista previa y categoría por archivo.
Alpine.data('evidencias', () => ({
    items: [],
    agregar(event) {
        for (const file of event.target.files) {
            const esImagen = file.type.startsWith('image/');
            this.items.push({
                file,
                nombre: file.name,
                tamano: (file.size / 1024 / 1024).toFixed(2) + ' MB',
                preview: esImagen ? URL.createObjectURL(file) : null,
                categoria: esImagen ? 'foto' : 'acta_conformidad',
                descripcion: '',
            });
        }
        event.target.value = '';
        this.sincronizar();
    },
    quitar(i) {
        this.items.splice(i, 1);
        this.sincronizar();
    },
    sincronizar() {
        const dt = new DataTransfer();
        this.items.forEach((it) => dt.items.add(it.file));
        this.$refs.input.files = dt.files;
    },
}));

// ---------------------------------------------------------------------------
// Ventana de confirmación y avisos flotantes (ver components/dialogos.blade.php)
// ---------------------------------------------------------------------------
let resolverConfirmacion = null;

Alpine.data('dialogos', (iniciales = []) => ({
    visible: false,
    titulo: '',
    mensaje: '',
    tipo: 'info',
    boton: 'Aceptar',
    lista: [],
    init() {
        iniciales.forEach((a) => this.avisar(a));
    },
    abrir({ titulo, mensaje, tipo, boton }) {
        this.titulo = titulo || '¿Confirmar la acción?';
        this.mensaje = mensaje || '';
        this.tipo = tipo || 'info';
        this.boton = boton || (this.tipo === 'peligro' ? 'Sí, continuar' : 'Aceptar');
        this.visible = true;
        this.$nextTick(() => this.$refs.aceptar?.focus());
    },
    responder(ok) {
        if (!this.visible) return;
        this.visible = false;
        resolverConfirmacion?.(ok);
        resolverConfirmacion = null;
    },
    avisar({ texto, tipo = 'info' }) {
        const aviso = { id: Date.now() + Math.random(), texto, tipo, visible: true };
        this.lista.push(aviso);
        setTimeout(() => { aviso.visible = false; }, tipo === 'error' ? 7000 : 4500);
    },
}));

/** Pregunta en la ventana propia del sistema; devuelve una promesa con true/false. */
window.confirmar = (opciones) => new Promise((resolve) => {
    resolverConfirmacion = resolve;
    window.dispatchEvent(new CustomEvent('confirmar', { detail: typeof opciones === 'string' ? { titulo: opciones } : opciones }));
});
window.aviso = (texto, tipo = 'info') => window.dispatchEvent(new CustomEvent('aviso', { detail: { texto, tipo } }));

// Formularios con data-confirmar: se pide confirmación antes de enviarlos.
document.addEventListener('submit', async (e) => {
    const form = e.target;
    if (!(form instanceof HTMLFormElement)) return;

    if (form.dataset.confirmar && form.dataset.confirmado !== '1') {
        e.preventDefault();
        const ok = await window.confirmar({
            titulo: form.dataset.confirmar,
            mensaje: form.dataset.confirmarDetalle || '',
            tipo: form.dataset.confirmarTipo || 'info',
            boton: form.dataset.confirmarBoton,
        });
        if (ok) {
            form.dataset.confirmado = '1';
            form.requestSubmit(e.submitter || undefined);
        }
        return;
    }

    // Evita envíos dobles (y registros duplicados): bloquea el botón mientras se guarda.
    if (form.method.toLowerCase() === 'post' && !form.target && !form.dataset.sinBloqueo) {
        const boton = e.submitter || form.querySelector('button[type=submit], button:not([type])');
        if (form.dataset.enviando === '1') { e.preventDefault(); return; }
        form.dataset.enviando = '1';
        if (boton) {
            boton.dataset.textoOriginal = boton.innerHTML;
            boton.disabled = true;
            boton.innerHTML = '<svg class="size-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3" opacity=".25"/><path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg> Procesando…';
        }
    }
});

// Si el usuario vuelve atrás, los botones quedan habilitados otra vez.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form[data-enviando="1"]').forEach((f) => {
        delete f.dataset.enviando;
        delete f.dataset.confirmado;
        f.querySelectorAll('button[data-texto-original]').forEach((b) => { b.disabled = false; b.innerHTML = b.dataset.textoOriginal; });
    });
});

// Mensajes de validación del navegador en español y claros.
document.addEventListener('invalid', (e) => {
    const c = e.target;
    if (!c.validity) return;
    const v = c.validity;
    let msg = '';
    if (v.valueMissing) msg = c.type === 'file' ? 'Seleccione un archivo.' : (c.tagName === 'SELECT' ? 'Seleccione una opción.' : 'Este campo es obligatorio.');
    else if (v.typeMismatch && c.type === 'email') msg = 'Ingrese un correo válido, por ejemplo: nombre@empresa.com';
    else if (v.typeMismatch) msg = 'El formato no es válido.';
    else if (v.patternMismatch) msg = c.dataset.mensaje || c.title || 'El formato no es válido.';
    else if (v.tooShort) msg = `Ingrese al menos ${c.minLength} caracteres.`;
    else if (v.tooLong) msg = `Máximo ${c.maxLength} caracteres.`;
    else if (v.rangeUnderflow) msg = `El valor mínimo es ${c.min}.`;
    else if (v.rangeOverflow) msg = c.type === 'date' || c.type === 'datetime-local' ? 'La fecha no puede ser posterior a la permitida.' : `El valor máximo es ${c.max}.`;
    else if (v.stepMismatch) msg = 'Ingrese un valor válido.';
    else if (v.badInput) msg = 'Ingrese un número válido.';
    c.setCustomValidity(msg);
}, true);
['input', 'change'].forEach((ev) => document.addEventListener(ev, (e) => e.target.setCustomValidity?.(''), true));

// Al volver con errores del servidor, se lleva al usuario al primer campo con error.
document.addEventListener('DOMContentLoaded', () => {
    const campo = document.querySelector('.input-error, .error');
    if (campo) {
        campo.scrollIntoView({ behavior: 'smooth', block: 'center' });
        const control = campo.matches('input, select, textarea') ? campo : campo.parentElement?.querySelector('input, select, textarea');
        setTimeout(() => control?.focus({ preventScroll: true }), 400);
    }
});

window.Alpine = Alpine;
// Borrador de la hoja de servicio en el navegador: los textos escritos no se pierden si se
// recarga la página o se sale por error, y se avisa antes de salir con cambios sin guardar.
// (Las filas dinámicas —equipos, mediciones— y los archivos no se guardan: el navegador no lo permite para archivos.)
function borradorDeFormulario(form) {
    const clave = `sggp:borrador:${form.dataset.borrador}`;
    const campos = () => [...form.elements].filter((el) => el.name && el.type !== 'file' && el.type !== 'hidden'
        && !['_token', '_method'].includes(el.name) && !el.hasAttribute('x-model') && !el.hasAttribute(':name'));
    let modificado = false;
    let enviando = false;
    let espera;

    const leer = () => { try { return JSON.parse(localStorage.getItem(clave) || 'null'); } catch { return null; } };
    const guardar = () => {
        const valores = {};
        campos().forEach((el) => {
            if (el.type === 'radio' || el.type === 'checkbox') { if (el.checked) valores[el.name] = el.value; } else valores[el.name] = el.value;
        });
        try { localStorage.setItem(clave, JSON.stringify({ fecha: new Date().toISOString(), valores })); } catch { /* sin espacio */ }
    };

    // Recuperar lo escrito (si la página no volvió con errores del servidor, que ya trae lo enviado)
    const previo = leer();
    if (previo && form.dataset.conErrores !== '1') {
        campos().forEach((el) => {
            if (!(el.name in previo.valores)) return;
            if (el.type === 'radio' || el.type === 'checkbox') el.checked = previo.valores[el.name] === el.value;
            else el.value = previo.valores[el.name];
        });
        const aviso = document.createElement('div');
        aviso.className = 'flex flex-wrap items-center justify-between gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900';
        aviso.innerHTML = `<span>Se recuperó lo que había escrito el ${new Date(previo.fecha).toLocaleString('es-PE')} (borrador del navegador).</span>`;
        const descartar = document.createElement('button');
        descartar.type = 'button';
        descartar.className = 'font-medium text-red-700 hover:underline';
        descartar.textContent = 'Descartar y empezar de nuevo';
        descartar.onclick = () => { localStorage.removeItem(clave); modificado = false; location.reload(); };
        aviso.append(descartar);
        form.prepend(aviso);
    }

    const alCambiar = () => { modificado = true; clearTimeout(espera); espera = setTimeout(guardar, 600); };
    form.addEventListener('input', alCambiar);
    form.addEventListener('change', alCambiar);
    form.addEventListener('submit', () => { enviando = true; guardar(); });
    window.addEventListener('beforeunload', (e) => {
        if (modificado && !enviando) { guardar(); e.preventDefault(); e.returnValue = ''; }
    });
}
document.querySelectorAll('form[data-borrador]').forEach(borradorDeFormulario);
// Hoja guardada con éxito: se borra su borrador
document.querySelectorAll('[data-limpiar-borrador]').forEach((el) => localStorage.removeItem(`sggp:borrador:${el.dataset.limpiarBorrador}`));

Alpine.start();
