<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: X-API-KEY, Origen, X-Request-Width, Content-Type, Accept, Access-Control-Request-Method, Authorization');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Allow: GET, POST, OPTIONS, PUT, DELETE');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    die('Metodo no permitido');
}

//echo "hola";

require __DIR__ . "/src/app/app.php";


//require "./src/app/app.php";
