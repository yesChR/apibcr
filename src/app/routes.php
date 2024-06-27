<?php
namespace App\controllers;
use Slim\Routing\RouteCollectorProxy;

$app->group('/cliente',function(RouteCollectorProxy $cliente){
    $cliente->get('/obtener/{idCliente}', Cliente::class . ':obtenerClienteParaBN');//este es para la redireccion
    $cliente->post('', Cliente::class . ':resumen');//el que se usa en insomnia
}); 

$app->group('/transferencia', function (RouteCollectorProxy $transferencia) {
    $transferencia->post('/{idBanco}', Transferencia::class . ':transferenciaGeneral');
    $transferencia->put('/sumar', Transferencia::class . ':actualizarSaldo');
});

$app->group('/auth',function(RouteCollectorProxy $auth){
    $auth->post('/iniciar', Auth::class . ':iniciar'); 
    $auth->put('/cerrar/{idUsuario}', Auth::class . ':cerrar');
    $auth->post('/refresh', Auth::class . ':refrescar');
});
