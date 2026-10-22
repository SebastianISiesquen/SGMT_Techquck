import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

/// Colores de la app (los mismos de la plataforma web).
class C {
  static const brand = Color(0xFF1F63C0);
  static const brandDark = Color(0xFF0B1D3A);
  static const brandSoft = Color(0xFFEFF5FF);
  static const bg = Color(0xFFF1F5F9);
  static const border = Color(0xFFE2E8F0);
  static const input = Color(0xFFCBD5E1);
  static const text = Color(0xFF0F172A);
  static const textMuted = Color(0xFF64748B);
  static const placeholder = Color(0xFF94A3B8);
  static const danger = Color(0xFFDC2626);
  static const success = Color(0xFF059669);
  static const amberBg = Color(0xFFFFFBEB);
  static const amberFg = Color(0xFF92400E);
  static const blueBg = Color(0xFFEFF6FF);
  static const blueFg = Color(0xFF1E3A8A);
}

/// Par fondo / texto de las insignias de estado.
class Tono {
  final Color bg;
  final Color fg;
  const Tono(this.bg, this.fg);
}

const tonos = <String, Tono>{
  'slate': Tono(Color(0xFFF1F5F9), Color(0xFF334155)),
  'blue': Tono(Color(0xFFEFF6FF), Color(0xFF1D4ED8)),
  'green': Tono(Color(0xFFECFDF5), Color(0xFF047857)),
  'amber': Tono(Color(0xFFFFFBEB), Color(0xFF92400E)),
  'red': Tono(Color(0xFFFEF2F2), Color(0xFFB91C1C)),
  'violet': Tono(Color(0xFFF5F3FF), Color(0xFF6D28D9)),
};

const colorEstado = <String, String>{
  'registrada': 'slate',
  'asignada': 'blue',
  'en_proceso': 'amber',
  'resuelta': 'violet',
  'cerrada': 'green',
  'anulada': 'red',
  'vigente': 'green',
  'por_vencer': 'amber',
  'vencida': 'red',
  'resuelto': 'green',
  'parcial': 'amber',
  'pendiente_repuesto': 'blue',
  'no_procede': 'red',
  'conforme': 'green',
  // Equipos
  'operativo': 'green',
  'observado': 'amber',
  'con_falla': 'red',
  'en_reparacion': 'blue',
  'inoperativo': 'red',
  'de_baja': 'slate',
};

const colorPrioridad = <String, String>{'baja': 'slate', 'media': 'blue', 'alta': 'amber', 'critica': 'red'};

String dos(int n) => n.toString().padLeft(2, '0');

/// dd/mm/aaaa hh:mm en hora local.
String fecha(String? iso, {bool hora = true}) {
  if (iso == null || iso.isEmpty) return '—';
  final d = DateTime.tryParse(iso)?.toLocal();
  if (d == null) return iso;
  final base = '${dos(d.day)}/${dos(d.month)}/${d.year}';
  return hora ? '$base ${dos(d.hour)}:${dos(d.minute)}' : base;
}

/// aaaa-mm-dd, el formato que espera Laravel.
String fechaIso(DateTime d) => '${d.year}-${dos(d.month)}-${dos(d.day)}';

String horaDe(TimeOfDay t) => '${dos(t.hour)}:${dos(t.minute)}';

ThemeData temaApp() {
  final base = ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(seedColor: C.brand, primary: C.brand, surface: Colors.white),
    scaffoldBackgroundColor: C.bg,
  );
  return base.copyWith(
    appBarTheme: const AppBarTheme(
      backgroundColor: C.brandDark,
      foregroundColor: Colors.white,
      elevation: 0,
      systemOverlayStyle: SystemUiOverlayStyle.light,
      titleTextStyle: TextStyle(color: Colors.white, fontSize: 18, fontWeight: FontWeight.w700),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: Colors.white,
      isDense: true,
      contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 13),
      hintStyle: const TextStyle(color: C.placeholder),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: C.input),
      ),
      enabledBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: C.input),
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(10),
        borderSide: const BorderSide(color: C.brand, width: 1.5),
      ),
    ),
    snackBarTheme: const SnackBarThemeData(behavior: SnackBarBehavior.floating),
  );
}
