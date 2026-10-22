<?php

return [

    /*
    | Módulos y permisos. El Administrador tiene todos; para Supervisor y
    | Técnico se asignan por rol y pueden ampliarse por usuario.
    */
    'permisos' => [
        'Panel' => [
            'dashboard.ver' => 'Ver dashboard e indicadores',
        ],
        'Clientes' => [
            'entidades.ver' => 'Consultar clientes (públicos y privados)',
            'entidades.gestionar' => 'Registrar / editar clientes',
        ],
        'Proyectos y servicios' => [
            'proyectos.ver' => 'Consultar proyectos y servicios',
            'proyectos.gestionar' => 'Registrar / editar proyectos, servicios y sus documentos',
        ],
        'Garantías' => [
            'garantias.ver' => 'Consultar garantías',
            'garantias.gestionar' => 'Registrar / editar garantías y emitir cartas de garantía',
        ],
        'Equipos' => [
            'equipos.ver' => 'Consultar equipos y su historial',
            'equipos.gestionar' => 'Editar equipos, importar inventario, registrar eventos y definir los checklists de mantenimiento',
        ],
        'Mantenimientos' => [
            'mantenimientos.ver' => 'Consultar mantenimientos programados',
            'mantenimientos.gestionar' => 'Programar planes de mantenimiento',
        ],
        'Incidencias' => [
            'incidencias.ver' => 'Consultar todas las incidencias',
            'incidencias.gestionar' => 'Registrar / editar incidencias',
            'incidencias.asignar' => 'Asignar incidencias a técnicos',
            'incidencias.cerrar' => 'Cerrar / anular incidencias',
        ],
        'Atención técnica' => [
            'atenciones.registrar' => 'Atender incidencias asignadas (diagnóstico, evidencias, actas)',
        ],
        'Almacén' => [
            'almacen.ver' => 'Consultar stock y movimientos del almacén',
            'almacen.gestionar' => 'Registrar artículos, entradas, salidas y ajustes de inventario',
        ],
        'Cotizaciones' => [
            'cotizaciones.ver' => 'Consultar cotizaciones',
            'cotizaciones.gestionar' => 'Elaborar cotizaciones',
        ],
        'Hojas de servicio' => [
            'hojas.ver' => 'Consultar todas las hojas de servicio',
            'hojas.registrar' => 'Registrar hojas de servicio en campo (con firma)',
            'hojas.anular' => 'Anular hojas de servicio',
        ],
        'Reportes' => [
            'reportes.ver' => 'Generar y exportar reportes (PDF / Excel)',
        ],
        'Administración' => [
            'usuarios.gestionar' => 'Gestionar usuarios, roles y permisos',
            'auditoria.ver' => 'Consultar auditoría del sistema',
            'configuracion.gestionar' => 'Configuración general y catálogos',
        ],
    ],

    'roles' => [
        'Administrador' => '*',
        'Supervisor' => [
            'dashboard.ver',
            'entidades.ver', 'entidades.gestionar',
            'proyectos.ver', 'proyectos.gestionar',
            'garantias.ver', 'garantias.gestionar',
            'equipos.ver', 'equipos.gestionar',
            'mantenimientos.ver', 'mantenimientos.gestionar',
            'cotizaciones.ver', 'cotizaciones.gestionar',
            'almacen.ver', 'almacen.gestionar',
            'incidencias.ver', 'incidencias.gestionar', 'incidencias.asignar', 'incidencias.cerrar',
            'hojas.ver', 'hojas.registrar', 'hojas.anular',
            'reportes.ver',
        ],
        'Técnico' => [
            'atenciones.registrar',
            'hojas.registrar',
            'equipos.ver',
            'mantenimientos.ver',
        ],
    ],

    /*
    | Valores por defecto de la configuración editable (tabla configuraciones).
    */
    'ajustes' => [
        'empresa_nombre' => 'TECHQUK S.A.C.',
        'empresa_ruc' => '20487913791',
        'empresa_direccion' => 'Cal. Los Sauces Nro. 787 Urb. Santa Victoria - Chiclayo',
        'empresa_telefono' => '',
        'empresa_email' => '',
        'empresa_representante' => '',
        'empresa_cargo_representante' => 'Gerente General',
        'empresa_logo' => '',
        // Dirección pública del sistema (la que abre el QR del sticker). Vacía = APP_URL del .env.
        'url_publica' => '',
        // Correo saliente (SMTP). Vacío = se usa la configuración MAIL_* del .env.
        'correo_host' => '',
        'correo_puerto' => 587,
        'correo_cifrado' => 'tls',
        'correo_usuario' => '',
        'correo_clave' => '',
        'correo_remitente' => '',
        'correo_nombre' => '',
        // Hoja de servicio: verificaciones de seguridad antes de empezar y recomendaciones al pie
        'verificaciones_previas' => "Cuenta con EPP en perfecto estado\nInstrumentos y herramientas en buenas condiciones\nAutorización del encargado para iniciar el servicio\nCondiciones inseguras y riesgos de la zona de trabajo identificados\nZona de trabajo y equipos señalizados",
        'recomendaciones_generales' => "Mantener el área de los equipos limpia, despejada y ventilada.\nNo colocar objetos sobre los equipos ni obstruir su ventilación.\nToda manipulación de los equipos debe ser realizada por personal técnico calificado.\nAnte cualquier falla, comunicarse con el área de soporte indicando el código del sticker del equipo.",
        'dias_alerta_garantia' => 60,
        'avisos_garantia' => '60,30,15,7',
        'avisar_cliente_garantia' => 1,
        'dias_alerta_mantenimiento' => 7,
        // Hora (24 h) de los envíos automáticos: alertas diarias e informe mensual (día 1)
        'hora_alertas' => '07:00',
        'hora_informes' => '07:30',
        'igv' => 18,
        'anios_historial' => 5,
        'dias_incidencia_sin_atender' => 3,
        'requerir_acta_cierre' => 1,
        'max_archivo_mb' => 20,
        'sectores' => "Gobierno nacional / Ministerios\nGobierno regional\nGobierno local / Municipalidades\nSalud\nEducación\nSeguridad / Policía\nMinería\nEnergía e hidrocarburos\nTelecomunicaciones\nBanca y finanzas\nComercio / Retail\nIndustria / Manufactura\nAgroindustria\nTransporte y logística\nConstrucción / Inmobiliaria\nServicios\nOtro",
        'tipos_proyecto' => "Videovigilancia\nRedes y cableado estructurado\nServidores y centro de datos\nTelecomunicaciones / Radioenlaces\nSeguridad electrónica\nSoftware\nOtro",
        'tipos_servicio' => "Mantenimiento preventivo\nMantenimiento preventivo y correctivo\nSoporte técnico\nSoporte técnico y mesa de ayuda\nMonitoreo de sistemas\nOutsourcing de TI\nOtro",
        'tipos_garantia' => "Garantía de equipos\nGarantía de instalación\nGarantía del servicio\nGarantía de software / licencias\nOtro",
        'categorias_equipo' => "Cámara IP fija\nCámara PTZ\nNVR / DVR\nServidor\nPC de escritorio\nLaptop\nImpresora\nSwitch\nRouter\nFirewall\nAccess point\nRadioenlace\nUPS\nMonitor / Videowall\nControl de acceso\nOtro",
        'condiciones_cotizacion' => "Forma de pago: 50% adelanto, 50% a la conformidad.\nPlazo de ejecución: a coordinar con el cliente.\nLos precios incluyen mano de obra y traslado dentro de la ciudad.",
        'canales_reporte' => "Teléfono\nCorreo electrónico\nOficio\nWhatsApp\nVisita técnica\nOtro",
    ],

    'extensiones_permitidas' => ['jpg', 'jpeg', 'png', 'webp', 'heic', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'],

];
