<?php

namespace App\controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use PDO;

class Cliente extends ApiConexion
{
    protected $container;
    public function __construct(ContainerInterface $c)
    {
        $this->container = $c;
    }

    /*Obtener saldo****************************************************************************************************/

    function consultarSaldo($data)
    {
        $sql = "CALL consultarSaldo(:idCliente, :monto)";
        $con = $this->container->get('bd');
        $query = $con->prepare($sql);
        $query->bindValue(':idCliente', $data->idCliente, PDO::PARAM_INT);
        $query->bindValue(':monto', $data->monto, PDO::PARAM_STR);
        $query->execute();
        $res = $query->fetchAll(PDO::FETCH_ASSOC);
        $status = match ($res[0]['estado']) {
            0 => 400, // Estado 0: Sin fondos suficientes
            1 => 200, // Estado 1: Posee los fondos suficientes
            2 => 404, // Estado 2: No se encontró el cliente
            default => 500, // Estado desconocido o no esperado
        };
        $query = null;
        $con = null;
        return $status;
    }

    /*LOGICA OBTENER CLIENTE************************************************************************************** */
    //si o si debe retornar aunque no este segura de si vienen datos o no porque luego se comprueba
    /*function obtenerClienteInterno($idCliente)
    {
        $sql = "CALL obtenerCliente(:idCliente)";
        $con = $this->container->get('bd');
        $query = $con->prepare($sql);

        $query->bindValue(':idCliente', $idCliente['idCliente'], PDO::PARAM_INT);
        $query->execute();
        $res = $query->fetchAll();
        $query = null;
        $con = null;
        if (isset($res[0]->estado)) { //para que no me aparezcan mensajes cuando no funciona
            $res = [];
        }
        return $res;
    }*/

    function obtenerClienteInterno($idCliente)
    {
        if (is_array($idCliente)) {
            $idCliente = $idCliente['idCliente'];
        }
    
        $sql = "CALL obtenerCliente(:idCliente)";
        $con = $this->container->get('bd');
        $query = $con->prepare($sql);
        $query->bindValue(':idCliente', $idCliente, PDO::PARAM_INT);
        $query->execute();
        $res = $query->fetchAll(PDO::FETCH_OBJ);  // Aquí aseguramos que los resultados sean objetos
    
        $query = null;
        $con = null;
        if (isset($res[0]->estado)) { //para que no me aparezcan mensajes cuando no funciona
            $res = [];
        }
        return $res;
    }
    

    function obtenerClienteExterno($idCliente)
    {
        $url = $this::URL . 'bn/cliente/obtener/' . $idCliente;
        $resp = $this->ejecutarCURL($url, 'GET');  //viene json y status
        return json_decode($resp['resp']); //ignoro status
    }

    function obtenerClienteGeneral($idCliente, $idBancoDestino)
    {
        $cliente = null;
        if ($idBancoDestino === '1') { //bn
            $cliente = $this->obtenerClienteExterno($idCliente);
        } else if ($idBancoDestino === '2') {
            $cliente = $this->obtenerClienteInterno($idCliente);
        }
        return $cliente;
    }

    //esto es un metodo de acceso que es solo para ofrecer al otro banco y no debe ser usado aqui
    function  obtenerClienteParaBN(Request $request, Response $response, $args)
    {
        $obtenerCliente = $this->obtenerClienteInterno($args);
        if (isset($obtenerCliente[0]->estado)) {
            $status = match ($obtenerCliente[0]->estado) {
                '0' => 204
            };
        } else {
            $response->getBody()->write(json_encode($obtenerCliente));
            $status = 200;
        }
        return $response
            ->withHeader('Content-type', 'Application/json')
            ->withStatus($status);
    }


    /*Llamados al central*************************************************************************************** */
    function validarCuenta($telefono)
    {
        $url = $this::URL . 'central/monkeycash/' . $telefono;
        $resp = $this->ejecutarCURL($url, 'GET');  //viene json y status
        return $resp;
    }

    /*Final********************************************************************************************************/
    /*function resumen(Request $request, Response $response)
    {
        $data = json_decode($request->getBody());
        $saldoSuficiente = $this->consultarSaldo($data);
        if ($saldoSuficiente == 200) {
            $existeCuenta = $this->validarCuenta($data->telefono); //viene telefono, monto, detalle solo obtengo tel
            if ($existeCuenta['status'] == 200) {
                $datos = json_decode($existeCuenta['resp']);
                $cliente = $this->obtenerClienteGeneral($datos[0]->idCliente, $datos[0]->idBanco);
                if (!empty($cliente)) {
                    $resumen = [
                        'IdBancoDestino' => $datos[0]->idBanco,
                        'TipoTransferencia' => 'MonkeyCash',
                        'TelefonoOrigen' => $data->telefonoOrigen,
                        'NombreOrigen' => $data->nombreOrigen,
                        'TelefonoDestino' => $data->telefono,
                        'NombreDestino' => $cliente[0]['nombre'] . ' ' . $cliente[0]['apellido1'] . ' ' . $cliente[0]['apellido2'],
                        'Monto' => $data->monto,
                        'Detalle' => $data->detalle
                    ];
                    $response->getBody()->write(json_encode($resumen));
                    $status = 200;
                    return $response
                        ->withHeader('Content-type', 'Application/json')
                        ->withStatus($status);
                }
            } else {
                return $response
                    ->withHeader('Content-type', 'Application/json')
                    ->withStatus(404);
            }
        } else {
            return $response
                ->withHeader('Content-type', 'Application/json')
                ->withStatus(400);
        }
    }*/

    function resumen(Request $request, Response $response)
{
    $data = json_decode($request->getBody());
    $saldoSuficiente = $this->consultarSaldo($data);
    if ($saldoSuficiente == 200) {
        $existeCuenta = $this->validarCuenta($data->telefono); //viene telefono, monto, detalle solo obtengo tel
        if ($existeCuenta['status'] == 200) {
            $datos = json_decode($existeCuenta['resp']);
            $cliente = $this->obtenerClienteGeneral($datos[0]->idCliente, $datos[0]->idBanco);
            
            // Cambio: Verificación si $cliente no está vacío
            if (!empty($cliente)) { 
                $resumen = [
                    'IdBancoDestino' => $datos[0]->idBanco,
                    'IdClienteDestino' => $datos[0]->idCliente,
                    'TipoTransferencia' => 'MonkeyCash',
                    'TelefonoOrigen' => $data->telefonoOrigen,
                    'NombreOrigen' => $data->nombreOrigen,
                    'TelefonoDestino' => $data->telefono,
                    
                    // Cambio: Acceso a las propiedades del objeto
                    'NombreDestino' => $cliente[0]->nombre . ' ' . $cliente[0]->apellido1 . ' ' . $cliente[0]->apellido2, 
                    
                    'Monto' => $data->monto,
                    'Detalle' => $data->detalle
                ];
                $response->getBody()->write(json_encode($resumen));
                $status = 200;
                return $response
                    ->withHeader('Content-type', 'Application/json')
                    ->withStatus($status);
            } else {
                return $response
                    ->withHeader('Content-type', 'Application/json')
                    ->withStatus(204);
            }
        } else {
            return $response
                ->withHeader('Content-type', 'Application/json')
                ->withStatus(204);
        }
    } else {
        return $response
            ->withHeader('Content-type', 'Application/json')
            ->withStatus(400);
    }
}

}
