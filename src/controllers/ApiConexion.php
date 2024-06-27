<?php

namespace App\controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class ApiConexion
{
    const URL = "http://"; //solo http porque se completa en las funciones
        //hace peticiones http
        protected function ejecutarCURL($url, $metodo, $datos = null)
        {
            //Esto lo habilita para todos
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            //Esto es porque tanto el POST como el PUT almacenan datos
            if ($datos != null) { //Si hay datos
                curl_setopt($ch, CURLOPT_POSTFIELDS, $datos); //Habilita el POST
            }
    
            switch ($metodo) {
                case 'POST':
                    curl_setopt($ch, CURLOPT_POST, true);
                    break;
                case 'PUT':
                case 'DELETE':
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $metodo); //PUT o DELETE
                    break;
            }
    
            $resp = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return ['resp' => $resp, 'status' => $status];
        }
    
}
