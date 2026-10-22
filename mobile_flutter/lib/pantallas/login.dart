import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../core/api.dart';
import '../core/sesion.dart';
import '../core/tema.dart';
import '../widgets/ui.dart';

/// Inicio de sesión del técnico. Solo el personal técnico puede usar la app.
class LoginPantalla extends StatefulWidget {
  const LoginPantalla({super.key});

  @override
  State<LoginPantalla> createState() => _LoginPantallaState();
}

class _LoginPantallaState extends State<LoginPantalla> {
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _servidor = TextEditingController(text: Api.i.servidor);
  bool _verServidor = false;
  bool _verClave = false;
  bool _enviando = false;
  String _error = '';

  @override
  void initState() {
    super.initState();
    _email.addListener(() => setState(() {}));
    _password.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _servidor.dispose();
    super.dispose();
  }

  Future<void> _enviar() async {
    if (_email.text.trim().isEmpty || _password.text.isEmpty) return;
    FocusScope.of(context).unfocus();
    setState(() {
      _error = '';
      _enviando = true;
    });
    try {
      await Api.i.setServidor(_servidor.text);
      await Sesion.i.ingresar(_email.text.trim(), _password.text);
    } catch (e) {
      if (mounted) setState(() => _error = '$e');
    } finally {
      if (mounted) setState(() => _enviando = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnnotatedRegion<SystemUiOverlayStyle>(
      value: SystemUiOverlayStyle.light,
      child: Scaffold(
        backgroundColor: C.brandDark,
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(20),
              child: Column(
                children: [
                  Container(
                    width: 68,
                    height: 68,
                    decoration: BoxDecoration(color: C.brand, borderRadius: BorderRadius.circular(18)),
                    child: const Icon(Icons.shield_outlined, color: Colors.white, size: 36),
                  ),
                  const SizedBox(height: 14),
                  const Text(
                    'SGGP Técnico',
                    style: TextStyle(color: Colors.white, fontSize: 26, fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Atención de incidencias en garantía · TECHQUK',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: Color(0xFF94A3B8), fontSize: 13),
                  ),
                  const SizedBox(height: 28),
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20)),
                    child: Column(
                      children: [
                        Campo(
                          'Correo',
                          controller: _email,
                          teclado: TextInputType.emailAddress,
                          hint: 'tecnico@techquk.com.pe',
                          accion: TextInputAction.next,
                          onSubmitted: (_) => FocusScope.of(context).nextFocus(),
                        ),
                        Campo(
                          'Contraseña',
                          controller: _password,
                          oculto: !_verClave,
                          accion: TextInputAction.go,
                          onSubmitted: (_) => _enviar(),
                          // ExcludeFocus: «Siguiente» del teclado pasa del correo a la contraseña, no al ojo
                          sufijo: ExcludeFocus(
                            child: IconButton(
                              icon: Icon(_verClave ? Icons.visibility_off : Icons.visibility, color: C.textMuted),
                              onPressed: () => setState(() => _verClave = !_verClave),
                            ),
                          ),
                        ),
                        if (_verServidor) Campo('Servidor', controller: _servidor, teclado: TextInputType.url, hint: 'https://sggp.techquk.com.pe'),
                        if (_error.isNotEmpty) TextoError(_error),
                        Boton('Ingresar', onPressed: _email.text.trim().isEmpty || _password.text.isEmpty ? null : _enviar, cargando: _enviando),
                        const SizedBox(height: 14),
                        TextButton(
                          onPressed: () => setState(() => _verServidor = !_verServidor),
                          child: Text(
                            _verServidor ? 'Ocultar servidor' : 'Servidor: ${_servidor.text}',
                            style: const TextStyle(color: C.textMuted, fontSize: 12),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}
