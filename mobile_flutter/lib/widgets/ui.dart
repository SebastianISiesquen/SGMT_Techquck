import 'package:flutter/material.dart';

import '../core/api.dart';
import '../core/modelos.dart';
import '../core/tema.dart';

/// Insignia de estado con punto de color (como en la web).
class Insignia extends StatelessWidget {
  final String texto;
  final String? tono;
  const Insignia(this.texto, {super.key, this.tono});

  @override
  Widget build(BuildContext context) {
    final t = tonos[tono] ?? tonos['slate']!;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: t.bg, borderRadius: BorderRadius.circular(999)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 6,
            height: 6,
            decoration: BoxDecoration(color: t.fg, shape: BoxShape.circle),
          ),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              texto,
              style: TextStyle(color: t.fg, fontSize: 12, fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
    );
  }
}

/// Tarjeta blanca con título opcional en mayúsculas.
class Tarjeta extends StatelessWidget {
  final String? titulo;
  final List<Widget> children;
  final Widget? accion;
  const Tarjeta({super.key, this.titulo, required this.children, this.accion});

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: C.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (titulo != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      titulo!.toUpperCase(),
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: C.textMuted, letterSpacing: 0.5),
                    ),
                  ),
                  ?accion,
                ],
              ),
            ),
          ...children,
        ],
      ),
    );
  }
}

enum Variante { primary, secondary, success, danger }

class Boton extends StatelessWidget {
  final String texto;
  final VoidCallback? onPressed;
  final Variante variante;
  final bool cargando;
  final IconData? icono;
  const Boton(this.texto, {super.key, this.onPressed, this.variante = Variante.primary, this.cargando = false, this.icono});

  @override
  Widget build(BuildContext context) {
    final fondo = switch (variante) {
      Variante.primary => C.brand,
      Variante.secondary => Colors.white,
      Variante.success => C.success,
      Variante.danger => C.danger,
    };
    final fg = variante == Variante.secondary ? C.text : Colors.white;
    return SizedBox(
      width: double.infinity,
      height: 48,
      child: FilledButton(
        onPressed: cargando ? null : onPressed,
        style: FilledButton.styleFrom(
          backgroundColor: fondo,
          foregroundColor: fg,
          disabledBackgroundColor: fondo.withValues(alpha: 0.5),
          disabledForegroundColor: fg.withValues(alpha: 0.8),
          padding: const EdgeInsets.symmetric(horizontal: 12),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
            side: variante == Variante.secondary ? const BorderSide(color: C.border) : BorderSide.none,
          ),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
        ),
        child: cargando
            ? SizedBox(width: 22, height: 22, child: CircularProgressIndicator(strokeWidth: 2.5, color: fg))
            : Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  if (icono != null) ...[Icon(icono, size: 19), const SizedBox(width: 8)],
                  Flexible(
                    child: Text(texto, textAlign: TextAlign.center, overflow: TextOverflow.ellipsis),
                  ),
                ],
              ),
      ),
    );
  }
}

/// Etiqueta + campo de texto.
class Campo extends StatelessWidget {
  final String label;
  final TextEditingController? controller;
  final bool requerido, oculto, multilinea;
  final String? hint;
  final TextInputType? teclado;
  final ValueChanged<String>? onSubmitted;
  final TextInputAction? accion;
  final Widget? sufijo;
  const Campo(
    this.label, {
    super.key,
    this.controller,
    this.requerido = false,
    this.oculto = false,
    this.multilinea = false,
    this.hint,
    this.teclado,
    this.onSubmitted,
    this.accion,
    this.sufijo,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text.rich(
            TextSpan(
              text: label,
              style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w600, color: C.text),
              children: [
                if (requerido)
                  const TextSpan(
                    text: ' *',
                    style: TextStyle(color: C.danger),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 6),
          TextField(
            controller: controller,
            obscureText: oculto,
            keyboardType: multilinea ? TextInputType.multiline : teclado,
            minLines: multilinea ? 3 : 1,
            maxLines: multilinea ? 8 : 1,
            onSubmitted: onSubmitted,
            textInputAction: accion,
            decoration: InputDecoration(hintText: hint, suffixIcon: sufijo),
          ),
        ],
      ),
    );
  }
}

/// Píldora seleccionable (filtros y opciones).
class Opcion extends StatelessWidget {
  final String texto;
  final bool activo;
  final VoidCallback onTap;
  final Color? colorActivo;
  const Opcion(this.texto, {super.key, required this.activo, required this.onTap, this.colorActivo});

  @override
  Widget build(BuildContext context) {
    final c = colorActivo ?? C.brand;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(999),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: activo ? c : Colors.white,
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: activo ? c : C.border),
        ),
        child: Text(
          texto,
          style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: activo ? Colors.white : C.textMuted),
        ),
      ),
    );
  }
}

/// Dato con etiqueta pequeña arriba.
class Fila extends StatelessWidget {
  final String label;
  final String? valor;
  final VoidCallback? onTap;
  const Fila(this.label, this.valor, {super.key, this.onTap});

  @override
  Widget build(BuildContext context) {
    final contenido = Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600, color: C.textMuted, letterSpacing: 0.4),
          ),
          const SizedBox(height: 2),
          Text(
            (valor == null || valor!.isEmpty) ? '—' : valor!,
            style: TextStyle(fontSize: 15, color: onTap != null ? C.brand : C.text, fontWeight: onTap != null ? FontWeight.w600 : FontWeight.normal),
          ),
        ],
      ),
    );
    return onTap == null ? contenido : InkWell(onTap: onTap, child: contenido);
  }
}

/// Recuadro de aviso (ámbar, azul, rojo o verde).
class Aviso extends StatelessWidget {
  final List<Widget> children;
  final String tono;
  const Aviso({super.key, required this.children, this.tono = 'amber'});

  @override
  Widget build(BuildContext context) {
    final t = tonos[tono]!;
    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: t.bg,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: t.fg.withValues(alpha: 0.3)),
      ),
      child: DefaultTextStyle.merge(
        style: TextStyle(color: t.fg),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: children),
      ),
    );
  }
}

class TextoError extends StatelessWidget {
  final String mensaje;
  const TextoError(this.mensaje, {super.key});

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    margin: const EdgeInsets.only(bottom: 12),
    padding: const EdgeInsets.all(10),
    decoration: BoxDecoration(color: const Color(0xFFFEF2F2), borderRadius: BorderRadius.circular(8)),
    child: Text(mensaje, style: const TextStyle(color: C.danger, fontSize: 13)),
  );
}

class Vacio extends StatelessWidget {
  final String texto;
  const Vacio(this.texto, {super.key});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 36, horizontal: 12),
    child: Text(
      texto,
      textAlign: TextAlign.center,
      style: const TextStyle(color: C.textMuted, height: 1.4),
    ),
  );
}

class Cargando extends StatelessWidget {
  const Cargando({super.key});

  @override
  Widget build(BuildContext context) => const Center(child: CircularProgressIndicator(color: C.brand));
}

/// Tarjeta de lista tocable.
class ItemLista extends StatelessWidget {
  final List<Widget> children;
  final VoidCallback? onTap;
  const ItemLista({super.key, required this.children, this.onTap});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 10),
    child: Material(
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(14),
        side: const BorderSide(color: C.border),
      ),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: children),
        ),
      ),
    ),
  );
}

/// Línea con ícono y texto gris.
class Meta extends StatelessWidget {
  final IconData icono;
  final String texto;
  final Color? color;
  final bool negrita;
  const Meta(this.icono, this.texto, {super.key, this.color, this.negrita = false});

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(top: 3),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icono, size: 15, color: color ?? C.textMuted),
        const SizedBox(width: 6),
        Expanded(
          child: Text(
            texto,
            style: TextStyle(fontSize: 13, color: color ?? C.textMuted, fontWeight: negrita ? FontWeight.w700 : FontWeight.normal),
          ),
        ),
      ],
    ),
  );
}

/// Miniaturas de fotos y chips de documentos del servidor (con el token para verlas).
class Galeria extends StatelessWidget {
  final List<Archivo> archivos;
  final double lado;
  const Galeria(this.archivos, {super.key, this.lado = 92});

  @override
  Widget build(BuildContext context) {
    if (archivos.isEmpty) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(top: 4),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        children: [
          for (final f in archivos)
            if (f.esImagen)
              GestureDetector(
                onTap: () => verImagen(context, f),
                child: SizedBox(
                  width: lado,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: Image.network(
                          f.url,
                          headers: Api.i.authHeaders,
                          width: lado,
                          height: lado,
                          fit: BoxFit.cover,
                          errorBuilder: (_, _, _) => Container(
                            width: lado,
                            height: lado,
                            color: C.bg,
                            child: const Icon(Icons.broken_image, color: C.textMuted),
                          ),
                        ),
                      ),
                      if (f.descripcion != null && f.descripcion!.isNotEmpty)
                        Text(
                          f.descripcion!,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(fontSize: 11, color: C.textMuted),
                        ),
                    ],
                  ),
                ),
              )
            else
              Container(
                width: 140,
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  border: Border.all(color: C.border),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      f.categoriaLabel,
                      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700, color: f.categoria == 'acta_conformidad' ? C.success : C.text),
                    ),
                    Text(
                      f.nombre,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontSize: 11, color: C.textMuted),
                    ),
                  ],
                ),
              ),
        ],
      ),
    );
  }
}

void verImagen(BuildContext context, Archivo f) {
  showDialog<void>(
    context: context,
    builder: (_) => Dialog(
      insetPadding: const EdgeInsets.all(12),
      backgroundColor: Colors.black,
      child: Stack(
        children: [
          InteractiveViewer(
            child: Image.network(f.url, headers: Api.i.authHeaders, fit: BoxFit.contain),
          ),
          Positioned(
            right: 4,
            top: 4,
            child: IconButton(
              onPressed: () => Navigator.pop(context),
              icon: const Icon(Icons.close, color: Colors.white),
            ),
          ),
        ],
      ),
    ),
  );
}

Future<void> mensaje(BuildContext context, String titulo, String texto) => showDialog<void>(
  context: context,
  builder: (c) => AlertDialog(
    title: Text(titulo),
    content: Text(texto),
    actions: [TextButton(onPressed: () => Navigator.pop(c), child: const Text('Aceptar'))],
  ),
);
