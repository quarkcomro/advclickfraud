<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'advclickfraud/classes/ClickFraudLog.php';

class AdvClickFraud extends Module
{
    public function __construct()
    {
        $this->name = 'advclickfraud';
        $this->tab = 'analytics_stats';
        $this->version = '1.0.0';
        $this->author = 'Expert Developer';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Advanced Click Fraud Detector and Analytics');
        $this->description = $this->l('Detectează comportamentul malițios, boții și click-urile repetitive din reclame.');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.9.9'];
    }

    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('displayHeader') ||
            !$this->registerHook('displayBackOfficeHeader') ||
            !$this->createTables()
        ) {
            return false;
        }
        
        Configuration::updateValue('ADVCLICKFRAUD_CLICK_LIMIT', 3); // Max click-uri permise per IP
        Configuration::updateValue('ADVCLICKFRAUD_TIME_WINDOW', 3600); // Fereastra de timp în secunde (1 oră)
        
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() || !$this->dropTables()) {
            return false;
        }
        
        Configuration::deleteByName('ADVCLICKFRAUD_CLICK_LIMIT');
        Configuration::deleteByName('ADVCLICKFRAUD_TIME_WINDOW');
        
        return true;
    }

    private function createTables()
    {
        $queries = [
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "adv_click_fraud_logs` (
                `id_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip_address` VARCHAR(45) NOT NULL,
                `gclid` VARCHAR(255) NULL,
                `utm_source` VARCHAR(100) NULL,
                `user_agent` TEXT NOT NULL,
                `referrer` TEXT NULL,
                `country` VARCHAR(3) NULL,
                `click_count` INT UNSIGNED DEFAULT 1,
                `is_bot` TINYINT(1) DEFAULT 0,
                `is_vpn` TINYINT(1) DEFAULT 0,
                `fraud_score` INT UNSIGNED DEFAULT 0,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_log`),
                INDEX `idx_ip` (`ip_address`),
                INDEX `idx_gclid` (`gclid`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "adv_click_fraud_sessions` (
                `id_session` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip_address` VARCHAR(45) NOT NULL,
                `session_token` VARCHAR(64) NOT NULL,
                `duration` INT UNSIGNED DEFAULT 0,
                `pages_visited` TEXT NULL,
                `mouse_movements` INT UNSIGNED DEFAULT 0,
                `key_presses` INT UNSIGNED DEFAULT 0,
                `screen_resolution` VARCHAR(30) NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_session`),
                UNIQUE KEY `idx_token` (`session_token`),
                INDEX `idx_ip_sess` (`ip_address`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8mb4;"
        ];

        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }
        return true;
    }

    private function dropTables()
    {
        return Db::getInstance()->execute("DROP TABLE IF EXISTS `" . _DB_PREFIX_ . "adv_click_fraud_logs`, `" . _DB_PREFIX_ . "adv_click_fraud_sessions`;");
    }

    public function getContent()
    {
        $output = '';
        
        if (Tools::isSubmit('submit_adv_config')) {
            Configuration::updateValue('ADVCLICKFRAUD_CLICK_LIMIT', (int)Tools::getValue('ADVCLICKFRAUD_CLICK_LIMIT'));
            Configuration::updateValue('ADVCLICKFRAUD_TIME_WINDOW', (int)Tools::getValue('ADVCLICKFRAUD_TIME_WINDOW'));
            $output .= $this->displayConfirmation($this->l('Setările au fost salvate cu succes.'));
        }

        $this->context->smarty->assign([
            'logs' => ClickFraudLog::getAllLogs(50),
            'stats' => ClickFraudLog::getGlobalStats(),
            'form_action' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            'click_limit' => Configuration::get('ADVCLICKFRAUD_CLICK_LIMIT'),
            'time_window' => Configuration::get('ADVCLICKFRAUD_TIME_WINDOW')
        ]);

        // Afișarea URL-ului de Export în Dashboard
        $secure_key = Tools::encrypt($this->name);
        $export_link = $this->context->link->getModuleLink('advclickfraud', 'export', ['secure_key' => $secure_key]);
        
        $this->context->smarty->assign([
            'logs' => ClickFraudLog::getAllLogs(50),
            'stats' => ClickFraudLog::getGlobalStats(),
            'form_action' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            'click_limit' => Configuration::get('ADVCLICKFRAUD_CLICK_LIMIT'),
            'time_window' => Configuration::get('ADVCLICKFRAUD_TIME_WINDOW'),
            'export_link' => $export_link // Trimitem link-ul către interfață
        ]);
        
        return $output . $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dashboard.tpl');
    }

    public function hookDisplayHeader()
    {
        // Preluăm parametrii de tracking publicitar direct din URL global securizat
        $gclid = Tools::getValue('gclid');
        $fbclid = Tools::getValue('fbclid');
        $utm_source = Tools::getValue('utm_source');

        // Verificăm dacă vizita provine dintr-o reclamă plătită
        $is_ad_click = (!empty($gclid) || !empty($fbclid) || !empty($utm_source) || ($utm_source == 'google'));

        // Generăm un token unic de sesiune pentru securitate AJAX și izolare tracking
        if (!isset($this->context->cookie->acf_session_token)) {
            $session_token = Bin2hex(random_bytes(32));
            $this->context->cookie->acf_session_token = $session_token;
            $this->context->cookie->write();
        } else {
            $session_token = $this->context->cookie->acf_session_token;
        }

        // Executăm logica de analiză severă backend la prima încărcare dacă e click din Ads
        if ($is_ad_click) {
            ClickFraudLog::logAdClick(
                Tools::getRemoteAddr(),
                $gclid ? $gclid : $fbclid,
                $utm_source ? $utm_source : ($gclid ? 'google_ads' : 'facebook_ads')
            );
        }

        $this->context->smarty->assign([
            'acf_ajax_link' => $this->context->link->getModuleLink('advclickfraud', 'track'),
            'acf_token' => $session_token,
            'current_page' => Tools::getHttpHost(true) . $_SERVER['REQUEST_URI']
        ]);

        return $this->display(__FILE__, 'views/templates/hook/header.tpl');
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        }
    }
}
