<?php
/**
 * Advanced Click Fraud & Scraper Detector
 * Completely Multistore and Multilanguage compliant for PrestaShop 9.
 */

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
        $this->version = '1.3.0';
        $this->author = 'Expert Developer';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Advanced Click Fraud Detector and Analytics');
        $this->description = $this->l('Detects malicious clicks, price scrapers, and repetitive ad bots.');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => '9.9.9'];
    }

    public function install()
    {
        // Multistore compatibility check during installation
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        if (!parent::install() ||
            !$this->registerHook('displayHeader') ||
            !$this->registerHook('displayBackOfficeHeader') ||
            !$this->createTables()
        ) {
            return false;
        }
        
        // Initializing configurations globally or per shop group context
        $this->updateShopConfig('ADVCLICKFRAUD_CLICK_LIMIT', 3);
        $this->updateShopConfig('ADVCLICKFRAUD_TIME_WINDOW', 3600);
        $this->updateShopConfig('ADVCLICKFRAUD_MIN_DURATION', 5);
        $this->updateShopConfig('ADVCLICKFRAUD_MAX_DURATION', 30);
        $this->updateShopConfig('ADVCLICKFRAUD_RETENTION_DAYS', 30);
        $this->updateShopConfig('ADVCLICKFRAUD_SCRAPE_LIMIT', 15);
        $this->updateShopConfig('ADVCLICKFRAUD_DISPLAY_LIMIT', 20);
        
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
        Configuration::deleteByName('ADVCLICKFRAUD_DISPLAY_LIMIT');
        
        return true;
    }

    private function createTables()
    {
        $queries = [
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "adv_click_fraud_logs` (
                `id_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
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
                INDEX `idx_ip_shop` (`ip_address`, `id_shop`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "adv_click_fraud_sessions` (
                `id_session` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL DEFAULT 1,
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
                UNIQUE KEY `idx_token_shop` (`session_token`, `id_shop`),
                INDEX `idx_ip_sess_shop` (`ip_address`, `id_shop`)
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

    private function updateShopConfig($key, $value)
    {
        $shopContext = $this->getShopContext();
        Configuration::updateValue($key, $value, false, $shopContext['id_shop_group'], $shopContext['id_shop']);
    }

    private function getShopConfig($key)
    {
        $shopContext = $this->getShopContext();
        return Configuration::get($key, null, $shopContext['id_shop_group'], $shopContext['id_shop']);
    }

    private function getShopContext()
    {
        $id_shop_group = (int)Context::getContext()->shop->id_shop_group;
        $id_shop = (int)Context::getContext()->shop->id;
        return ['id_shop_group' => $id_shop_group, 'id_shop' => $id_shop];
    }

    public function getContent()
    {
        $output = '';
        $shopContext = $this->getShopContext();
        
        if (Tools::isSubmit('submit_adv_config')) {
            Configuration::updateValue('ADVCLICKFRAUD_CLICK_LIMIT', (int)Tools::getValue('ADVCLICKFRAUD_CLICK_LIMIT'), false, $shopContext['id_shop_group'], $shopContext['id_shop']);
            Configuration::updateValue('ADVCLICKFRAUD_TIME_WINDOW', (int)Tools::getValue('ADVCLICKFRAUD_TIME_WINDOW'), false, $shopContext['id_shop_group'], $shopContext['id_shop']);
            Configuration::updateValue('ADVCLICKFRAUD_MIN_DURATION', (int)Tools::getValue('ADVCLICKFRAUD_MIN_DURATION'), false, $shopContext['id_shop_group'], $shopContext['id_shop']);
            Configuration::updateValue('ADVCLICKFRAUD_MAX_DURATION', (int)Tools::getValue('ADVCLICKFRAUD_MAX_DURATION'), false, $shopContext['id_shop_group'], $shopContext['id_shop']);
            Configuration::updateValue('ADVCLICKFRAUD_RETENTION_DAYS', (int)Tools::getValue('ADVCLICKFRAUD_RETENTION_DAYS'), false, $shopContext['id_shop_group'], $shopContext['id_shop']);
            Configuration::updateValue('ADVCLICKFRAUD_SCRAPE_LIMIT', (int)Tools::getValue('ADVCLICKFRAUD_SCRAPE_LIMIT'), false, $shopContext['id_shop_group'], $shopContext['id_shop']);
            Configuration::updateValue('ADVCLICKFRAUD_DISPLAY_LIMIT', (int)Tools::getValue('ADVCLICKFRAUD_DISPLAY_LIMIT'), false, $shopContext['id_shop_group'], $shopContext['id_shop']);
            $output .= $this->displayConfirmation($this->l('Configuration updated successfully for the current shop context.'));
        }

        ClickFraudLog::cleanOldLogs($shopContext['id_shop']);

        $limit = (int)$this->getShopConfig('ADVCLICKFRAUD_DISPLAY_LIMIT');
        if ($limit <= 0) {
            $limit = 20;
        }
        
        $currentPage = (int)Tools::getValue('page', 1);
        if ($currentPage < 1) {
            $currentPage = 1;
        }
        $offset = ($currentPage - 1) * $limit;

        $orderBy = Tools::getValue('order_by', 'date_upd');
        $orderWay = Tools::getValue('order_way', 'DESC');
        $nextOrderWay = (strtoupper($orderWay) === 'DESC') ? 'ASC' : 'DESC';

        $totalLogs = ClickFraudLog::getTotalLogsCount($shopContext['id_shop']);
        $totalPages = ceil($totalLogs / $limit);
        if ($totalPages < 1) {
            $totalPages = 1;
        }

        $logs = ClickFraudLog::getAllLogs($limit, $offset, $orderBy, $orderWay, $shopContext['id_shop']);
        $stats = ClickFraudLog::getGlobalStats($shopContext['id_shop']);

        $baseUrl = AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules');
        $sortUrl = $baseUrl . '&page=' . $currentPage;
        $pageUrl = $baseUrl . '&order_by=' . $orderBy . '&order_way=' . $orderWay;

        $secure_key = md5($this->name . _COOKIE_KEY_);
        $export_link = $this->context->link->getModuleLink('advclickfraud', 'export', ['secure_key' => $secure_key, 'id_shop' => $shopContext['id_shop']]);

        $this->context->smarty->assign([
            'logs' => $logs,
            'stats' => $stats,
            'form_action' => $baseUrl,
            'click_limit' => $this->getShopConfig('ADVCLICKFRAUD_CLICK_LIMIT'),
            'time_window' => $this->getShopConfig('ADVCLICKFRAUD_TIME_WINDOW'),
            'min_duration' => $this->getShopConfig('ADVCLICKFRAUD_MIN_DURATION'),
            'max_duration' => $this->getShopConfig('ADVCLICKFRAUD_MAX_DURATION'),
            'retention_days' => $this->getShopConfig('ADVCLICKFRAUD_RETENTION_DAYS'),
            'scrape_limit' => $this->getShopConfig('ADVCLICKFRAUD_SCRAPE_LIMIT'),
            'display_limit' => $limit,
            'export_link' => $export_link,
            'sort_url' => $sortUrl,
            'page_url' => $pageUrl,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'order_by' => $orderBy,
            'order_way' => $orderWay,
            'next_order_way' => $nextOrderWay
        ]);

        return $output . $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dashboard.tpl');
    }

    public function hookDisplayHeader()
    {
        $gclid = Tools::getValue('gclid');
        $fbclid = Tools::getValue('fbclid');
        $utm_source = Tools::getValue('utm_source');
        $id_shop = (int)$this->context->shop->id;

        $is_ad_click = (!empty($gclid) || !empty($fbclid) || !empty($utm_source));
        $is_product_page = ($this->context->controller instanceof ProductController);

        if (!isset($this->context->cookie->acf_session_token)) {
            $session_token = bin2hex(random_bytes(32));
            $this->context->cookie->acf_session_token = $session_token;
            $this->context->cookie->write();
        } else {
            $session_token = $this->context->cookie->acf_session_token;
        }

        $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : Tools::getRemoteAddr();

        ClickFraudLog::evaluateVisitor($ip, $is_ad_click, ($gclid ? $gclid : $fbclid), $utm_source, $is_product_page, $id_shop);

        $this->context->smarty->assign([
            'acf_ajax_link' => $this->context->link->getModuleLink('advclickfraud', 'track', ['id_shop' => $id_shop]),
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
