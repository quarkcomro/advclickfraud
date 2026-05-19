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
        $this->version = '1.2.0';
        $this->author = 'Expert Developer';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Advanced Click Fraud Detector and Analytics');
        $this->description = $this->l('Detectează comportamentul malițios, scraperii de prețuri și click-urile repetitive.');
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
        
        // Salvare valori implicite în baza de date (Fără hardcodare)
        Configuration::updateValue('ADVCLICKFRAUD_CLICK_LIMIT', 3);
        Configuration::updateValue('ADVCLICKFRAUD_TIME_WINDOW', 3600);
        Configuration::updateValue('ADVCLICKFRAUD_MIN_DURATION', 5);
        Configuration::updateValue('ADVCLICKFRAUD_MAX_DURATION', 30);
        Configuration::updateValue('ADVCLICKFRAUD_RETENTION_DAYS', 30);
        Configuration::updateValue('ADVCLICKFRAUD_SCRAPE_LIMIT', 15); // Max pagini produs / minut
        
        return true;
    }

    public function uninstall()
    {
        if (!parent::uninstall() || !$this->dropTables()) {
            return false;
        }
        
        Configuration::deleteByName('ADVCLICKFRAUD_CLICK_LIMIT');
        Configuration::deleteByName('ADVCLICKFRAUD_TIME_WINDOW');
        Configuration::deleteByName('ADVCLICKFRAUD_MIN_DURATION');
        Configuration::deleteByName('ADVCLICKFRAUD_MAX_DURATION');
        Configuration::deleteByName('ADVCLICKFRAUD_RETENTION_DAYS');
        Configuration::deleteByName('ADVCLICKFRAUD_SCRAPE_LIMIT');
        
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
                `click_count` INT UNSIGNED DEFAULT 1,
                `is_bot` TINYINT(1) DEFAULT 0,
                `is_scraper` TINYINT(1) DEFAULT 0,
                `fraud_score` INT UNSIGNED DEFAULT 0,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_log`),
                INDEX `idx_ip` (`ip_address`)
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
            Configuration::updateValue('ADVCLICKFRAUD_MIN_DURATION', (int)Tools::getValue('ADVCLICKFRAUD_MIN_DURATION'));
            Configuration::updateValue('ADVCLICKFRAUD_MAX_DURATION', (int)Tools::getValue('ADVCLICKFRAUD_MAX_DURATION'));
            Configuration::updateValue('ADVCLICKFRAUD_RETENTION_DAYS', (int)Tools::getValue('ADVCLICKFRAUD_RETENTION_DAYS'));
            Configuration::updateValue('ADVCLICKFRAUD_SCRAPE_LIMIT', (int)Tools::getValue('ADVCLICKFRAUD_SCRAPE_LIMIT'));
            $output .= $this->displayConfirmation($this->l('Configurația a fost actualizată fin.'));
        }

        // Curățare automată a logurilor vechi la deschiderea panoului
        ClickFraudLog::cleanOldLogs();

        $secure_key = md5($this->name . _COOKIE_KEY_);
        $export_link = $this->context->link->getModuleLink('advclickfraud', 'export', ['secure_key' => $secure_key]);

        $this->context->smarty->assign([
            'logs' => ClickFraudLog::getAllLogs(50),
            'stats' => ClickFraudLog::getGlobalStats(),
            'form_action' => AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules'),
            'click_limit' => Configuration::get('ADVCLICKFRAUD_CLICK_LIMIT'),
            'time_window' => Configuration::get('ADVCLICKFRAUD_TIME_WINDOW'),
            'min_duration' => Configuration::get('ADVCLICKFRAUD_MIN_DURATION'),
            'max_duration' => Configuration::get('ADVCLICKFRAUD_MAX_DURATION'),
            'retention_days' => Configuration::get('ADVCLICKFRAUD_RETENTION_DAYS'),
            'scrape_limit' => Configuration::get('ADVCLICKFRAUD_SCRAPE_LIMIT'),
            'export_link' => $export_link
        ]);

        return $output . $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dashboard.tpl');
    }

    public function hookDisplayHeader()
    {
        $gclid = Tools::getValue('gclid');
        $fbclid = Tools::getValue('fbclid');
        $utm_source = Tools::getValue('utm_source');
        $ip = Tools::getRemoteAddr();

        // Algoritm de detecție Scraperi: Verificăm dacă vizualizează o pagină de produs
        $is_product_page = ($this->context->controller instanceof ProductController);

        if (!isset($this->context->cookie->acf_session_token)) {
            $session_token = bin2hex(random_bytes(32));
            $this->context->cookie->acf_session_token = $session_token;
            $this->context->cookie->write();
        } else {
            $session_token = $this->context->cookie->acf_session_token;
        }

        // Rulam logica centralizată (atât pentru Ads cât și pentru monitorizare pagini de produs/Scrapers)
        ClickFraudLog::evaluateVisitor($ip, $gclid || $fbclid || $utm_source, $gclid, $utm_source, $is_product_page);

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
