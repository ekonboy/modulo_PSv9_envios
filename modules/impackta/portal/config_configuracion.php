<?php
include('connect.php');

$tipo = impacktaGetString('tipo');
$legacyShopId = impacktaGetInt('idShop', INPUT_GET, 1);
if ($legacyShopId <= 0) {
    $legacyShopId = 1;
}

// Endpoint legacy de configuracion usado por portal/index.php. El back office moderno
// usa AdminImpacktashippingController, pero se mantiene para compatibilidad.
if ($tipo == "json_configuracion")
{    
    $respuesta = array();

    $sql = "SELECT guid, idCliente FROM `" . $prefix . "impackta` WHERE id_shop = ? LIMIT 1";
    $sentencia = impacktaPrepareAndExecute($link, $sql, array((int) $legacyShopId), 'i');
    if (!$sentencia) {
        impacktaJsonResponse(array('error' => 'No se pudo obtener la configuracion.'), 500);
    }

    $sentencia->bind_result($guid, $idCliente);


    if ($sentencia->fetch())
    {        
        array_push($respuesta, $guid);
        array_push($respuesta, $idCliente);
    }

    $sentencia->close();

    impacktaJsonResponse($respuesta);
}
elseif ($tipo == "editar")
{
    $guid = impacktaGetString('guid', INPUT_POST);
    $idCliente = impacktaGetString('idCliente', INPUT_POST);

    if ($guid === '' || $idCliente === '') {
        impacktaJsonResponse(array('error' => 'Faltan datos obligatorios.'), 400);
    }
               
    
    $sql = "SELECT guid FROM `" . $prefix . "impackta` WHERE id_shop = ? LIMIT 1";
    $sentencia = impacktaPrepareAndExecute($link, $sql, array((int) $legacyShopId), 'i');
    if (!$sentencia) {
        impacktaJsonResponse(array('error' => 'No se pudo validar la configuracion actual.'), 500);
    }

    $sentencia->bind_result($comprobacion);


    if ($sentencia->fetch())
    {
        $sentencia->close();   
        
        $sentencia = impacktaPrepareAndExecute(
            $link,
            "UPDATE `" . $prefix . "impackta` SET guid = ?, idCliente = ? WHERE id_shop = ?",
            array($guid, $idCliente, (int) $legacyShopId),
            'ssi'
        );
        if (!$sentencia) {
            impacktaJsonResponse(array('error' => 'No se pudo actualizar la configuracion.'), 500);
        }
        $sentencia->close();
    }
    else
    {
        $sentencia->close();
        
        $sentencia = impacktaPrepareAndExecute(
            $link,
            "INSERT INTO `" . $prefix . "impackta` (id_shop, guid, idCliente) VALUES (?, ?, ?)",
            array((int) $legacyShopId, $guid, $idCliente),
            'iss'
        );
        if (!$sentencia) {
            impacktaJsonResponse(array('error' => 'No se pudo guardar la configuracion.'), 500);
        }
        $sentencia->close();
    }

    impacktaJsonResponse(array('success' => true));
}

impacktaJsonResponse(array('error' => 'Operacion no valida.'), 400);
?>
