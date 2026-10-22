{{-- Estilo común de los documentos PDF: encabezado con logo, secciones con barra de título y tablas sobrias. --}}
<style>
    @page { margin: 138px 30px 46px 30px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.6px; color: #1f2937; line-height: 1.35; }

    /* Encabezado fijo en cada página */
    .cabecera { position: fixed; top: -120px; left: 0; right: 0; height: 108px; }
    .cabecera table { width: 100%; border-collapse: collapse; }
    .cabecera td { vertical-align: middle; padding: 0; border: none; }
    .cabecera .logo { width: 74px; }
    .cabecera .logo img { max-width: 68px; max-height: 60px; }
    .cabecera .iniciales { width: 58px; background: #0b1d3a; color: #fff; font-size: 21px; font-weight: bold; text-align: center; padding: 16px 0 17px; border-radius: 6px; letter-spacing: 1px; }
    .cabecera .empresa { font-size: 12.5px; font-weight: bold; color: #0b1d3a; letter-spacing: .2px; }
    .cabecera .datos-empresa { font-size: 7.4px; color: #4b5563; margin-top: 2px; line-height: 1.45; }
    .cabecera .caja-titulo { width: 205px; border: 1.2px solid #0b1d3a; }
    .cabecera .caja-titulo .tit { background: #0b1d3a; color: #fff; text-align: center; font-weight: bold; font-size: 9.5px; padding: 4px 3px; letter-spacing: .4px; }
    .cabecera .caja-titulo .num { text-align: center; font-size: 13px; font-weight: bold; color: #1f63c0; padding: 3px 0 2px; }
    .cabecera .caja-titulo table td { font-size: 7.2px; padding: 1px 6px; color: #374151; }
    .cabecera .caja-titulo table td.k { color: #6b7280; width: 46%; }
    .cabecera .linea { height: 3px; background: #1f63c0; margin-top: 7px; }

    /* Pie fijo */
    .pie { position: fixed; bottom: -30px; left: 0; right: 0; height: 18px; font-size: 7px; color: #6b7280; border-top: 1px solid #d1d5db; padding-top: 4px; }
    .pie .pag:after { content: "Página " counter(page); }

    /* Secciones */
    .seccion { margin-top: 10px; }
    .seccion-titulo { background: #0b1d3a; color: #fff; font-weight: bold; font-size: 8.6px; padding: 4px 7px; letter-spacing: .3px; }
    .seccion-titulo .n { display: inline-block; background: #1f63c0; padding: 0 5px; margin-right: 5px; }
    .seccion-cuerpo { border: 1px solid #cbd5e1; border-top: none; padding: 6px 7px; }
    h2 { font-size: 10px; color: #0b1d3a; margin: 12px 0 5px; padding-bottom: 3px; border-bottom: 1.5px solid #1f63c0; }

    /* Tablas */
    table { width: 100%; border-collapse: collapse; }
    th { background: #e8eef7; color: #0b1d3a; font-size: 7.6px; text-align: left; padding: 4px 4px; border: 1px solid #cbd5e1; font-weight: bold; }
    td { border: 1px solid #e2e8f0; padding: 3px 4px; vertical-align: top; }
    tbody tr:nth-child(even) td { background: #f8fafc; }
    td.num, th.num { text-align: right; }
    td.centro, th.centro { text-align: center; }
    .datos td { border: 1px solid #cbd5e1; padding: 3px 6px; }
    .datos td.k { background: #f1f5f9; color: #334155; font-weight: bold; width: 17%; white-space: nowrap; }
    .kv td { border: none; padding: 2px 4px; background: none !important; }
    .kv td.k { color: #6b7280; width: 26%; }
    .sin-borde td { border: none; background: none !important; }

    /* Utilidades */
    .caja { border: 1px solid #cbd5e1; padding: 6px 7px; margin-bottom: 6px; }
    .pre { white-space: pre-line; }
    .muted { color: #6b7280; }
    .chico { font-size: 7.4px; }
    .ok { color: #047857; font-weight: bold; }
    .obs { color: #b45309; font-weight: bold; }
    .mal { color: #b91c1c; font-weight: bold; }
    .etiqueta { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7.4px; font-weight: bold; }
    .e-verde { background: #d1fae5; color: #065f46; }
    .e-ambar { background: #fef3c7; color: #92400e; }
    .e-rojo { background: #fee2e2; color: #991b1b; }
    .e-azul { background: #dbeafe; color: #1e40af; }
    .e-gris { background: #e5e7eb; color: #374151; }
    .evitar-corte { page-break-inside: avoid; }
    .salto { page-break-before: always; }
    .estado-vigente { color: #047857; font-weight: bold; }
    .estado-por_vencer { color: #b45309; font-weight: bold; }
    .estado-vencida { color: #b91c1c; font-weight: bold; }
    .filtros { margin: 0 0 8px; font-size: 8px; color: #4b5563; }
    .sello-anulado { position: fixed; top: 260px; left: 120px; font-size: 64px; font-weight: bold; color: rgba(220, 38, 38, .16); transform: rotate(-24deg); }

    /* Registro fotográfico: recuadros fijos, la foto centrada sin deformarse */
    .fotos { width: 100%; border-collapse: collapse; }
    .fotos td { width: 50%; border: none; padding: 0 0 10px 0; background: none !important; vertical-align: top; }
    .fotos td.izq { padding-right: 6px; }
    .fotos td.der { padding-left: 6px; }
    .foto-caja { border: 1px solid #cbd5e1; text-align: center; background: #f8fafc; }
    .foto-leyenda { background: #e8eef7; color: #0b1d3a; font-size: 7.4px; font-weight: bold; text-align: center; padding: 3px 4px; border: 1px solid #cbd5e1; border-bottom: none; }

    /* Firmas impresas (el acta se firma a mano y se sube escaneada) */
    .firmas td { border: none; background: none !important; vertical-align: bottom; width: 50%; padding: 0 14px; }
    .firma-linea { border-top: 1px solid #111827; padding-top: 3px; text-align: center; font-size: 7.6px; }
</style>
