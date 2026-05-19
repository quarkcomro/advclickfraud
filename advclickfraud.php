<?php
/**
 * Advanced Click Fraud Detector and Analytics
 *
 * @author    Expert Developer
 * @copyright 2026 Expert Developer
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

// Load the main analytic model class dynamically
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

        // Multilanguage compliant strings handled via PrestaShop translation system
        $this->displayName = $this->l('Advanced Click Fraud Detector and Analytics');
        $this->description = $this->l('Detects malicious behavior, price scrapers, and repetitive ad clicks.');
        $this->ps_versions_compliancy = array('min' => '8.0.0', 'max' => '9.9.9');
    }

    /**
     * Module installation database tables and default configuration parameters settings
     */
    public function install()
    {
        if (!parent::install() ||
            !$this->registerHook('displayHeader') ||
            !$this->registerHook('displayBackOfficeHeader') ||
            !$this->createTables()
        ) {
            return false;
        }
        
        // Dynamic settings saved into configuration table (Multistore adaptive context)
        Configuration::updateValue('ADVCLICKFRAUD_CLICK_LIMIT', 3);
        Configuration::updateValue('ADVCLICKFRAUD_TIME_WINDOW', 3600);
        Configuration::updateValue('ADVCLICKFRAUD_MIN_DURATION', 5);
        Configuration::updateValue('ADVCLICKFRAUD_MAX_DURATION', 30);
        Configuration::updateValue('ADVCLICKFRAUD_RETENTION_DAYS', 30);
        Configuration::updateValue('ADVCLICKFRAUD_SCRAPE_LIMIT', 15);
        Configuration::updateValue('ADVCLICKFRAUD_DISPLAY_LIMIT', 20);
        
        return true;
    }

    /**
     * Module uninstallation cleanup process
     */
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

    /**
     * Create required optimized database tables for logging and telemetry matching MariaDB standards
     */
    private function createTables()
    {
        $queries = array(
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
        );

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

    /**
     * Handle the Backoffice main control panel submission and rendering configuration view
     */
    public function getContent()
    {
        $output = '';
        
        // Process administration setting form submit natively handling core variables
        if (Tools::isSubmit('submit_adv_config')) {
            Configuration::updateValue('ADVCLICKFRAUD_CLICK_LIMIT', (int)Tools::getValue('ADVCLICKFRAUD_CLICK_LIMIT'));
            Configuration::updateValue('ADVCLICKFRAUD_TIME_WINDOW', (int)Tools::getValue('ADVCLICKFRAUD_TIME_WINDOW'));
            Configuration::updateValue('ADVCLICKFRAUD_MIN_DURATION', (int)Tools::getValue('ADVCLICKFRAUD_MIN_DURATION'));
            Configuration::updateValue('ADVCLICKFRAUD_MAX_DURATION', (int)Tools::getValue('ADVCLICKFRAUD_MAX_DURATION'));
            Configuration::updateValue('ADVCLICKFRAUD_RETENTION_DAYS', (int)Tools::getValue('ADVCLICKFRAUD_RETENTION_DAYS'));
            Configuration::updateValue('ADVCLICKFRAUD_SCRAPE_LIMIT', (int)Tools::getValue('ADVCLICKFRAUD_SCRAPE_LIMIT'));
            Configuration::updateValue('ADVCLICKFRAUD_DISPLAY_LIMIT', (int)Tools::getValue('ADVCLICKFRAUD_DISPLAY_LIMIT'));
            $output .= $this->displayConfirmation($this->l('Configuration updated successfully.'));
        }

        // Automatic clean-up trigger for historical rows based on current configured parameters
        ClickFraudLog::cleanOldLogs();

        // Pagination parameters calculations based on global states
        $limit = (int)Configuration::get('ADVCLICKFRAUD_DISPLAY_LIMIT');
        if ($limit <= 0) {
            $limit = 20;
        }
        
        $currentPage = (int)Tools::getValue('page', 1);
        if ($currentPage < 1) {
            $currentPage = 1;
        }
        $offset = ($currentPage - 1) * $limit;

        // Sorting parameters state management
        $orderBy = Tools::getValue('order_by', 'date_upd');
        $orderWay = Tools::getValue('order_way', 'DESC');
        $nextOrderWay = (strtoupper($orderWay) === 'DESC') ? 'ASC' : 'DESC';

        // Calculate maximum database rows and structural metrics split
        $totalLogs = ClickFraudLog::getTotalLogsCount();
        $totalPages = ceil($totalLogs / $limit);
        if ($totalPages < 1) {
            $totalPages = 1;
        }

        $logs = ClickFraudLog::getAllLogs($limit, $offset, $orderBy, $orderWay);
        $stats = ClickFraudLog::getGlobalStats();

        // Core controller index linking mapping
        $baseUrl = AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules');
        $sortUrl = $baseUrl . '&page=' . $currentPage;
        $pageUrl = $baseUrl . '&order_by=' . $orderBy . '&order_way=' . $orderWay;

        // Secure dynamic data token hashing mechanism avoiding direct string manipulation conflicts
        $secureKey = md5($this->name . _COOKIE_KEY_);
        $exportParams = array('secure_key' => $secureKey);
        $exportLink = $this->context->link->getModuleLink('advclickfraud', 'export', $exportParams);

        // Assign Smarty engine scope template safe data parameters arrays
        $this->context->smarty->assign(array(
            'logs' => $logs,
            'stats' => $stats,
            'form_action' => $baseUrl,
            'click_limit' => Configuration::get('ADVCLICKFRAUD_CLICK_LIMIT'),
            'time_window' => Configuration::get('ADVCLICKFRAUD_TIME_WINDOW'),
            'min_duration' => Configuration::get('ADVCLICKFRAUD_MIN_DURATION'),
            'max_duration' => Configuration::get('ADVCLICKFRAUD_MAX_DURATION'),
            'retention_days' => Configuration::get('ADVCLICKFRAUD_RETENTION_DAYS'),
            'scrape_limit' => Configuration::get('ADVCLICKFRAUD_SCRAPE_LIMIT'),
            'display_limit' => $limit,
            'export_link' => $exportLink,
            'sort_url' => $sortUrl,
            'page_url' => $pageUrl,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'order_by' => $orderBy,
            'order_way' => $orderWay,
            'next_order_way' => $nextOrderWay
        ));

        return $output . $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dashboard.tpl');
    }

    /**
     * Frontend injection wrapper hooking up to monitor paid parameters and product scrapers
     */
    public function hookDisplayHeader()
    {
        $gclid = Tools::getValue('gclid');
        $fbclid = Tools::getValue('fbclid');
        $utm_source = Tools::getValue('utm_source');

        $isAdClick = (!empty($gclid) || !empty($fbclid) || !empty($utm_source));
        $isProductPage = ($this->context->controller instanceof ProductController);

        // Client telemetry isolation cookie verification
        if (!isset($this->context->cookie->acf_session_token)) {
            $sessionToken = bin2hex(random_bytes(32));
            $this->context->cookie->acf_session_token = $sessionToken;
            $this->context->cookie->write();
        } else {
            $sessionToken = $this->context->cookie->acf_session_token;
        }

        // Resolving visitor tracking source parameters while maintaining Cloudflare proxies support
        $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : Tools::getRemoteAddr();
        $adToken = $gclid ? $gclid : $fbclid;

        // Forward behavioral analytics data to the processing matrix class model
        ClickFraudLog::evaluateVisitor($ip, $isAdClick, $adToken, $utm_source, $isProductPage);

        // Bind dynamic AJAX endpoints back inside the header templates
        $this->context->smarty->assign(array(
            'acf_ajax_link' => $this->context->link->getModuleLink('advclickfraud', 'track'),
            'acf_token' => $sessionToken,
            'current_page' => Tools::getHttpHost(true) . $_SERVER['REQUEST_URI']
        ));

        return $this->display(__FILE__, 'views/templates/hook/header.tpl');
    }

    /**
     * Asset loader targeting the Administration control board views
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        }
    }
}
