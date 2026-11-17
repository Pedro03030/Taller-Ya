# Uso de IA en el proyecto

Usamos **Claude** (Anthropic) como ayuda durante el TP.

## Para qué la usamos
- Generar una primera versión del código del sistema (PHP + MySQL), que después probamos, publicamos y ajustamos.
- Armar la estructura de la documentación de las entregas (requerimientos, historias de usuario, diagramas).
- Resolver problemas al publicar en InfinityFree: el FTP, la base de datos y el envío de emails.
- Preparar una guía para el informe teórico y una explicación del sistema para estudiar para la defensa.

## Ejemplos de lo que le pedimos
1. **"Empezá el TP de cero"**, pasándole el PDF de la consigna, con el cliente taller mecánico y PHP + MySQL. Armó el código, la base de datos y la documentación de las entregas 1 y 2.
2. **"Lo voy a subir a InfinityFree."** Nos avisó que el hosting gratis bloquea la API de Telegram, así que los avisos pasaron a ser por email. También adaptó el proyecto a la carpeta `htdocs`.
3. **"No puedo cargar los archivos por FTP."** Con una captura de WinSCP vimos que estábamos usando SFTP (puerto 22), y que InfinityFree solo acepta FTP (puerto 21).
4. **Le pasamos el archivo exportado de la base** y nos señaló que las tablas se habían creado como MyISAM, que no respeta las claves foráneas. Corrigió `schema.sql` para que use InnoDB.
5. **"No me llegó el primer mail."** Revisamos juntos el registro de envíos de Brevo: aceptaba el pedido pero después lo rechazaba porque el remitente era un Gmail. Le pedimos cambiar a Gmail SMTP con una contraseña de aplicación.
6. **"Vamos a cargar a GitHub, pero no quiero usar git."** Nos explicó cómo subir por la página de GitHub en varios commits, y que los `.htaccess` hay que crearlos con *Create new file* porque GitHub no los sube arrastrando.
7. **"Necesito el Word de cómo funciona el sistema."** Armó la explicación completa del sistema para estudiar para la defensa.
8. **Guía del informe teórico (tema 9):** le pedimos el esquema, ideas y fuentes. El texto del informe lo redactamos nosotros.

## Qué decidimos y cambiamos nosotros
- Elegimos el cliente, la tecnología y el hosting.
- Cuando Telegram y Brevo no funcionaron, decidimos pasar a Gmail SMTP.
- Probamos todo en el sitio publicado y encontramos los errores (FTP, tablas MyISAM, emails que no llegaban) que después corregimos.
- Repartimos el sistema en tres módulos, uno por integrante.
Integrantes: Devia Lautaro, Di Prinzio Pedro y Ruesga Lautaro. 
