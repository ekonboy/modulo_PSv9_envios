<?php

include('connect.php');

impacktaRegisterShutdownLogger();

function impacktaNormalizeKey($value)
{
    // Convierte textos de estados, paises o pagos a claves comparables sin acentos ni espacios.
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $value = strtolower($value);
    $value = str_replace(
        array('á', 'à', 'ä', 'â', 'ã', 'é', 'è', 'ë', 'ê', 'í', 'ì', 'ï', 'î', 'ó', 'ò', 'ö', 'ô', 'õ', 'ú', 'ù', 'ü', 'û', 'ñ', 'ç'),
        array('a', 'a', 'a', 'a', 'a', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'n', 'c'),
        $value
    );

    return preg_replace('/[^a-z0-9]+/', '', $value);
}

function impacktaNormalizeCountryIso($countryValue)
{
    // Devuelve ISO-2 cuando Impackta o PrestaShop envian paises como nombre en vez de codigo.
    $country = strtoupper(trim((string) $countryValue));
    if ($country === '') {
        return '';
    }

    if (strlen($country) === 2) {
        return $country;
    }

    $map = array(
        'spain' => 'ES',
        'espana' => 'ES',
        'espaa' => 'ES',
        'unitedstates' => 'US',
        'estadosunidos' => 'US',
        'france' => 'FR',
        'francia' => 'FR',
        'portugal' => 'PT',
        'germany' => 'DE',
        'alemania' => 'DE',
        'italy' => 'IT',
        'italia' => 'IT',
        'unitedkingdom' => 'GB',
        'reinounido' => 'GB',
    );

    $key = impacktaNormalizeKey($countryValue);

    return isset($map[$key]) ? $map[$key] : $country;
}

function impacktaNormalizeOrderStateLabel($stateName)
{
    // Mapea alias habituales de estados a etiquetas esperadas en tiendas en castellano.
    $key = impacktaNormalizeKey($stateName);

    $map = array(
        'pagoaceptado' => 'Pago aceptado',
        'paymentaccepted' => 'Pago aceptado',
        'pagado' => 'Pago aceptado',
        'paid' => 'Pago aceptado',
        'awaitingcheckpayment' => 'Pendiente de pago',
        'awaitingbankwirepayment' => 'Pendiente de pago',
        'pendientedepago' => 'Pendiente de pago',
        'pendingpayment' => 'Pendiente de pago',
        'enviado' => 'Enviado',
        'shipped' => 'Enviado',
        'entregado' => 'Entregado',
        'delivered' => 'Entregado',
        'cancelado' => 'Cancelado',
        'canceled' => 'Cancelado',
        'cancelled' => 'Cancelado',
        'errordepago' => 'Error de pago',
        'paymenterror' => 'Error de pago',
    );

    return isset($map[$key]) ? $map[$key] : (string) $stateName;
}

function impacktaGetRequestScheme()
{
    // Reconstruye http/https teniendo en cuenta proxies para generar URLs correctas del endpoint.
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $forwardedProto = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_PROTO']);
        if (strtolower(trim($forwardedProto[0])) === 'https') {
            return 'https';
        }
    }

    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return 'https';
    }

    if (isset($_SERVER['SERVER_PORT']) && (string) $_SERVER['SERVER_PORT'] === '443') {
        return 'https';
    }

    return 'http';
}

function impacktaStateLogFile()
{
    // Archivo de log propio para diagnosticar cambios de estado solicitados desde Impackta.
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'impackta_estado_log.txt';
}

function impacktaLogStateEvent($message, array $context = array())
{
    // Registra cada paso critico en error_log y en el fichero local del modulo.
    $line = date('Y-m-d H:i:s') . ' | ' . (string) $message;

    if (!empty($context)) {
        $parts = array();
        foreach ($context as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = json_encode($value);
            }

            $parts[] = $key . '=' . str_replace(array("\r", "\n"), ' ', (string) $value);
        }

        $line .= ' | ' . implode(' | ', $parts);
    }

    $line .= PHP_EOL;

    error_log('[impackta] ' . trim($line));
    @file_put_contents(impacktaStateLogFile(), $line, FILE_APPEND);
}

function impacktaSetDebugStep($step, array $context = array())
{
    // Guarda el ultimo paso ejecutado para que un shutdown fatal pueda indicar donde fallo.
    $GLOBALS['impackta_debug_step'] = (string) $step;
    $GLOBALS['impackta_debug_context'] = $context;
    impacktaLogStateEvent($step, $context);
}

function impacktaRegisterShutdownLogger()
{
    // Activa una sola vez el logger de errores fatales durante llamadas al endpoint.
    static $registered = false;

    if ($registered) {
        return;
    }

    $registered = true;

    register_shutdown_function('impacktaHandleShutdownLog');
}

function impacktaHandleShutdownLog()
{
    // Si PHP muere por error fatal, deja una pista con el ultimo paso y contexto de pedido.
    $error = error_get_last();
    if (empty($error)) {
        return;
    }

    $fatalTypes = array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR);
    if (!in_array((int) $error['type'], $fatalTypes, true)) {
        return;
    }

    $context = isset($GLOBALS['impackta_debug_context']) && is_array($GLOBALS['impackta_debug_context'])
        ? $GLOBALS['impackta_debug_context']
        : array();

    $context['last_step'] = isset($GLOBALS['impackta_debug_step']) ? (string) $GLOBALS['impackta_debug_step'] : '';
    $context['php_error_type'] = isset($error['type']) ? (int) $error['type'] : 0;
    $context['php_error_message'] = isset($error['message']) ? (string) $error['message'] : '';
    $context['php_error_file'] = isset($error['file']) ? (string) $error['file'] : '';
    $context['php_error_line'] = isset($error['line']) ? (int) $error['line'] : 0;

    impacktaLogStateEvent('shutdown_fatal_error', $context);
}

function impacktaNormalizePaymentMethod($paymentMethod)
{
    // Unifica nombres de modulos/metodos de pago para enviarlos a Impackta con etiquetas legibles.
    $paymentMethod = trim((string) $paymentMethod);
    if ($paymentMethod === '') {
        return '';
    }

    $key = impacktaNormalizeKey($paymentMethod);

    $map = array(
        'pagosportransferenciabancaria' => 'Pagos por transferencia bancaria',
        'transferenciabancaria' => 'Pagos por transferencia bancaria',
        'bankwire' => 'Pagos por transferencia bancaria',
        'banktransfer' => 'Pagos por transferencia bancaria',
        'wiretransfer' => 'Pagos por transferencia bancaria',
        'prestashopcheckout' => 'Pagos por transferencia bancaria',
        'paymentbycheck' => 'Pagos por cheque',
        'pagoporcheque' => 'Pagos por cheque',
        'pagosporcheque' => 'Pagos por cheque',
        'checkpayment' => 'Pagos por cheque',
        'cheque' => 'Pagos por cheque',
        'cashondeliverycod' => 'Contra reembolso',
        'cashondelivery' => 'Contra reembolso',
        'contrareembolso' => 'Contra reembolso',
        'paypal' => 'PayPal',
    );

    return isset($map[$key]) ? $map[$key] : $paymentMethod;
}

function impacktaFindStateIdByFlag($link, $prefix, $flagField)
{
    // Busca estados nativos por flags de PS cuando el nombre traducido no es fiable.
    $allowed = array('shipped', 'delivered', 'paid');
    if (!in_array($flagField, $allowed, true)) {
        return 0;
    }

    $sql = "SELECT id_order_state
        FROM `" . $prefix . "order_state`
        WHERE `" . $flagField . "` = 1
        ORDER BY id_order_state ASC
        LIMIT 1";

    $statement = impacktaPrepareAndExecute($link, $sql);
    if (!$statement) {
        return 0;
    }

    $statement->bind_result($stateId);
    $result = $statement->fetch() ? (int) $stateId : 0;
    $statement->close();

    return $result;
}

function impacktaGetOrderStateSql($prefix)
{
    // Fragmento SQL reutilizable para obtener el ultimo estado real del pedido en el idioma activo.
    return "COALESCE(
        (SELECT osl.name
            FROM `" . $prefix . "order_state_lang` osl
            WHERE osl.id_order_state = COALESCE(
                (SELECT oh.id_order_state
                    FROM `" . $prefix . "order_history` oh
                    WHERE oh.id_order = o.id_order
                    ORDER BY oh.date_add DESC, oh.id_order_history DESC
                    LIMIT 1),
                o.current_state
            ) AND osl.id_lang = ?
            LIMIT 1),
        (SELECT osl.name
            FROM `" . $prefix . "order_state_lang` osl
            WHERE osl.id_order_state = COALESCE(
                (SELECT oh.id_order_state
                    FROM `" . $prefix . "order_history` oh
                    WHERE oh.id_order = o.id_order
                    ORDER BY oh.date_add DESC, oh.id_order_history DESC
                    LIMIT 1),
                o.current_state
            )
            ORDER BY osl.id_lang ASC
            LIMIT 1),
        ''
    )";
}

function impacktaNormalizeSortDirection($sortDirection)
{
    // Limita la ordenacion externa a ASC/DESC antes de concatenarla en SQL.
    $sortDirection = strtoupper(trim((string) $sortDirection));

    if ($sortDirection === 'DESC') {
        return 'DESC';
    }

    return 'ASC';
}

function impacktaRequestOrders($link, $prefix, $idLang, $fromDate, $toDate, $sortDirection)
{
    // Devuelve el listado de pedidos para Impackta, opcionalmente filtrado por rango de fechas.
    $response = array();
    $whereClause = '';
    $queryParams = array($idLang);
    $queryTypes = 'i';
    $stateSql = impacktaGetOrderStateSql($prefix);
    $sortDirection = impacktaNormalizeSortDirection($sortDirection);

    if ($fromDate !== '' && $toDate !== '') {
        $whereClause = 'WHERE o.date_add BETWEEN ? AND ?';
        $queryParams[] = $fromDate;
        $queryParams[] = $toDate;
        $queryTypes .= 'ss';
    }

    $sql = "SELECT o.id_order, o.reference, DATE_FORMAT(o.date_add, '%Y-%m-%d %H:%i:%s'), a.company, a.firstname, a.lastname, a.address1, a.address2, a.postcode, a.city,
        IF(a.phone = '', IFNULL((SELECT ad.phone FROM `" . $prefix . "address` ad WHERE ad.id_customer = o.id_customer AND ad.phone <> '' ORDER BY ad.id_address DESC LIMIT 1), ''), a.phone),
        IF(a.phone_mobile = '', IFNULL((SELECT ad.phone_mobile FROM `" . $prefix . "address` ad WHERE ad.id_customer = o.id_customer AND ad.phone_mobile <> '' ORDER BY ad.id_address DESC LIMIT 1), ''), a.phone_mobile),
        cu.email,
        " . $stateSql . " AS estado,
        co.iso_code, c.name,
        IFNULL((SELECT 'Pagado' FROM `" . $prefix . "order_payment` op WHERE op.order_reference = o.reference LIMIT 1), 'Pendiente') AS pago
        FROM `" . $prefix . "orders` o
        INNER JOIN `" . $prefix . "carrier` c ON c.id_carrier = o.id_carrier
        INNER JOIN `" . $prefix . "address` a ON a.id_address = o.id_address_delivery
        INNER JOIN `" . $prefix . "country` co ON co.id_country = a.id_country
        INNER JOIN `" . $prefix . "customer` cu ON cu.id_customer = o.id_customer
        " . $whereClause . "
        ORDER BY o.id_order " . $sortDirection;

    $statement = impacktaPrepareAndExecute($link, $sql, $queryParams, $queryTypes);
    if (!$statement) {
        impacktaJsonResponse(array('error' => 'No se pudieron obtener los pedidos.'), 500);
    }

    $statement->bind_result(
        $id,
        $reference,
        $date,
        $company,
        $firstname,
        $lastname,
        $address1,
        $address2,
        $postcode,
        $city,
        $phone,
        $mobile,
        $email,
        $state,
        $country,
        $service,
        $paymentStatus
    );

    while ($statement->fetch()) {
        if (empty($id)) {
            continue;
        }

        array_push($response, array(
            $id,
            $reference,
            $date,
            trim($company . ' ' . $firstname . ' ' . $lastname),
            trim($address1 . ' ' . $address2),
            $postcode,
            $city,
            $phone,
            $mobile,
            $email,
            impacktaNormalizeCountryIso($country),
            $service,
            $state,
            $paymentStatus,
        ));
    }

    $statement->close();

    impacktaJsonResponse($response);
}

function impacktaRequestOrder($link, $prefix, $idLang, $orderId, $orderReference)
{
    // Devuelve la ficha de un pedido con destinatario, importe, pago, servicio y lineas de producto.
    $response = array();
    $whereClause = '';
    $queryParams = array($idLang);
    $queryTypes = 'i';
    $stateSql = impacktaGetOrderStateSql($prefix);

    if ($orderId > 0) {
        $whereClause = 'o.id_order = ?';
        $queryParams[] = (int) $orderId;
        $queryTypes .= 'i';
    } else {
        $whereClause = 'o.reference = ?';
        $queryParams[] = (string) $orderReference;
        $queryTypes .= 's';
    }

    $sql = "SELECT o.id_order, o.reference, a.company, a.firstname, a.lastname, a.address1, a.address2, a.postcode, a.city,
        IF(a.phone = '', IFNULL((SELECT ad.phone FROM `" . $prefix . "address` ad WHERE ad.id_customer = o.id_customer AND ad.phone <> '' ORDER BY ad.id_address DESC LIMIT 1), ''), a.phone),
        IF(a.phone_mobile = '', IFNULL((SELECT ad.phone_mobile FROM `" . $prefix . "address` ad WHERE ad.id_customer = o.id_customer AND ad.phone_mobile <> '' ORDER BY ad.id_address DESC LIMIT 1), ''), a.phone_mobile),
        co.iso_code, cu.email,
        ROUND(o.total_paid_tax_incl, 2), o.payment,
        c.name,
        " . $stateSql . " AS estado,
        IFNULL((SELECT 'Pagado' FROM `" . $prefix . "order_payment` op WHERE op.order_reference = o.reference LIMIT 1), 'Pendiente') AS pago,
        IFNULL((SELECT SUBSTRING(cm.message, 1, 512) FROM `" . $prefix . "customer_thread` ct JOIN `" . $prefix . "customer_message` cm ON ct.id_customer_thread = cm.id_customer_thread WHERE ct.id_order = o.id_order AND cm.id_employee = 0 ORDER BY cm.date_add ASC LIMIT 1), '')
        FROM `" . $prefix . "orders` o
        INNER JOIN `" . $prefix . "carrier` c ON c.id_carrier = o.id_carrier
        INNER JOIN `" . $prefix . "address` a ON a.id_address = o.id_address_delivery
        INNER JOIN `" . $prefix . "country` co ON co.id_country = a.id_country
        INNER JOIN `" . $prefix . "customer` cu ON cu.id_customer = o.id_customer
        WHERE " . $whereClause . "
        LIMIT 1";

    $statement = impacktaPrepareAndExecute($link, $sql, $queryParams, $queryTypes);
    if (!$statement) {
        impacktaJsonResponse(array('error' => 'No se pudo obtener el pedido.'), 500);
    }

    $statement->bind_result(
        $resolvedOrderId,
        $reference,
        $company,
        $firstname,
        $lastname,
        $address1,
        $address2,
        $postcode,
        $city,
        $phone,
        $mobile,
        $countryIso,
        $email,
        $amount,
        $paymentType,
        $service,
        $state,
        $paymentStatus,
        $notes
    );

    if ($statement->fetch()) {
        array_push($response, (int) $resolvedOrderId);
        array_push($response, $reference);
        array_push($response, trim($company . ' ' . $firstname . ' ' . $lastname));
        array_push($response, trim($address1 . ' ' . $address2));
        array_push($response, $postcode);
        array_push($response, $city);
        array_push($response, $phone);
        array_push($response, $mobile);
        array_push($response, $email);
        array_push($response, impacktaNormalizeCountryIso($countryIso));
        array_push($response, str_replace(',', '.', $amount));
        array_push($response, impacktaNormalizePaymentMethod($paymentType));
        array_push($response, $service);
        array_push($response, $state);
        array_push($response, $paymentStatus);
        array_push($response, $notes);
    }

    $statement->close();

    if (empty($response)) {
        impacktaJsonResponse($response);
    }

    $items = array();
    $sql = "SELECT od.product_id, od.product_attribute_id, od.product_quantity
        FROM `" . $prefix . "order_detail` od
        WHERE od.id_order = ?";
    $statement = impacktaPrepareAndExecute($link, $sql, array((int) $resolvedOrderId), 'i');
    if (!$statement) {
        impacktaJsonResponse(array('error' => 'No se pudieron obtener las lineas del pedido.'), 500);
    }

    $statement->bind_result($productId, $productAttributeId, $quantity);
    while ($statement->fetch()) {
        array_push($items, array($productId . $productAttributeId, $quantity));
    }
    $statement->close();

    array_push($response, $items);

    impacktaJsonResponse($response);
}

function impacktaFindStateId($link, $prefix, $idLang, $stateName)
{
    // Resuelve un nombre de estado recibido por la API a id_order_state, usando flags y traducciones.
    $stateKey = impacktaNormalizeKey($stateName);
    $normalizedStateName = impacktaNormalizeOrderStateLabel($stateName);
    $shippedAliases = array('enviado', 'shipped');
    $deliveredAliases = array('entregado', 'delivered');
    $paidAliases = array('pagado', 'paid', 'pagoaceptado', 'paymentaccepted');

    if (in_array($stateKey, $shippedAliases, true)) {
        $stateId = impacktaFindStateIdByFlag($link, $prefix, 'shipped');
        if ($stateId > 0) {
            return $stateId;
        }
    }

    if (in_array($stateKey, $deliveredAliases, true)) {
        $stateId = impacktaFindStateIdByFlag($link, $prefix, 'delivered');
        if ($stateId > 0) {
            return $stateId;
        }
    }

    if (in_array($stateKey, $paidAliases, true)) {
        $stateId = impacktaFindStateIdByFlag($link, $prefix, 'paid');
        if ($stateId > 0) {
            return $stateId;
        }
    }

    $queryParams = array();
    $queryTypes = '';
    $whereLang = '';

    if ((int) $idLang > 0) {
        $whereLang = ' WHERE osl.id_lang = ?';
        $queryParams[] = (int) $idLang;
        $queryTypes .= 'i';
    }

    $sql = "SELECT DISTINCT os.id_order_state, osl.name
        FROM `" . $prefix . "order_state` os
        INNER JOIN `" . $prefix . "order_state_lang` osl ON osl.id_order_state = os.id_order_state
        " . $whereLang . "
        ORDER BY os.id_order_state ASC";
    $statement = impacktaPrepareAndExecute($link, $sql, $queryParams, $queryTypes);
    if ($statement) {
        $statement->bind_result($stateId, $dbStateName);
        while ($statement->fetch()) {
            if (impacktaNormalizeKey($dbStateName) === $stateKey) {
                $statement->close();

                return (int) $stateId;
            }
        }
        $statement->close();
    }

    $search = '%' . $normalizedStateName . '%';
    $queryParams = array($search);
    $queryTypes = 's';
    $whereLang = '';

    if ((int) $idLang > 0) {
        $whereLang = ' AND osl.id_lang = ?';
        $queryParams[] = (int) $idLang;
        $queryTypes .= 'i';
    }

    $sql = "SELECT DISTINCT os.id_order_state
        FROM `" . $prefix . "order_state` os
        INNER JOIN `" . $prefix . "order_state_lang` osl ON osl.id_order_state = os.id_order_state
        WHERE osl.name LIKE ?" . $whereLang . "
        ORDER BY os.id_order_state ASC
        LIMIT 1";
    $statement = impacktaPrepareAndExecute($link, $sql, $queryParams, $queryTypes);
    if (!$statement) {
        return 0;
    }

    $statement->bind_result($stateId);
    $result = $statement->fetch() ? (int) $stateId : 0;
    $statement->close();

    if ($result > 0) {
        return $result;
    }

    $sql = "SELECT DISTINCT os.id_order_state
        FROM `" . $prefix . "order_state` os
        INNER JOIN `" . $prefix . "order_state_lang` osl ON osl.id_order_state = os.id_order_state
        WHERE osl.name LIKE ?
        ORDER BY os.id_order_state ASC
        LIMIT 1";
    $statement = impacktaPrepareAndExecute($link, $sql, array($search), 's');
    if (!$statement) {
        return 0;
    }

    $statement->bind_result($stateId);
    $result = $statement->fetch() ? (int) $stateId : 0;
    $statement->close();

    return $result;
}

function impacktaResolveOrderId($link, $prefix, $orderId, $orderReference)
{
    // Acepta tanto id numerico como referencia de pedido para compatibilidad con llamadas externas.
    if ((int) $orderId > 0) {
        return (int) $orderId;
    }

    $orderReference = trim((string) $orderReference);
    if ($orderReference === '') {
        return 0;
    }

    $statement = impacktaPrepareAndExecute(
        $link,
        "SELECT id_order FROM `" . $prefix . "orders` WHERE reference = ? ORDER BY id_order DESC LIMIT 1",
        array($orderReference),
        's'
    );
    if (!$statement) {
        return 0;
    }

    $statement->bind_result($resolvedOrderId);
    $result = $statement->fetch() ? (int) $resolvedOrderId : 0;
    $statement->close();

    return $result;
}

function impacktaChangeOrderState($orderId, $stateId)
{
    // Cambia el estado del pedido usando OrderHistory para respetar la logica de PrestaShop y emails.
    $debugContext = array(
        'order_id' => (int) $orderId,
        'target_state_id' => (int) $stateId,
    );

    impacktaSetDebugStep('change_state_inicio', $debugContext);

    try {
        require('../../../config/config.inc.php');
        require_once '../../../classes/order/OrderHistory.php';
        require_once '../../../classes/order/Order.php';

        impacktaSetDebugStep('change_state_dependencias_cargadas', $debugContext);

        $order = new Order((int) $orderId);
        if (!Validate::isLoadedObject($order)) {
            impacktaLogStateEvent('pedido_no_encontrado', $debugContext);

            return false;
        }

        $beforeStateId = (int) $order->current_state;
        $historyBeforeStateId = impacktaGetLatestHistoryStateId($orderId);
        $debugContext['before_state_id'] = $beforeStateId;
        $debugContext['history_before_state_id'] = $historyBeforeStateId;

        impacktaSetDebugStep('change_state_pedido_cargado', $debugContext);

        $history = new OrderHistory();
        $history->id_order = (int) $orderId;
        $history->id_employee = impacktaGetFallbackEmployeeId();
        $debugContext['history_employee_id'] = (int) $history->id_employee;

        impacktaSetDebugStep('change_state_history_preparado', $debugContext);

        $history->changeIdOrderState((int) $stateId, $order);
        impacktaSetDebugStep('changeIdOrderState_ejecutado', $debugContext);

        impacktaBootKernel();
        impacktaSetDebugStep('change_state_kernel_boot', $debugContext);

        if (method_exists($history, 'addWithemail')) {
            $debugContext['history_add_method'] = 'addWithemail';
            impacktaSetDebugStep('change_state_add_history', $debugContext);
            $result = (bool) $history->addWithemail(true, array(), Context::getContext());
        } elseif (method_exists($history, 'addWithEmail')) {
            $debugContext['history_add_method'] = 'addWithEmail';
            impacktaSetDebugStep('change_state_add_history', $debugContext);
            $result = (bool) $history->addWithEmail(true, array());
        } else {
            $debugContext['history_add_method'] = 'add';
            impacktaSetDebugStep('change_state_add_history', $debugContext);
            $result = (bool) $history->add();
        }

        $debugContext['history_add_result'] = $result ? 'ok' : 'error';
        $debugContext['history_object_id'] = isset($history->id) ? (int) $history->id : 0;
        impacktaSetDebugStep('change_state_history_guardado', $debugContext);

        $refreshedOrder = new Order((int) $orderId);
        $afterStateId = Validate::isLoadedObject($refreshedOrder) ? (int) $refreshedOrder->current_state : 0;
        $latestHistoryStateId = impacktaGetLatestHistoryStateId($orderId);
        $historyStateOk = ((int) $latestHistoryStateId === (int) $stateId);

        $debugContext['after_state_id'] = $afterStateId;
        $debugContext['latest_history_state_id'] = $latestHistoryStateId;
        impacktaSetDebugStep('change_state_verificacion_inicial', $debugContext);

        if (!$historyStateOk) {
            $historyStateOk = impacktaEnsureOrderHistoryState($orderId, $stateId);
            $latestHistoryStateId = impacktaGetLatestHistoryStateId($orderId);
            $debugContext['latest_history_state_id'] = $latestHistoryStateId;
            $debugContext['history_fallback_result'] = $historyStateOk ? 'ok' : 'error';
            impacktaSetDebugStep('change_state_fallback_historial', $debugContext);
        }

        $historyCount = (int) Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_history` WHERE id_order = ' . (int) $orderId
        );
        $stateApplied = ((int) $afterStateId === (int) $stateId) && ((int) $latestHistoryStateId === (int) $stateId);

        $debugContext['history_count'] = $historyCount;
        $debugContext['state_applied'] = $stateApplied ? 'yes' : 'no';

        impacktaLogStateEvent('cambio_estado_resultado', array(
            'order_id' => (int) $orderId,
            'before_state_id' => $beforeStateId,
            'target_state_id' => (int) $stateId,
            'after_state_id' => $afterStateId,
            'latest_history_state_id' => $latestHistoryStateId,
            'history_count' => $historyCount,
            'history_object_id' => isset($history->id) ? (int) $history->id : 0,
            'result' => $result ? 'ok' : 'error',
            'state_applied' => $stateApplied ? 'yes' : 'no',
        ));
        impacktaSetDebugStep('change_state_fin', $debugContext);

        return $stateApplied;
    } catch (Exception $exception) {
        $debugContext['exception_message'] = $exception->getMessage();
        $debugContext['exception_file'] = $exception->getFile();
        $debugContext['exception_line'] = (int) $exception->getLine();
        impacktaLogStateEvent('change_state_exception', $debugContext);

        return false;
    }
}

function impacktaGetFallbackEmployeeId()
{
    // Obtiene un empleado valido para registrar historiales cuando la llamada viene sin sesion BO.
    $context = Context::getContext();
    if (Validate::isLoadedObject($context->employee)) {
        return (int) $context->employee->id;
    }

    return (int) Db::getInstance()->getValue(
        'SELECT id_employee FROM `' . _DB_PREFIX_ . 'employee` ORDER BY id_employee ASC'
    );
}

function impacktaGetLatestHistoryStateId($orderId)
{
    // Consulta el ultimo estado guardado en historial para verificar cambios y poder restaurar estados.
    return (int) Db::getInstance()->getValue(
        'SELECT id_order_state
        FROM `' . _DB_PREFIX_ . 'order_history`
        WHERE id_order = ' . (int) $orderId . '
        ORDER BY date_add DESC, id_order_history DESC'
    );
}

function impacktaEnsureOrderHistoryState($orderId, $stateId)
{
    // Refuerzo: si changeIdOrderState no deja historial coherente, inserta una entrada minima.
    require_once '../../../classes/order/OrderHistory.php';

    $latestHistoryStateId = impacktaGetLatestHistoryStateId($orderId);
    if ((int) $latestHistoryStateId === (int) $stateId) {
        return true;
    }

    $history = new OrderHistory();
    $history->id_order = (int) $orderId;
    $history->id_order_state = (int) $stateId;
    $history->id_employee = impacktaGetFallbackEmployeeId();

    $result = (bool) $history->add(true);
    if ($result && method_exists('Order', 'cleanHistoryCache')) {
        Order::cleanHistoryCache();
    }

    impacktaLogStateEvent('fallback_historial_estado', array(
        'order_id' => (int) $orderId,
        'target_state_id' => (int) $stateId,
        'latest_history_state_id_before' => (int) $latestHistoryStateId,
        'history_object_id' => isset($history->id) ? (int) $history->id : 0,
        'result' => $result ? 'ok' : 'error',
    ));

    return $result;
}

function impacktaUpdateTrackingNumber($orderId, $trackingNumber)
{
    // Guarda el albaran/tracking en order_carrier para que PS lo muestre en pedido y comunicaciones.
    require('../../../config/config.inc.php');
    require_once '../../../classes/order/OrderCarrier.php';
    require_once '../../../classes/order/Order.php';

    $order = new Order((int) $orderId);
    $orderCarrierId = (int) $order->getIdOrderCarrier();

    if ($orderCarrierId <= 0) {
        return false;
    }

    $orderCarrier = new OrderCarrier($orderCarrierId);
    $orderCarrier->tracking_number = (string) $trackingNumber;

    return (bool) $orderCarrier->save();
}

function impacktaRequestProducts($link, $prefix, $idLang)
{
    // Exporta productos y combinaciones con atributos para que Impackta pueda mapear articulos.
    $response = array();
    $sql = "SELECT pa.id_product, pa.id_product_attribute,
        COALESCE(
            (SELECT pl.name
                FROM `" . $prefix . "product_lang` pl
                WHERE pl.id_product = pa.id_product AND pl.id_lang = ?
                LIMIT 1),
            (SELECT pl.name
                FROM `" . $prefix . "product_lang` pl
                WHERE pl.id_product = pa.id_product
                ORDER BY pl.id_lang ASC
                LIMIT 1),
            ''
        ) AS name
        FROM `" . $prefix . "product_attribute` pa
        WHERE EXISTS (
            SELECT 1
            FROM `" . $prefix . "product_lang` pl
            WHERE pl.id_product = pa.id_product
        )
        GROUP BY pa.id_product, pa.id_product_attribute
        UNION
        SELECT p.id_product, 0,
        COALESCE(
            (SELECT pl.name
                FROM `" . $prefix . "product_lang` pl
                WHERE pl.id_product = p.id_product AND pl.id_lang = ?
                LIMIT 1),
            (SELECT pl.name
                FROM `" . $prefix . "product_lang` pl
                WHERE pl.id_product = p.id_product
                ORDER BY pl.id_lang ASC
                LIMIT 1),
            ''
        ) AS name
        FROM `" . $prefix . "product` p
        WHERE EXISTS (
            SELECT 1
            FROM `" . $prefix . "product_lang` pl
            WHERE pl.id_product = p.id_product
        )
        AND p.id_product NOT IN (SELECT pa.id_product FROM `" . $prefix . "product_attribute` pa)";

    $statement = impacktaPrepareAndExecute($link, $sql, array($idLang, $idLang), 'ii');
    if (!$statement) {
        impacktaJsonResponse(array('error' => 'No se pudieron obtener los productos.'), 500);
    }

    $statement->bind_result($productId, $productAttributeId, $name);
    while ($statement->fetch()) {
        array_push($response, array((int) $productId, (int) $productAttributeId, $name));
    }
    $statement->close();

    foreach ($response as $index => $productRow) {
        $attributes = array();
        if ((int) $productRow[1] > 0) {
            $sql = "SELECT
                COALESCE(
                    (SELECT agl.name
                        FROM `" . $prefix . "attribute_group_lang` agl
                        WHERE agl.id_attribute_group = a.id_attribute_group AND agl.id_lang = ?
                        LIMIT 1),
                    (SELECT agl.name
                        FROM `" . $prefix . "attribute_group_lang` agl
                        WHERE agl.id_attribute_group = a.id_attribute_group
                        ORDER BY agl.id_lang ASC
                        LIMIT 1),
                    ''
                ) AS attribute_group_name,
                COALESCE(
                    (SELECT al.name
                        FROM `" . $prefix . "attribute_lang` al
                        WHERE al.id_attribute = a.id_attribute AND al.id_lang = ?
                        LIMIT 1),
                    (SELECT al.name
                        FROM `" . $prefix . "attribute_lang` al
                        WHERE al.id_attribute = a.id_attribute
                        ORDER BY al.id_lang ASC
                        LIMIT 1),
                    ''
                ) AS attribute_name
                FROM `" . $prefix . "attribute` a
                WHERE a.id_attribute IN (
                    SELECT pac.id_attribute
                    FROM `" . $prefix . "product_attribute_combination` pac
                    WHERE pac.id_product_attribute = ?
                )";

            $statement = impacktaPrepareAndExecute($link, $sql, array($idLang, $idLang, (int) $productRow[1]), 'iii');
            if (!$statement) {
                impacktaJsonResponse(array('error' => 'No se pudieron obtener los atributos del producto.'), 500);
            }

            $statement->bind_result($attributeGroupName, $attributeName);
            while ($statement->fetch()) {
                array_push($attributes, array($attributeGroupName, $attributeName));
            }
            $statement->close();
        }

        array_push($response[$index], $attributes);
    }

    impacktaJsonResponse($response);
}

function impacktaRenderProductsList()
{
    // Vista HTML auxiliar para revisar visualmente el catalogo que devuelve tipo=products.
    $url = impacktaGetRequestScheme() . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $url = str_replace('products_list', 'products', $url);
    $products = json_decode(file_get_contents($url));

    echo '<style>table{width:100%;border-collapse:collapse;}th,td{border:1px solid black;padding:5px;}</style>';
    echo '<table><thead><tr><th>Codigo</th><th>Nombre</th><th>Propiedades</th></tr></thead><tbody>';

    if (is_array($products)) {
        foreach ($products as $product) {
            $code = $product[0] . $product[1];
            $name = $product[2];
            $properties = '';

            if (!empty($product[3]) && is_array($product[3])) {
                foreach ($product[3] as $attribute) {
                    $name .= ' - ' . $attribute[0] . ' ' . $attribute[1];
                    $properties .= '- ' . $attribute[0] . ' ' . $attribute[1] . '<br/>';
                }
            }

            echo '<tr>';
            echo '<td>' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td>';
            echo '<td>' . $properties . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody></table>';
    exit;
}

$tipo = impacktaGetString('tipo');
$requestedOrderId = impacktaGetInt('id');
$requestedOrderReference = impacktaGetString('reference');
$requestedFromDate = impacktaGetString('desde');
$requestedToDate = impacktaGetString('hasta');
$requestedSortDirection = impacktaGetString('orden');

// Router del endpoint externo: Impackta llama con tipo=... para consultar pedidos,
// marcar enviados/entregados, borrar tracking, cambiar estados o listar productos.
if ($requestedSortDirection === '') {
    $requestedSortDirection = impacktaGetString('order');
}

if ($tipo === '') {
    if ($requestedOrderId > 0) {
        $tipo = 'order';
    } elseif ($requestedOrderReference !== '') {
        $tipo = 'order';
    } elseif ($requestedFromDate !== '' && $requestedToDate !== '') {
        $tipo = 'orders';
    } else {
        $tipo = 'all_orders';
    }
}

impacktaLogStateEvent('api_request', array(
    'tipo' => $tipo,
    'id' => $requestedOrderId,
    'reference' => $requestedOrderReference,
    'desde' => $requestedFromDate,
    'hasta' => $requestedToDate,
    'order' => $requestedSortDirection,
    'codigoCliente' => impacktaGetString('codigoCliente'),
));

if ($tipo === '') {
    impacktaTextResponse('Faltan datos necesarios', 400);
}

if (empty($uid) || empty($idCliente)) {
    impacktaTextResponse('Modulo no configurado', 500);
}

$requestClientCode = impacktaGetString('codigoCliente');
$requestApiKey = impacktaGetString('claveApi');
if (
    $requestClientCode === ''
    || $requestApiKey === ''
    || !hash_equals((string) $idCliente, (string) $requestClientCode)
    || !hash_equals((string) $uid, (string) $requestApiKey)
) {
    impacktaTextResponse('Credenciales invalidas', 403);
}

if ($tipo === 'orders') {
    $fromDate = impacktaNormalizeDateTime($requestedFromDate, false);
    $toDate = impacktaNormalizeDateTime($requestedToDate, true);
    if ($fromDate === false || $toDate === false) {
        impacktaJsonResponse(array('error' => 'Rango de fechas no valido.'), 400);
    }

    impacktaRequestOrders($link, $prefix, (int) $id_lang, $fromDate, $toDate, $requestedSortDirection);
}

if ($tipo === 'all_orders') {
    impacktaRequestOrders($link, $prefix, (int) $id_lang, '', '', $requestedSortDirection);
}

if ($tipo === 'order') {
    $orderId = $requestedOrderId;
    $orderReference = $requestedOrderReference;
    if ($orderId <= 0 && $orderReference === '') {
        impacktaJsonResponse(array('error' => 'Pedido no valido.'), 400);
    }

    impacktaRequestOrder($link, $prefix, (int) $id_lang, $orderId, $orderReference);
}

if ($tipo === 'enviado') {
    $orderId = impacktaResolveOrderId($link, $prefix, impacktaGetInt('id'), impacktaGetString('reference'));
    $serviceCode = impacktaGetString('serviciocompleto');
    $postcode = impacktaGetString('cp');
    $stateId = impacktaFindStateId($link, $prefix, (int) $id_lang, 'Enviado');

    impacktaLogStateEvent('solicitud_enviado', array(
        'order_id' => (int) $orderId,
        'reference' => impacktaGetString('reference'),
        'service_code' => $serviceCode,
        'postcode' => $postcode,
        'resolved_state_id' => (int) $stateId,
    ));

    if ($orderId <= 0 || $stateId <= 0) {
        impacktaTextResponse('Parametros invalidos', 400);
    }

    if ($serviceCode !== '') {
        $trackingNumber = $postcode === '' ? $serviceCode : $serviceCode . '/' . $postcode;
        if (!impacktaUpdateTrackingNumber($orderId, $trackingNumber)) {
            impacktaTextResponse('No se pudo actualizar el tracking', 500);
        }
    }

    if (!impacktaChangeOrderState($orderId, $stateId)) {
        impacktaTextResponse('No se pudo actualizar el estado del pedido', 500);
    }

    impacktaTextResponse('*done*');
}

if ($tipo === 'borrar') {
    $orderId = impacktaResolveOrderId($link, $prefix, impacktaGetInt('id'), impacktaGetString('reference'));
    if ($orderId <= 0) {
        impacktaTextResponse('Pedido no valido', 400);
    }

    $statement = impacktaPrepareAndExecute(
        $link,
        "UPDATE `" . $prefix . "order_carrier` SET tracking_number = '' WHERE id_order = ?",
        array($orderId),
        'i'
    );
    if (!$statement) {
        impacktaTextResponse('No se pudo borrar el tracking', 500);
    }
    $statement->close();

    $statement = impacktaPrepareAndExecute(
        $link,
        "SELECT id_order_state FROM `" . $prefix . "order_history` WHERE id_order = ? ORDER BY date_add DESC, id_order_history DESC LIMIT 1 OFFSET 1",
        array($orderId),
        'i'
    );
    if (!$statement) {
        impacktaTextResponse('No se pudo restaurar el estado', 500);
    }

    $statement->bind_result($previousStateId);
    if (!$statement->fetch()) {
        $statement->close();
        impacktaTextResponse('No hay estado anterior', 404);
    }
    $statement->close();

    $statement = impacktaPrepareAndExecute(
        $link,
        "UPDATE `" . $prefix . "orders` SET current_state = ? WHERE id_order = ?",
        array((int) $previousStateId, $orderId),
        'ii'
    );
    if (!$statement) {
        impacktaTextResponse('No se pudo actualizar el pedido', 500);
    }
    $statement->close();

    $statement = impacktaPrepareAndExecute(
        $link,
        "INSERT INTO `" . $prefix . "order_history` (id_employee, id_order, id_order_state, date_add)
        VALUES ((SELECT id_employee FROM `" . $prefix . "employee` ORDER BY id_employee ASC LIMIT 1), ?, ?, NOW())",
        array($orderId, (int) $previousStateId),
        'ii'
    );
    if (!$statement) {
        impacktaTextResponse('No se pudo registrar el cambio de estado', 500);
    }
    $statement->close();

    impacktaTextResponse('*done*');
}

if ($tipo === 'entregado') {
    $orderId = impacktaResolveOrderId($link, $prefix, impacktaGetInt('id'), impacktaGetString('reference'));
    $stateId = impacktaFindStateId($link, $prefix, (int) $id_lang, 'Entregado');

    impacktaLogStateEvent('solicitud_entregado', array(
        'order_id' => (int) $orderId,
        'reference' => impacktaGetString('reference'),
        'resolved_state_id' => (int) $stateId,
    ));

    if ($orderId <= 0 || $stateId <= 0) {
        impacktaTextResponse('Parametros invalidos', 400);
    }

    if (!impacktaChangeOrderState($orderId, $stateId)) {
        impacktaTextResponse('No se pudo actualizar el estado del pedido', 500);
    }

    impacktaTextResponse('*done*');
}

if ($tipo === 'products') {
    impacktaRequestProducts($link, $prefix, (int) $id_lang);
}

if ($tipo === 'products_list') {
    impacktaRenderProductsList();
}

if ($tipo === 'nuevo_estado') {
    $orderId = impacktaResolveOrderId($link, $prefix, impacktaGetInt('id'), impacktaGetString('reference'));
    $stateName = impacktaGetString('estado');
    $stateId = impacktaFindStateId($link, $prefix, (int) $id_lang, $stateName);

    impacktaLogStateEvent('solicitud_nuevo_estado', array(
        'order_id' => (int) $orderId,
        'reference' => impacktaGetString('reference'),
        'requested_state_name' => $stateName,
        'resolved_state_id' => (int) $stateId,
    ));

    if ($orderId <= 0 || $stateName === '' || $stateId <= 0) {
        impacktaTextResponse('Parametros invalidos', 400);
    }

    if (!impacktaChangeOrderState($orderId, $stateId)) {
        impacktaTextResponse('No se pudo actualizar el estado del pedido', 500);
    }

    impacktaTextResponse('*done*');
}

if ($tipo === 'actualizar_tracking') {
    $orderId = impacktaGetInt('id');
    $serviceCode = impacktaGetString('serviciocompleto');
    if ($orderId <= 0 || $serviceCode === '') {
        impacktaTextResponse('Parametros invalidos', 400);
    }

    if (!impacktaUpdateTrackingNumber($orderId, $serviceCode)) {
        impacktaTextResponse('No se pudo actualizar el tracking', 500);
    }

    impacktaTextResponse('*done*');
}

impacktaTextResponse('Tipo no valido', 400);
