<?php
require __DIR__ . '/../src/app.php';
session_destroy();
redirigir('/login.php');
