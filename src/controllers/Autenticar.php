<?php

namespace App\controllers;

use Psr\Container\ContainerInterface;
use PDO;

class Autenticar{
    
    protected $container;
    public function __construct(ContainerInterface $c)
    {
        $this->container = $c;
    }
    protected function autenticar($usuario, $contraseña){
        //sp que traiga el id y la contraseña de la tabla usuario
        //sp que traiga nombre de un cliente y apellido1 y apellido 2
        $sql = "CALL buscarUsuario(:idUsuario)";
        $con = $this->container->get('bd');
        $query = $con->prepare($sql);
        $query->bindParam(':idUsuario', $usuario, PDO::PARAM_STR);
        $query->execute();
        $datos = $query->fetch();

        if($datos && password_verify($contraseña, $datos->contrasena)){
        
            $sql = "CALL obtenerCliente(:idUsuario)";
            $query = $con->prepare($sql);
            $query->bindParam(':idUsuario', $datos->idUsuario);
            $query->execute();
            $datos = $query->fetch();
            $retorno["nombre"] = $datos->nombre . ' ' . $datos->apellido1 . ' ' . $datos->apellido2; 
            $retorno["telefono"] = $datos->telefono;  
        }
        $query = null;
        $con = null;

        return isset($retorno) ? $retorno : null;
    }
}