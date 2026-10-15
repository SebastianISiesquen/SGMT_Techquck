export interface Usuario {
  id: number;
  nombre: string;
  email: string;
  cargo: string | null;
  telefono: string | null;
  rol: string;
  iniciales: string;
  no_leidas: number;
}

export interface Archivo {
  id: number;
  nombre: string;
  categoria: string;
  categoria_label: string;
  descripcion: string | null;
  mime: string;
  es_imagen: boolean;
  tamano: string;
  url: string;
  fecha: string;
}

export interface Atencion {
  id: number;
  tecnico: string | null;
  fecha_inicio: string;
  fecha_fin: string | null;
  diagnostico: string;
  actividades: string;
  observaciones: string | null;
  resultado: string;
  resultado_label: string;
  archivos: Archivo[];
}

export interface Incidencia {
  id: number;
  codigo: string;
  titulo: string;
  descripcion: string;
  estado: string;
  estado_label: string;
  prioridad: string;
  prioridad_label: string;
  canal: string | null;
  reportado_por: string | null;
  ubicacion_detalle: string | null;
  fecha_reporte: string;
  fecha_limite: string | null;
  fecha_asignacion: string | null;
  sla_vencido: boolean;
  cobertura: string;
  cobertura_label: string;
  puede_atender: boolean;
  proyecto: {
    id: number;
    codigo: string;
    nombre: string;
    ubicacion: string | null;
    entidad: string;
    /** Si es un subcontrato: quién contrató a la empresa (el técnico va al cliente final). */
    contratante: string | null;
    contacto: string | null;
    contacto_telefono: string | null;
  };
  equipo?: {
    id: number;
    codigo: string;
    categoria: string;
    nombre: string;
    ubicacion: string | null;
    responsable: string | null;
    estado_label: string;
    antecedentes: { fecha: string; tipo_label: string; titulo: string; detalle: string | null }[];
  } | null;
  hojas?: { id: number; numero: string; estado: string; estado_label: string }[];
  garantia?: {
    codigo: string;
    tipo: string;
    cobertura: string | null;
    condiciones: string | null;
    fecha_vencimiento: string;
    estado: string;
    estado_label: string;
  } | null;
  atenciones?: Atencion[];
  archivos?: Archivo[];
  historial?: { accion: string; detalle: string | null; estado_nuevo: string | null; usuario: string | null; fecha: string }[];
}

export interface Resumen {
  asignadas: number;
  en_proceso: number;
  resueltas_mes: number;
  criticas: number;
}

export interface Notificacion {
  id: string;
  titulo: string;
  mensaje: string;
  incidencia_id: number | null;
  leida: boolean;
  fecha: string;
}

export interface HojaServicio {
  id: number;
  numero: string;
  tipo_servicio: string;
  tipo_servicio_label: string;
  estado: string;
  estado_label: string;
  fecha: string;
  hora_llegada: string;
  hora_salida: string | null;
  diagnostico: string | null;
  resultado: string | null;
  resultado_label: string | null;
  trabajo_realizado: string;
  observaciones: string | null;
  recomendaciones: string | null;
  conforme: boolean;
  receptor_nombre: string | null;
  receptor_dni: string | null;
  receptor_cargo: string | null;
  fecha_firma: string | null;
  puede_firmar: boolean;
  proyecto: { id: number; nombre: string; entidad: string };
  mantenimiento?: string | null;
  incidencia?: { id: number; codigo: string } | null;
  tecnico?: string | null;
  equipos?: { equipo_id: number | null; equipo: string; marca: string | null; modelo: string | null; serie: string | null; accion: string; estado_final: string; observacion: string | null }[];
  materiales?: { descripcion: string; cantidad: number; unidad: string }[];
  archivos?: Archivo[];
  pdf_url: string;
}

export interface ProyectoOpcion {
  id: number;
  codigo: string;
  nombre: string;
  entidad: string;
  garantias: { id: number; texto: string }[];
}

export interface EquipoEvento {
  id: number;
  tipo: string;
  tipo_label: string;
  color: string;
  fecha: string;
  titulo: string;
  detalle: string | null;
  componente: string | null;
  usuario: string | null;
  incidencia: string | null;
  hoja: string | null;
}

export interface Equipo {
  id: number;
  codigo: string;
  categoria: string;
  marca: string | null;
  modelo: string | null;
  serie: string | null;
  nombre: string;
  descripcion: string | null;
  ubicacion: string | null;
  area: string | null;
  responsable: string | null;
  fecha_instalacion: string | null;
  estado: string;
  estado_label: string;
  cliente: string;
  proyecto: { id: number; codigo: string; nombre: string } | null;
  garantia?: { codigo: string; tipo: string; fecha_vencimiento: string; dias_restantes: number } | null;
  eventos?: EquipoEvento[];
}

export interface Mantenimiento {
  id: number;
  etiqueta: string;
  plan: string;
  alcance: string | null;
  fecha_programada: string;
  dias_restantes: number;
  atrasado: boolean;
  proyecto: { id: number; codigo: string; nombre: string };
  cliente: string;
}

export interface ArticuloAlmacen {
  id: number;
  codigo: string;
  nombre: string;
  unidad: string;
  stock: number;
  tipo: string;
}

export interface PuntoChecklist {
  item: string;
  resultado: string;
  nota: string;
}
