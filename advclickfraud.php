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

require_once _PS_MODULE_DIR_ . 'advclickfraud/classes/ClickFraudLog.php';

class AdvClickFraud extends Module
{
    public function __construct()
    {
        $this->name = 'advclickfraud';
        $this->tab = 'analytics_stats';
        $this->version = '1.6.0';
        $this->author = 'Expert Developer';
        $this->need_instance = 1;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Advanced Click Fraud Detector and Analytics');
        $this->description = $this->l('Detects malicious behavior, price scrapers, and repetitive multi-channel ad clicks with hardware fingerprinting, localized geofencing and structured tabs isolation.');
        $this->ps_versions_compliancy = array('min' => '8.0.0', 'max' => '9.9.9');
    }

    /**
     * Module installation process handling configuration parameters unifications from scratch
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
        
        // Dynamic algorithm fine tuning parameter defaults
        Configuration::updateValue('ADVCLICKFRAUD_CLICK_LIMIT', 3);
        Configuration::updateValue('ADVCLICKFRAUD_TIME_WINDOW', 3600);
        Configuration::updateValue('ADVCLICKFRAUD_MIN_DURATION', 5);
        Configuration::updateValue('ADVCLICKFRAUD_MAX_DURATION', 30);
        Configuration::updateValue('ADVCLICKFRAUD_RETENTION_DAYS', 30);
        Configuration::updateValue('ADVCLICKFRAUD_SCRAPE_LIMIT', 15);
        Configuration::updateValue('ADVCLICKFRAUD_DISPLAY_LIMIT', 20);
        Configuration::updateValue('ADVCLICKFRAUD_EXPORT_THRESHOLD', 70);
        
        // Advanced infrastructure verification network settings toggles
        Configuration::updateValue('ADVCLICKFRAUD_CLOUDFLARE_ACTIVE', 0);
        Configuration::updateValue('ADVCLICKFRAUD_MAXMIND_ACTIVE', 0);
        
        // Fetch all default active store countries to save as the baseline initialization list JSON array
        $activeCountriesData = Country::getCountries((int)Context::getContext()->language->id, true);
        $defaultCountriesList = array();
        if (!empty($activeCountriesData)) {
            foreach ($activeCountriesData as $countryRow) {
                if (!empty($countryRow['iso_code'])) {
                    $defaultCountriesList[] = strtoupper($countryRow['iso_code']);
                }
            }
        }
        Configuration::updateValue('ADVCLICKFRAUD_ALLOWED_COUNTRIES', json_encode($defaultCountriesList));
        
        // Pre-populate verified crawl engines exception networks parameters rows
        $this->populateDefaultWhitelist();
        
        return true;
    }

    /**
     * Module uninstallation database drop and configuration complete parameter purge
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
        Configuration::deleteByName('ADVCLICKFRAUD_EXPORT_THRESHOLD');
        Configuration::deleteByName('ADVCLICKFRAUD_CLOUDFLARE_ACTIVE');
        Configuration::deleteByName('ADVCLICKFRAUD_MAXMIND_ACTIVE');
        Configuration::deleteByName('ADVCLICKFRAUD_ALLOWED_COUNTRIES');
        
        return true;
    }

    /**
     * Create core unifications database schema metrics layouts matching MariaDB standards
     */
    private function createTables()
    {
        $queries = array(
            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "adv_click_fraud_logs` (
                `id_log` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip_address` VARCHAR(45) NOT NULL,
                `device_fingerprint` VARCHAR(64) NULL,
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
                INDEX `idx_ip` (`ip_address`),
                INDEX `idx_fingerprint` (`device_fingerprint`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "adv_click_fraud_sessions` (
                `id_session` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip_address` VARCHAR(45) NOT NULL,
                `session_token` VARCHAR(64) NOT NULL,
                `device_fingerprint` VARCHAR(64) NULL,
                `duration` INT UNSIGNED DEFAULT 0,
                `pages_visited` TEXT NULL,
                `mouse_movements` INT UNSIGNED DEFAULT 0,
                `key_presses` INT UNSIGNED DEFAULT 0,
                `screen_resolution` VARCHAR(30) NULL,
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_session`),
                UNIQUE KEY `idx_token` (`session_token`),
                INDEX `idx_ip_sess` (`ip_address`),
                INDEX `idx_sess_fingerprint` (`device_fingerprint`)
            ) ENGINE=" . _MYSQL_ENGINE_ . " DEFAULT CHARSET=utf8mb4;",

            "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "adv_click_fraud_whitelist` (
                `id_whitelist` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `ip_or_cidr` VARCHAR(50) NOT NULL,
                `description` VARCHAR(255) NOT NULL,
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_whitelist`),
                UNIQUE KEY `idx_ip_cidr` (`ip_or_cidr`)
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
        return Db::getInstance()->execute("
            DROP TABLE IF EXISTS 
            `" . _DB_PREFIX_ . "adv_click_fraud_logs`, 
            `" . _DB_PREFIX_ . "adv_click_fraud_sessions`,
            `" . _DB_PREFIX_ . "adv_click_fraud_whitelist`;
        ");
    }

    private function populateDefaultWhitelist()
    {
        $networks = array(
            array('ip' => '66.249.64.0/19', 'desc' => 'Official Googlebot Network Range'),
            array('ip' => '66.249.96.0/20', 'desc' => 'Official Googlebot Network Range'),
            array('ip' => '216.58.192.0/19', 'desc' => 'Google Global Cache & Indexers'),
            array('ip' => '172.217.0.0/16', 'desc' => 'Google Static Crawl Subnets'),
            array('ip' => '157.55.39.0/24', 'desc' => 'Official Microsoft Bingbot Range'),
            array('ip' => '207.46.13.0/24', 'desc' => 'Official Microsoft Bingbot Range'),
            array('ip' => '40.77.167.0/24', 'desc' => 'Microsoft MSN/Bing Crawler Service'),
            array('ip' => '141.8.128.0/18', 'desc' => 'Official Yandex Search Engine Indexer')
        );

        foreach ($networks as $net) {
            Db::getInstance()->execute("
                INSERT IGNORE INTO `" . _DB_PREFIX_ . "adv_click_fraud_whitelist` (`ip_or_cidr`, `description`, `date_add`)
                VALUES ('" . pSQL($net['ip']) . "', '" . pSQL($net['desc']) . "', NOW())
            ");
        }
    }
    /**
     * Handle the Backoffice main control panel configuration updates, whitelist actions, and rendering view
     */
    public function getContent()
    {
        $output = '';
        $db = Db::getInstance();
        
        // 1. Process main configuration form submission including checkboxes for geofencing countries
        if (Tools::isSubmit('submit_adv_config')) {
            Configuration::updateValue('ADVCLICKFRAUD_CLICK_LIMIT', (int)Tools::getValue('ADVCLICKFRAUD_CLICK_LIMIT'));
            Configuration::updateValue('ADVCLICKFRAUD_TIME_WINDOW', (int)Tools::getValue('ADVCLICKFRAUD_TIME_WINDOW'));
            Configuration::updateValue('ADVCLICKFRAUD_MIN_DURATION', (int)Tools::getValue('ADVCLICKFRAUD_MIN_DURATION'));
            Configuration::updateValue('ADVCLICKFRAUD_MAX_DURATION', (int)Tools::getValue('ADVCLICKFRAUD_MAX_DURATION'));
            Configuration::updateValue('ADVCLICKFRAUD_RETENTION_DAYS', (int)Tools::getValue('ADVCLICKFRAUD_RETENTION_DAYS'));
            Configuration::updateValue('ADVCLICKFRAUD_SCRAPE_LIMIT', (int)Tools::getValue('ADVCLICKFRAUD_SCRAPE_LIMIT'));
            Configuration::updateValue('ADVCLICKFRAUD_DISPLAY_LIMIT', (int)Tools::getValue('ADVCLICKFRAUD_DISPLAY_LIMIT'));
            Configuration::updateValue('ADVCLICKFRAUD_EXPORT_THRESHOLD', (int)Tools::getValue('ADVCLICKFRAUD_EXPORT_THRESHOLD'));
            Configuration::updateValue('ADVCLICKFRAUD_CLOUDFLARE_ACTIVE', (int)Tools::getValue('ADVCLICKFRAUD_CLOUDFLARE_ACTIVE'));
            Configuration::updateValue('ADVCLICKFRAUD_MAXMIND_ACTIVE', (int)Tools::getValue('ADVCLICKFRAUD_MAXMIND_ACTIVE'));
            
            // Process the editable array selection mapping of geofencing country tags
            $selectedCountries = Tools::getValue('ADVCLICKFRAUD_GEOTAGS');
            if (is_array($selectedCountries)) {
                $sanitizedCountries = array_map('strtoupper', array_map('trim', $selectedCountries));
                Configuration::updateValue('ADVCLICKFRAUD_ALLOWED_COUNTRIES', json_encode($sanitizedCountries));
            } else {
                Configuration::updateValue('ADVCLICKFRAUD_ALLOWED_COUNTRIES', json_encode(array()));
            }
            
            $output .= $this->displayConfirmation($this->l('Configuration updated successfully.'));
        }

        // 2. Process database statistics reset execution
        if (Tools::isSubmit('submit_reset_stats')) {
            if (ClickFraudLog::truncateAllTables()) {
                $output .= $this->displayConfirmation($this->l('All logs and behavioral metrics have been successfully reset.'));
            } else {
                $output .= $this->displayError($this->l('An error occurred while resetting database statistics.'));
            }
        }

        // 3. Process new Whitelist item addition form submission
        if (Tools::isSubmit('submit_add_whitelist')) {
            $ip_or_cidr = trim(Tools::getValue('acf_whitelist_ip'));
            $description = trim(Tools::getValue('acf_whitelist_desc'));

            if (!empty($ip_or_cidr) && !empty($description)) {
                $insert_ok = $db->execute("
                    INSERT IGNORE INTO `" . _DB_PREFIX_ . "adv_click_fraud_whitelist` (`ip_or_cidr`, `description`, `date_add`)
                    VALUES ('" . pSQL($ip_or_cidr) . "', '" . pSQL($description) . "', NOW())
                ");
                if ($insert_ok) {
                    $output .= $this->displayConfirmation($this->l('New exception rule added successfully.'));
                } else {
                    $output .= $this->displayError($this->l('An error occurred or the IP rule already exists.'));
                }
            } else {
                $output .= $this->displayError($this->l('Please fill in both the IP address and description fields.'));
            }
        }

        // 4. Process Whitelist item deletion request link
        if ($id_del = (int)Tools::getValue('delete_whitelist')) {
            $delete_ok = $db->execute("DELETE FROM `" . _DB_PREFIX_ . "adv_click_fraud_whitelist` WHERE `id_whitelist` = " . (int)$id_del);
            if ($delete_ok) {
                $output .= $this->displayConfirmation($this->l('Exception rule successfully removed.'));
            }
        }

        ClickFraudLog::cleanOldLogs();

        // AUTOMATED GEOFENCING MATRIX: Extract all PrestaShop countries system-wide to render editable choices
        $langId = (int)$this->context->language->id;
        $allSystemCountries = Country::getCountries($langId, false, false, false); // Fetch both active and inactive options
        
        // Read saved dynamic choices array from configuration table layers
        $savedCountriesJson = Configuration::get('ADVCLICKFRAUD_ALLOWED_COUNTRIES');
        $activeGeotags = json_decode($savedCountriesJson, true);
        if (!is_array($activeGeotags)) {
            $activeGeotags = array();
        }

        // Pagination administrative parameter configurations
        $limit = (int)Configuration::get('ADVCLICKFRAUD_DISPLAY_LIMIT');
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

        $totalLogs = ClickFraudLog::getTotalLogsCount();
        $totalPages = ceil($totalLogs / $limit);
        if ($totalPages < 1) {
            $totalPages = 1;
        }

        $logs = ClickFraudLog::getAllLogs($limit, $offset, $orderBy, $orderWay);
        $stats = ClickFraudLog::getGlobalStats();
        $whitelist_items = $db->executeS("SELECT * FROM `" . _DB_PREFIX_ . "adv_click_fraud_whitelist` ORDER BY `date_add` DESC");

        $baseUrl = AdminController::$currentIndex . '&configure=' . $this->name . '&token=' . Tools::getAdminTokenLite('AdminModules');
        $sortUrl = $baseUrl . '&page=' . $currentPage;
        $pageUrl = $baseUrl . '&order_by=' . $orderBy . '&order_way=' . $orderWay;

        $secureKey = md5($this->name . _COOKIE_KEY_);
        $exportParams = array('secure_key' => $secureKey);
        $exportLink = $this->context->link->getModuleLink('advclickfraud', 'export', $exportParams);

        $this->context->smarty->assign(array(
            'logs' => $logs,
            'stats' => $stats,
            'whitelist_items' => $whitelist_items,
            'form_action' => $baseUrl,
            'click_limit' => Configuration::get('ADVCLICKFRAUD_CLICK_LIMIT'),
            'time_window' => Configuration::get('ADVCLICKFRAUD_TIME_WINDOW'),
            'min_duration' => Configuration::get('ADVCLICKFRAUD_MIN_DURATION'),
            'max_duration' => Configuration::get('ADVCLICKFRAUD_MAX_DURATION'),
            'retention_days' => Configuration::get('ADVCLICKFRAUD_RETENTION_DAYS'),
            'scrape_limit' => Configuration::get('ADVCLICKFRAUD_SCRAPE_LIMIT'),
            'display_limit' => $limit,
            'export_threshold' => (int)Configuration::get('ADVCLICKFRAUD_EXPORT_THRESHOLD'),
            'export_link' => $exportLink,
            'sort_url' => $sortUrl,
            'page_url' => $pageUrl,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'order_by' => $orderBy,
            'order_way' => $orderWay,
            'next_order_way' => $nextOrderWay,
            'cloudflare_active' => (int)Configuration::get('ADVCLICKFRAUD_CLOUDFLARE_ACTIVE'),
            'maxmind_active' => (int)Configuration::get('ADVCLICKFRAUD_MAXMIND_ACTIVE'),
            
            // New smart selection variables passed down to UI loops
            'system_countries' => $allSystemCountries,
            'active_geotags' => $activeGeotags
        ));

        return $output . $this->context->smarty->fetch($this->local_path . 'views/templates/admin/dashboard.tpl');
    }

    /**
     * Frontend injection wrapper hooks monitoring dynamic multi-channel parameters
     */
    public function hookDisplayHeader()
    {
        // 1. EXTRACT ADVANCED INTER-CHANNEL MARKING ATTRIBUTES CLICK IDS
        $gclid = Tools::getValue('gclid');
        $wbraid = Tools::getValue('wbraid');
        $gbraid = Tools::getValue('gbraid');
        $fbclid = Tools::getValue('fbclid');
        $ttclid = Tools::getValue('ttclid');
        $utm_source = Tools::getValue('utm_source');

        $isAdClick = (!empty($gclid) || !empty($wbraid) || !empty($gbraid) || !empty($fbclid) || !empty($ttclid) || !empty($utm_source));
        $isProductPage = ($this->context->controller instanceof ProductController);

        // Map specific advertising platform attribution labels dynamically
        $detectedChannel = null;
        if (!empty($gclid) || !empty($wbraid) || !empty($gbraid)) {
            $detectedChannel = 'Google Ads';
        } elseif (!empty($fbclid)) {
            $detectedChannel = 'Meta Ads';
        } elseif (!empty($ttclid)) {
            $detectedChannel = 'TikTok Ads';
        } elseif (!empty($utm_source)) {
            $detectedChannel = $utm_source;
        }

        if (!isset($this->context->cookie->acf_session_token)) {
            $sessionToken = bin2hex(random_bytes(32));
            $this->context->cookie->acf_session_token = $sessionToken;
            $this->context->cookie->write();
        } else {
            $sessionToken = $this->context->cookie->acf_session_token;
        }

        $cfActive = (int)Configuration::get('ADVCLICKFRAUD_CLOUDFLARE_ACTIVE');
        if ($cfActive && isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } else {
            $ip = Tools::getRemoteAddr();
        }
        
        $adToken = $gclid ? $gclid : ($fbclid ? $fbclid : $ttclid);

        // Forward multi-channel markers parameters back into model evaluation engines matrices
        ClickFraudLog::evaluateVisitor($ip, $isAdClick, $adToken, $detectedChannel, $isProductPage);

        $this->context->smarty->assign(array(
            'acf_ajax_link' => $this->context->link->getModuleLink('advclickfraud', 'track'),
            'acf_token' => $sessionToken,
            'current_page' => Tools::getHttpHost(true) . $_SERVER['REQUEST_URI']
        ));

        return $this->display(__FILE__, 'views/templates/hook/header.tpl');
    }

    /**
     * Backend core asset styles framework register
     */
    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') === $this->name) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css', 'all');
        }
    }
}
