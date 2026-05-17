<?php
include 'menu_superior.php';
include('connect.php');
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />        
        <title>Configuración Impackta</title>


        <script>

            function cargar_configuracion() {
                // Recupera guid e id de cliente guardados para rellenar el formulario legacy.
                $('#cargando').fadeIn(100);

                $.ajax({
                    url: 'config_configuracion.php?tipo=json_configuracion',
                    success: function (response) {

                        if (response.length == 2)
                        {
                            document.getElementById("guid").value = response[0];
                            document.getElementById("idCliente").value = response[1];
                        }


                        $('#cargando').fadeOut(100);


                    }, dataType: "json"
                });
            }



            function grabar_configuracion() {
                // Guarda la configuracion legacy en la misma tabla propia usada por el modulo.
                $('#cargando').fadeIn(100);


                var guid = document.getElementById("guid").value;
                var idCliente = document.getElementById("idCliente").value;



                var parametros = {"guid": guid, "idCliente": idCliente};

                $.ajax({
                    data: parametros,
                    url: 'config_configuracion.php?tipo=editar',
                    type: 'post',
                    success: function (response) {

                        window.location = "index.php";
                    }
                });
            }




            function generar_guid() {
                // Crea una clave GUID para identificar esta tienda ante Impackta.
                var dt = new Date().getTime();
                var uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
                    var r = (dt + Math.random() * 16) % 16 | 0;
                    dt = Math.floor(dt / 16);
                    return (c == 'x' ? r : (r & 0x3 | 0x8)).toString(16);
                });
                
                document.getElementById("guid").value = uuid;
                
            }

        </script>
    </head>

    <body>


        <div style="float: left; width: calc(100% - 20px); margin-top: 80px; margin-left: 10px;">

            <div style="float:left; width: 665px; margin-left: calc(50% - 442px);">

                <div style="float: left; width: 100%">
                    <div style="float:left; margin-top: 6px;">Código de cliente: </div>
                    <input class="input" type="text" name="idCliente" id="idCliente" style="width:50px; margin-left: 20px;" placeholder="Código cliente"/>
                </div>

                <div style="float: left; width: 100%; margin-top: 20px;">
                    <div style="float:left; margin-top: 6px; width: 112px; text-align: right;">Guid: </div>
                    <input class="input" type="text" name="guid" id="guid" style="width:340px; margin-left: 20px; float: left;" readonly placeholder="GUID"/> 
                    <button class="boton" style="float: left; margin-left: 20px;" onclick="generar_guid();">Generar</button>
                </div>


                <button class="boton" style="float: right; margin-top: 20px;" onclick="grabar_configuracion();">Guardar</button>
            </div>

        </div>
    </body>
</html>
<script>
    cargar_configuracion();
</script>
