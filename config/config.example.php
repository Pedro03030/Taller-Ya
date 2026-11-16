<?php
// Copiar este archivo como config.php y completar los datos.
// config.php NO se sube a Git (está en .gitignore) porque tiene claves.
return [
    // En InfinityFree los datos están en el panel: "MySQL Databases".
    'db' => [
        'dsn'      => 'mysql:host=sqlXXX.infinityfree.com;dbname=if0_XXXXXXXX_tallerya;charset=utf8mb4',
        'usuario'  => 'if0_XXXXXXXX',
        'password' => 'la_contraseña_de_la_cuenta',
        // En XAMPP sería: 'mysql:host=localhost;dbname=tallerya;charset=utf8mb4', usuario 'root', password ''
    ],
    // Avisos por email con el SMTP de Gmail.
    'email' => [
        'gmail_usuario'      => 'tu_mail@gmail.com',
        'gmail_password_app' => '',   // contraseña de aplicación de 16 letras: https://myaccount.google.com/apppasswords
        'nombre_remitente'   => 'TallerYa',
        'puerto'             => 465,  // si no conecta, probar con 587
    ],
    // Ruta donde quedó la carpeta public, vista desde el navegador.
    // InfinityFree o XAMPP con el proyecto en la raíz: '/public'. Con php -S -t public: ''
    'url_base' => '/public',
    'zona_horaria' => 'America/Argentina/Buenos_Aires',
];
