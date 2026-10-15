<?php

namespace Database\Seeders;

use App\Models\Checklist;
use Illuminate\Database\Seeder;

/** Puntos de control iniciales del mantenimiento preventivo (se editan en Equipos → Checklists). No pisa los ya definidos. */
class ChecklistsSeeder extends Seeder
{
    private const LISTAS = [
        'Laptop' => ['Limpieza interna y del ventilador', 'Cambio de pasta térmica', 'Estado del disco (SMART / sectores reasignados)', 'Estado de la batería', 'Memoria RAM (prueba)', 'Teclado, pantalla y bisagras', 'Actualizaciones del sistema', 'Antivirus actualizado', 'Respaldo de información del usuario'],
        'PC de escritorio' => ['Limpieza interna y de ventiladores', 'Cambio de pasta térmica', 'Estado del disco (SMART)', 'Memoria RAM (prueba)', 'Fuente de poder', 'Cables y periféricos', 'Actualizaciones del sistema', 'Antivirus actualizado'],
        'Servidor' => ['Temperatura y ventiladores', 'Estado del arreglo RAID y discos', 'Fuentes redundantes', 'Logs de hardware (iDRAC / iLO)', 'Respaldos verificados', 'Actualizaciones de firmware y sistema', 'Espacio en disco', 'Limpieza de polvo'],
        'Cámara IP fija' => ['Imagen y enfoque', 'Visión nocturna (IR)', 'Limpieza de domo / lente', 'Conector y cable de red', 'Alimentación PoE', 'Grabación en el NVR', 'Fecha y hora sincronizadas', 'Sujeción del soporte'],
        'Cámara PTZ' => ['Imagen y enfoque', 'Movimiento pan / tilt / zoom', 'Presets y rondas', 'Visión nocturna (IR)', 'Limpieza de domo', 'Alimentación y protector eléctrico', 'Grabación en el NVR', 'Sujeción y hermeticidad'],
        'NVR / DVR' => ['Estado de los discos de grabación', 'Días de grabación disponibles', 'Canales en línea', 'Temperatura y ventilador', 'Firmware', 'Acceso remoto', 'Fecha y hora'],
        'Switch' => ['Puertos y enlaces activos', 'Consumo PoE', 'Temperatura', 'Firmware', 'Configuración respaldada', 'Ordenamiento y etiquetado de cables'],
        'UPS' => ['Estado de baterías', 'Prueba de autonomía', 'Carga conectada (%)', 'Alarmas', 'Limpieza'],
        'Radioenlace' => ['Nivel de señal (dBm)', 'Alineamiento', 'Capacidad del enlace', 'Protector de descargas', 'Conectores y hermeticidad', 'Firmware'],
        'Impresora' => ['Limpieza de cabezales / rodillos', 'Niveles de tinta o tóner', 'Prueba de impresión', 'Conexión de red'],
    ];

    public function run(): void
    {
        foreach (self::LISTAS as $categoria => $items) {
            Checklist::firstOrCreate(['categoria' => $categoria], ['items' => implode("\n", $items)]);
        }
    }
}
