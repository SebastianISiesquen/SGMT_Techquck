' Ejecuta el programador de Laravel sin abrir ninguna ventana.
' Lo lanza la tarea de Windows "SGGP Programador" cada minuto; Laravel decide qué toca a cada hora
' (las horas se cambian en Configuración → Alertas).
Set fso = CreateObject("Scripting.FileSystemObject")
backend = fso.GetParentFolderName(fso.GetParentFolderName(WScript.ScriptFullName))
CreateObject("WScript.Shell").Run "cmd /c cd /d """ & backend & """ && C:\xampp\php\php.exe artisan schedule:run >> storage\logs\programador.log 2>&1", 0, False
