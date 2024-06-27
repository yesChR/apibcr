<?php

namespace App\controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Container\ContainerInterface;
use PDO;

class Transferencia extends ApiConexion
{
    protected $container;

    public function __construct(ContainerInterface $c)
    {
        $this->container = $c;
    }

    //>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>>> Funciones Propias <<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<<
    function restarSaldo($datosTransferencia)
    {
        //Para devolver los estados
        $estado = "";
        $estadoNum = 0;

        //Consultas
        $sql = "CALL restarSaldo(:idClienteOrigen, :monto, :telefonoOrigen, :telefonoDestino, :detalle, @estado, @estadoNum);";
        $sqlEstados = "SELECT @estado AS estado, @estadoNum AS estadoNum;";

        $con = $this->container->get('bd');

        $query = $con->prepare($sql);

        $query->bindValue(":idClienteOrigen", $datosTransferencia['idClienteOrigen'], PDO::PARAM_INT);
        $query->bindValue(":monto", $datosTransferencia['monto']);
        $query->bindValue(":telefonoOrigen", $datosTransferencia['telefonoOrigen'], PDO::PARAM_STR);
        $query->bindValue(":telefonoDestino", $datosTransferencia['telefonoDestino'], PDO::PARAM_STR);
        $query->bindValue(":detalle", $datosTransferencia['detalle'], PDO::PARAM_STR);

        $query->execute();

        //Obtener los valores de los parámetros de salida del SP
        $queryEstado = $con->prepare($sqlEstados);
        $queryEstado->execute();
        $result = $queryEstado->fetch(PDO::FETCH_ASSOC);
        $estado = $result['estado'];
        $estadoNum = $result['estadoNum'];

        $status = 201;
        $query = null;
        $con = null;

        return ['estado' => $estado, 'estadoNum' => $estadoNum];
    }

    // Método para sumar saldo interno
    function sumarSaldoInterno($idClienteDestino, $datosTransferencia)
    {
        //Para devolver los estados
        $estado = "";
        $estadoNum = 0;

        //Consultas
        $sql = "CALL sumarSaldo(:idClienteDestino, :monto, :telefonoOrigen, :telefonoDestino, :detalle, @estado, @estadoNum);";
        $sqlEstados = "SELECT @estado AS estado, @estadoNum AS estadoNum;";

        $con = $this->container->get('bd');
        $query = $con->prepare($sql);

        $query->bindValue(":idClienteDestino", $idClienteDestino, PDO::PARAM_INT);
        $query->bindValue(":monto", $datosTransferencia['monto']);
        $query->bindValue(":telefonoOrigen", $datosTransferencia['telefonoOrigen'], PDO::PARAM_STR);
        $query->bindValue(":telefonoDestino", $datosTransferencia['telefonoDestino'], PDO::PARAM_STR);
        $query->bindValue(":detalle", $datosTransferencia['detalle'], PDO::PARAM_STR);

        $query->execute();

        //Obtener los valores de los parámetros de salida del SP
        $queryEstado = $con->prepare($sqlEstados);
        $queryEstado->execute();
        $result = $queryEstado->fetch(PDO::FETCH_ASSOC);

        $estado = $result['estado'];
        $estadoNum = $result['estadoNum'];

        $status = 201;
        $query = null;
        $con = null;

        return ['status' => $status, 'estado' => $estado, 'estadoNum' => $estadoNum];
    }

    // Método que llama a la ruta para sumar en otro banco (BN)
    function sumaBancoExterno($data)
    {
        $url = $this::URL . 'bn/transferencia/sumar';
        $resp = $this->ejecutarCURL($url, 'PUT', $data);
        // Verificar si la ejecución de cURL fue exitosa
        if ($resp['status'] !== 200) {
            throw new \RuntimeException('Error al ejecutar la solicitud a la API externa.');
        }

        // Decodificar la respuesta JSON
        $responseData = json_decode($resp['resp'], true);

        // Verificar la respuesta del servicio externo
        if (!isset($responseData['estadoNum']) || $responseData['estadoNum'] !== 2) {
            throw new \RuntimeException('La API externa devolvió una respuesta inesperada.');
        }

        // Retornar los datos relevantes de la respuesta
        return [
            'estado' => 'Transferencia completada con éxito',
            'estadoNum' => 2
        ];
    }

    // Método para transferir saldo entre bancos (ID de banco 1)
    function transferirSaldoExterno($data)
    {
        $estadosSuma = $this->sumaBancoExterno($data);

        if ($estadosSuma['estadoNum'] !== 2) {
            return $estadosSuma;
        }

        return [
            'status' => 201,
            'estado' => 'Transferencia completada con éxito',
            'estadoNum' => 2
        ];
    }

    //del central
    function actualizarSaldosBancos($data)
    {

        $url = $this::URL . 'central/bancoComercial/actualizarSaldos';
        $datos = [
            'idBancoOrigen' => 2,
            'idBancoDestino' => 1,
            'monto' => $data[1]['monto']
        ];
        $resp = $this->ejecutarCURL($url, 'PUT', json_encode($datos)); 
        return json_decode($resp['resp']);
    }

    // Método general para transferencia
    function transferenciaGeneral(Request $request, Response $response, $args)
    {
        $body = json_decode($request->getBody(), true);

        $idBanco = $args['idBanco'];
        $idClienteDestino = $body[0]['idClienteDestino'];
        $datosTransferencia = $body[1];

        // Restar saldo
        $estadosResta = $this->restarSaldo($datosTransferencia);

        if ($estadosResta['estadoNum'] !== 2) {
            // Si la resta no fue exitosa, devolvemos el estado resultante
            $response->getBody()->write(json_encode($estadosResta));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(400);
        }

        if ($idBanco === '1') {
            // Cuando el banco es externo
            $resultadoTransferencia = $this->transferirSaldoExterno($request->getBody()); //para que vayan los dos arreglos
            $responseTransferencia = [
                'estado' => $resultadoTransferencia['estado'],
                'estadoNum' => $resultadoTransferencia['estadoNum']
            ];
        } else if ($idBanco === '2') {
            // Sumar saldo de manera interna
            $resultadoSuma = $this->sumarSaldoInterno($idClienteDestino, $datosTransferencia);
            $responseTransferencia = [
                'estado' => $resultadoSuma['estado'],
                'estadoNum' => $resultadoSuma['estadoNum']
            ];
        }
        if($responseTransferencia['estadoNum']==2){
            $respCentral = $this->actualizarSaldosBancos($body);
            $responseTransferencia = [
                'estado' => $respCentral[0]->mensaje,
                'estadoNum' => $respCentral[0]->estado
            ];
        }
        $response->getBody()->write(json_encode($responseTransferencia));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    }






    //Funcion que se ofrece
    // Método que llama la ruta para sumar de otro Banco (BCR)
    function actualizarSaldo(Request $request, Response $response, $args)
    {
        $body = json_decode($request->getBody(), true);
        $idClienteDestino = $body[0]['idClienteDestino'];
        $datosTransferencia = $body[1];
        try {
            // Llamar a la función para sumar saldo externo
            $resultadoSuma = $this->sumarSaldoInterno($idClienteDestino,$datosTransferencia);

            // Preparar la respuesta de éxito
            $responseTransferencia = [
                'estado' => $resultadoSuma['estado'],
                'estadoNum' => $resultadoSuma['estadoNum']
            ];

            $response->getBody()->write(json_encode($responseTransferencia));

            // Retornar la respuesta con éxito
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(200); // Código de éxito
        } catch (\RuntimeException $e) {
            $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
            // Capturar cualquier excepción lanzada por sumaBancoExterno
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withStatus(500); // Código de error interno del servidor
        }
    }
}
