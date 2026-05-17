<?php
/**
 * 2026 gabriel rese
 *
 * NOTICE OF LICENSE
 *
 * @author    gabriel rese
 * @copyright 2026 gabriel rese
 * @license   GNU General Public License version 2
 *
 * You can not resell or redistribute this software.
 */

class AdminImpacktashippingController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        $this->display = 'view';

        parent::__construct();
    }

    public function postProcess()
    {
        // Atiende peticiones AJAX de configuracion antes de que el controlador renderice la vista.
        if (Tools::getValue('ajax')) {
            $this->ajaxRouter();
        }

        parent::postProcess();
    }

    protected function ajaxRouter()
    {
        // API interna del back office: lee o guarda las credenciales usadas por el endpoint externo.
        $action = Tools::getValue('action');

        if ($action === 'getConfiguration') {
            $this->sendJson($this->getConfigurationData());
        }

        if ($action === 'saveConfiguration') {
            $guid = trim((string) Tools::getValue('guid'));
            $idCliente = trim((string) Tools::getValue('idCliente'));

            $this->saveConfigurationData($guid, $idCliente);

            $this->sendJson(array('success' => true));
        }

        $this->sendJson(array('success' => false, 'message' => 'Unknown action'));
    }

    protected function renderConfigurationPage()
    {
        // Pantalla custom de configuracion: genera la URL que el cliente debe pegar en Impackta.
        $ajaxUrl = $this->context->link->getAdminLink('AdminImpacktashipping') . '&ajax=1';
        $apiUrl = $this->context->link->getPageLink(
            'index',
            true,
            (int) $this->context->shop->id,
            'fc=module&module=' . urlencode($this->module->name) . '&controller=api'
        );
        $idShop = (int) $this->context->shop->id;

        $logoUrl = $this->module->getPathUri() . 'portal/img/logo.png';

        return '
        <div class="panel">
            <div style="background:#1ac1f0;padding:18px 24px;margin:-20px -20px 24px -20px;">
                <img src="' . Tools::safeOutput($logoUrl) . '" alt="Impackta" style="max-height:26px;">
            </div>

            <div class="alert alert-info">
                Pegue su código de cliente proporcionado por impackta.<br>
                Haga clic en el botón "Generar" para crear una nueva "URL tienda" que deberá pegar en su área de cliente de impackta.<br>
                Al guardar, se mostrará la "URL completa" deberá pegarla en su area cliente en: "Url tienda".
            </div>

            <div class="form-horizontal">
                <div class="form-group">
                    <label class="control-label col-lg-2">Codigo de cliente</label>
                    <div class="col-lg-4">
                        <input type="text" id="impackta-idCliente" class="form-control" maxlength="100">
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-lg-2">Url tienda</label>
                    <div class="col-lg-6">
                        <div class="input-group">
                            <input type="text" id="impackta-guid" class="form-control" readonly maxlength="100">
                            <span class="input-group-btn">
                                <button type="button" class="btn btn-default" id="impackta-generate-guid">Generar</button>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="control-label col-lg-2">URL completa</label>
                    <div class="col-lg-8">
                        <input type="text" id="impackta-store-url" class="form-control" readonly>
                        <p class="help-block">Copie esta URL para usarla en impackta con código de cliente y clave API.</p>
                    </div>
                </div>

                <div class="panel-footer">
                    <button type="button" class="btn btn-primary pull-right" id="impackta-save">Guardar</button>
                </div>
            </div>
        </div>

        <script>
        (function () {
            var ajaxUrl = ' . json_encode($ajaxUrl) . ';
            var moduleName = ' . json_encode($this->module->name) . ';
            var apiUrl = ' . json_encode($apiUrl) . ';
            var idShop = ' . (int) $idShop . ';

            if (apiUrl.indexOf("/module/" + moduleName + "/api") !== -1) {
                apiUrl = apiUrl.replace("/module/" + moduleName + "/api", "/index.php?fc=module&module=" + moduleName + "&controller=api");
            }

            console.log("Impackta API base URL:", apiUrl);
            var guidInput = document.getElementById("impackta-guid");
            var clienteInput = document.getElementById("impackta-idCliente");
            var storeUrlInput = document.getElementById("impackta-store-url");
            var saveButton = document.getElementById("impackta-save");
            var generateButton = document.getElementById("impackta-generate-guid");

            // Envia acciones de configuracion al controlador admin sin recargar la pantalla.
            function request(action, payload, onSuccess) {
                $.ajax({
                    url: ajaxUrl,
                    type: payload ? "POST" : "GET",
                    dataType: "json",
                    data: $.extend({action: action}, payload || {}),
                    success: onSuccess
                });
            }

            // Genera una clave local para identificar la tienda en las llamadas desde Impackta.
            function generateGuid() {
                var dt = new Date().getTime();

                return "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, function (c) {
                    var r = (dt + Math.random() * 16) % 16 | 0;
                    dt = Math.floor(dt / 16);

                    return (c === "x" ? r : (r & 0x3 | 0x8)).toString(16);
                });
            }

            // Compone la URL externa con codigo de cliente y clave API para pegarla en Impackta.
            function updateStoreUrl() {
                var guid = guidInput.value.trim();
                var cliente = clienteInput.value.trim();

                if (guid !== "" && cliente !== "") {
                    var separator = apiUrl.indexOf("?") === -1 ? "?" : "&";
                    storeUrlInput.value = apiUrl + separator + "idShop=" + encodeURIComponent(idShop) + "&codigoCliente=" + encodeURIComponent(cliente) + "&claveApi=" + encodeURIComponent(guid);
                } else {
                    storeUrlInput.value = "";
                }
            }

            request("getConfiguration", null, function (response) {
                guidInput.value = response.guid || "";
                clienteInput.value = response.idCliente || "";
                updateStoreUrl();
            });

            generateButton.addEventListener("click", function () {
                guidInput.value = generateGuid();
                updateStoreUrl();
            });

            clienteInput.addEventListener("input", updateStoreUrl);
            guidInput.addEventListener("input", updateStoreUrl);

            saveButton.addEventListener("click", function () {
                request("saveConfiguration", {
                    guid: guidInput.value,
                    idCliente: clienteInput.value
                }, function () {
                    if (typeof showSuccessMessage === "function") {
                        showSuccessMessage("Configuracion guardada.");
                    }
                    updateStoreUrl();
                });
            });
        }());
        </script>';
    }

    protected function getConfigurationData()
    {
        // Recupera el guid y el codigo de cliente de la tienda activa en contexto multitienda.
        $idShop = (int) $this->context->shop->id;
        $rows = Db::getInstance()->executeS(
            'SELECT guid, idCliente FROM `' . _DB_PREFIX_ . 'impackta` WHERE id_shop = ' . (int) $idShop . ' LIMIT 1'
        );
        $row = !empty($rows[0]) ? $rows[0] : array();

        return array(
            'guid' => isset($row['guid']) ? (string) $row['guid'] : '',
            'idCliente' => isset($row['idCliente']) ? (string) $row['idCliente'] : '',
        );
    }

    protected function saveConfigurationData($guid, $idCliente)
    {
        // Mantiene una fila de configuracion por tienda; actualiza si ya existe o inserta si es instalacion nueva.
        if ($guid === '' || $idCliente === '') {
            $this->sendJson(array('success' => false, 'message' => 'Faltan datos obligatorios.'));
        }

        if (Tools::strlen($guid) > 100 || Tools::strlen($idCliente) > 100) {
            $this->sendJson(array('success' => false, 'message' => 'Los datos de configuracion son demasiado largos.'));
        }

        $db = Db::getInstance();
        $idShop = (int) $this->context->shop->id;
        $data = array(
            'id_shop' => (int) $idShop,
            'guid' => pSQL($guid),
            'idCliente' => pSQL($idCliente),
        );

        if ((bool) $db->getValue('SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'impackta WHERE id_shop = ' . (int) $idShop)) {
            return $db->update('impackta', $data, 'id_shop = ' . (int) $idShop);
        }

        return $db->insert('impackta', $data);
    }

    public function renderView()
    {
        // Fuerza a que el controlador muestre la pantalla custom en vez de un listado AdminController.
        return $this->renderConfigurationPage();
    }

    protected function sendJson(array $payload)
    {
        // Respuesta JSON simple para las acciones AJAX del panel.
        header('Content-Type: application/json');
        die(json_encode($payload));
    }
}
