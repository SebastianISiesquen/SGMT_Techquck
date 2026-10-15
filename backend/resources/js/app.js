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
Alpine.data('opcionesDeProyecto', (urlBase, garantiaInicial, equipoInicial = '', coberturaInicial = '') => ({
    proyecto: '',
    garantia: garantiaInicial || '',
    equipo: equipoInicial || '',
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
        const equipo = (d.equipos || []).find((o) => String(o.id) === String(this.equipo));
        this.garantias = d.garantias;
        this.equipos = d.equipos || [];
        this.contratoVigente = !!d.contrato_vigente;
        this.cargando = false;
        this.garantia = '';
        this.equipo = '';
        // Se asigna después de que Alpine dibuje las opciones, para que el select la muestre.
        this.$nextTick(() => {
            this.garantia = elegida ? String(elegida.id) : '';
            this.equipo = equipo ? String(equipo.id) : '';
            this.sugerirCobertura();
        });
    },
    // Si el equipo tiene una garantía propia, se usa esa.
    alElegirEquipo() {
        const e = this.equipos.find((o) => String(o.id) === String(this.equipo));
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
Alpine.data('filasEquipos', (iniciales, vacia, checklists) => ({
    filas: iniciales && iniciales.length ? iniciales : [],
    init() {
        this.filas.forEach((_, i) => this.sincronizar(i));
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

// Pad de firma manuscrita (mouse, dedo o lápiz). Guarda un PNG en base64 en un input oculto.
Alpine.data('firmaPad', () => ({
    vacio: true,
    dibujando: false,
    ctx: null,
    init() {
        const c = this.$refs.canvas;
        const ajustar = () => {
            const ratio = window.devicePixelRatio || 1;
            const ancho = c.offsetWidth;
            c.width = ancho * ratio;
            c.height = 180 * ratio;
            this.ctx = c.getContext('2d');
            this.ctx.scale(ratio, ratio);
            this.ctx.lineWidth = 2.2;
            this.ctx.lineCap = 'round';
            this.ctx.lineJoin = 'round';
            this.ctx.strokeStyle = '#0f172a';
            this.limpiar();
        };
        ajustar();
        c.addEventListener('pointerdown', (e) => {
            this.dibujando = true;
            c.setPointerCapture(e.pointerId);
            this.ctx.beginPath();
            this.ctx.moveTo(e.offsetX, e.offsetY);
        });
        c.addEventListener('pointermove', (e) => {
            if (!this.dibujando) return;
            this.ctx.lineTo(e.offsetX, e.offsetY);
            this.ctx.stroke();
            this.vacio = false;
        });
        const terminar = () => {
            if (!this.dibujando) return;
            this.dibujando = false;
            if (!this.vacio) this.$refs.valor.value = c.toDataURL('image/png');
        };
        c.addEventListener('pointerup', terminar);
        c.addEventListener('pointerleave', terminar);
    },
    limpiar() {
        const c = this.$refs.canvas;
        this.ctx.save();
        this.ctx.setTransform(1, 0, 0, 1, 0, 0);
        this.ctx.fillStyle = '#ffffff';
        this.ctx.fillRect(0, 0, c.width, c.height);
        this.ctx.restore();
        this.vacio = true;
        this.$refs.valor.value = '';
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
Alpine.start();
