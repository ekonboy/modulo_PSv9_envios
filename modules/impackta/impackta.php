<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class impackta extends Module
{
    const ADMIN_CONTROLLER = 'AdminImpacktashipping';

    /**
     * Legacy tab declaration kept for compatibility with older PrestaShop versions.
     *
     * @var array<int, array<string, mixed>>
     */
    public $tabs = array(
        array(
            'name' => 'Impackta',
            'class_name' => 'AdminImpacktashipping',
            'visible' => true,
            'parent_class_name' => 'AdminParentOrders',
        ),
    );

    public function __construct()
    {
        $this->name = 'impackta';
        $this->tab = 'shipping_logistics';
        $this->version = '2.0';
        $this->author = 'gabriel rese';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array(
            'min' => '1.7.8.0',
            'max' => _PS_VERSION_,
        );

        parent::__construct();

        $this->displayName = $this->l('Impackta');
        $this->description = $this->l('Gestion de envios Impackta.');
        $this->confirmUninstall = $this->l('Seguro que desea desinstalar el modulo de Impackta?');
    }

    public function install()
    {
        // Instalacion completa del modulo: valida dependencias, crea la tabla propia,
        // registra la pestaña de administracion, crea los transportistas Impackta y
        // engancha los hooks necesarios para cargar assets y reaccionar a transportistas.
        if (!extension_loaded('curl')) {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');

            return false;
        }

        return parent::install()
            && $this->installDatabase()
            && $this->installAdminTab()
            && $this->installCarriers()
            && $this->registerHook('backOfficeHeader')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('updateCarrier');
    }

    public function uninstall()
    {
        // Desinstalacion ordenada: elimina primero la pestaña custom y la tabla propia
        // para dejar el modulo sin residuos funcionales antes de llamar al core.
        return $this->uninstallAdminTab()
            && $this->uninstallCarriers()
            && $this->uninstallDatabase()
            && parent::uninstall();
    }

    public function getContent()
    {
        // En PrestaShop 9 se centraliza la configuracion en el controlador admin propio.
        // Si se entra desde "Configurar" en modulos, redirigimos a esa pantalla.
        $adminLink = $this->context->link->getAdminLink(self::ADMIN_CONTROLLER);

        Tools::redirectAdmin($adminLink);

        return '';
    }

    public function hookBackOfficeHeader()
    {
        $this->loadBackOfficeAssets();
    }

    public function hookDisplayBackOfficeHeader()
    {
        $this->loadBackOfficeAssets();
    }

    public function hookUpdateCarrier($params)
    {
        // Reservado para mapear ids si PrestaShop duplica un transportista al editarlo.
        // De momento los transportistas Impackta se identifican por nombre en la instalacion.
    }

    /**
     * @return array<int, array<string, string>>
     */
    protected function getCarrierDefinitions()
    {
        // Catalogo minimo de servicios que se crean como transportistas de PrestaShop.
        // Si Impackta cambia nombres comerciales, este es el punto unico de mantenimiento.
        // no hace falta desinstalar el modulo, los transportistas no se copian si ya existen.
        return array(
            array(
                'name' => 'Impackta 10 horas',
                'delay' => 'Entrega el dia siguiente antes de las 10h.',
            ),
            array(
                'name' => 'Impackta 14 horas',
                'delay' => 'Entrega el dia siguiente antes de las 14h.',
            ),
            array(
                'name' => 'Impackta 24 horas',
                'delay' => 'Entrega el dia siguiente antes de las 20h.',
            ),
            array(
                'name' => 'Impackta Economy',
                'delay' => 'Entrega en 24/72 horas.',
            ),
            array(
                'name' => 'Impackta Euro Parcel',
                'delay' => 'Entregas internacionales',
            ),
        );
    }

    protected function installDatabase()
    {
        // Crea la tabla de configuracion del modulo: guid de la tienda e id de cliente Impackta.
        return (bool) include dirname(__FILE__) . '/sql/install.php';
    }

    protected function uninstallDatabase()
    {
        // Elimina la tabla propia durante la desinstalacion completa del modulo.
        return (bool) include dirname(__FILE__) . '/sql/uninstall.php';
    }

    protected function installAdminTab()
    {
        // Registra la pantalla "Impackta" dentro del menu de pedidos para configurar la URL API.
        $existingTabId = (int) Tab::getIdFromClassName(self::ADMIN_CONTROLLER);
        if ($existingTabId > 0) {
            return true;
        }

        $tab = new Tab();
        $tab->active = true;
        $tab->class_name = self::ADMIN_CONTROLLER;
        $tab->module = $this->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminParentOrders');

        foreach (Language::getLanguages(false) as $language) {
            $tab->name[(int) $language['id_lang']] = 'Impackta';
        }

        return (bool) $tab->add();
    }

    protected function uninstallAdminTab()
    {
        // Borra la pestaña custom si existe; si ya fue eliminada manualmente no bloquea el uninstall.
        $tabId = (int) Tab::getIdFromClassName(self::ADMIN_CONTROLLER);
        if ($tabId <= 0) {
            return true;
        }

        $tab = new Tab($tabId);

        return (bool) $tab->delete();
    }

    protected function installCarriers()
    {
        // Crea solo los transportistas que no existan para que reinstalar el modulo sea idempotente.
        foreach ($this->getCarrierDefinitions() as $definition) {
            if ($this->findCarrierIdByName($definition['name']) > 0) {
                continue;
            }

            $carrier = $this->createCarrier($definition['name'], $definition['delay']);
            if (!$carrier) {
                return false;
            }
        }

        return true;
    }

    protected function uninstallCarriers()
    {
        // Limpieza funcional de transportistas creados por el modulo. Se marcan como borrados
        // siguiendo el patron habitual de PrestaShop para no romper pedidos historicos.
        foreach ($this->getCarrierDefinitions() as $definition) {
            Db::getInstance()->update(
                'carrier',
                array('deleted' => 1, 'active' => 0),
                'name = "' . pSQL($definition['name']) . '" AND external_module_name = "' . pSQL($this->name) . '"'
            );
        }

        return true;
    }

    protected function findCarrierIdByName($name)
    {
        // Evita duplicar servicios Impackta cuando el modulo se reinstala o se actualiza.
        $query = new DbQuery();
        $query->select('id_carrier');
        $query->from('carrier');
        $query->where('name = "' . pSQL($name) . '"');
        $query->where('deleted = 0');

        return (int) Db::getInstance()->getValue($query);
    }

    /**
     * @param string $name
     * @param string $delay
     *
     * @return Carrier|false
     */
    protected function createCarrier($name, $delay)
    {
        // Alta del transportista Impackta con rangos amplios y URL de seguimiento.
        // Las tarifas quedan abiertas para que el comerciante las ajuste desde PrestaShop.
        $carrier = new Carrier();
        $carrier->name = $this->l($name);
        $carrier->is_module = true;
        $carrier->external_module_name = $this->name;
        $carrier->active = 1;
        $carrier->deleted = 0;
        $carrier->range_behavior = 0;
        $carrier->need_range = 1;
        $carrier->shipping_external = false;
        $carrier->shipping_method = 2;
        $carrier->url = 'https://seguimiento.impackta.com/?albaran=@';

        foreach (Language::getLanguages(false) as $language) {
            $carrier->delay[(int) $language['id_lang']] = $this->l($delay);
        }

        if (!$carrier->add()) {
            return false;
        }

        @copy(dirname(__FILE__) . '/views/img/carrier_image.jpg', _PS_SHIP_IMG_DIR_ . '/' . (int) $carrier->id . '.jpg');

        $this->addCarrierZones($carrier);
        $this->addCarrierGroups($carrier);
        $this->addCarrierRanges($carrier);

        return $carrier;
    }

    protected function addCarrierGroups(Carrier $carrier)
    {
        // Hace visible el transportista para todos los grupos de clientes activos.
        $groupsIds = array();
        $groups = Group::getGroups((int) Context::getContext()->language->id);

        foreach ($groups as $group) {
            $groupsIds[] = (int) $group['id_group'];
        }

        return $carrier->setGroups($groupsIds);
    }

    protected function addCarrierRanges(Carrier $carrier)
    {
        // Crea rangos genericos de precio y peso; son necesarios para que PS acepte el carrier.
        $rangePrice = new RangePrice();
        $rangePrice->id_carrier = (int) $carrier->id;
        $rangePrice->delimiter1 = '0';
        $rangePrice->delimiter2 = '10000';

        if (!$rangePrice->add()) {
            return false;
        }

        $rangeWeight = new RangeWeight();
        $rangeWeight->id_carrier = (int) $carrier->id;
        $rangeWeight->delimiter1 = '0';
        $rangeWeight->delimiter2 = '10000';

        return (bool) $rangeWeight->add();
    }

    protected function addCarrierZones(Carrier $carrier)
    {
        // Activa el transportista en todas las zonas para no bloquear envios por zona al instalar.
        $zones = Zone::getZones();

        foreach ($zones as $zone) {
            $carrier->addZone((int) $zone['id_zone']);
        }

        return true;
    }

    protected function loadBackOfficeAssets()
    {
        // Carga CSS/JS solo en la configuracion del modulo o en su controlador admin.
        $controller = (string) Tools::getValue('controller');
        $configure = (string) Tools::getValue('configure');

        if (
            $controller !== self::ADMIN_CONTROLLER
            && !($controller === 'AdminModules' && $configure === $this->name)
        ) {
            return;
        }

        $this->context->controller->addJS($this->_path . 'views/js/back.js');
        $this->context->controller->addCSS($this->_path . 'views/css/back.css');
    }
}
