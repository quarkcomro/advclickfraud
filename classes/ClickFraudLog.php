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

class ClickFraudLog extends ObjectModel
{
    /**
     * Process and evaluate every page view to identify ad clicks or active price scrapers
     */
    public static function evaluateVisitor($ip, $is_ad_click, $gclid, $utm_source, $is_product_page)
    {
        $db = Db::getInstance();
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        // Match specific automated signatures inside user agent string
        $isBot = preg_match('/bot|crawl|slurp|spider|mediapartners|headless|curl|wget|headlesschrome/i', $userAgent) ? 1 : 0;
        
        // Extensions often bypass referrer headers when jumping between catalog objects
        if ($is_product_page && empty($referrer)) {
            $isBot = 1; 
        }

        $timeWindow = (int)Configuration::get('ADVCLICKFRAUD_TIME_WINDOW');
        $dateThreshold = date('Y-m-d H:i:s', time() - $timeWindow);

        // Standard hourly token matching preventing race conditions under multiple opened tabs
        $sessionToken = md5($ip . date('Y-m-d H'));
        $currentPage = Tools::getHttpHost(true) . $_SERVER['REQUEST_URI'];

        $isScraper = 0;

        // Atomic multi-tab scraping evaluation matrix
        if ($is_product_page) {
            $sessionData = $db->getRow('
                SELECT `pages_visited` 
                FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` 
                WHERE `session_token` = "' . pSQL($sessionToken) . '"
            ');
            
            $visitedPages = array();
            if ($sessionData && !empty($sessionData['pages_visited'])) {
                $decoded = json_decode($sessionData['pages_visited'], true);
                if (is_array($decoded)) {
                    $visitedPages = $decoded;
                }
            }
            
            if (!in_array($currentPage, $visitedPages)) {
                $visitedPages[] = $currentPage;
            }
            
            $productPagesCount = count($visitedPages);
            $scrapeLimit = (int)Configuration::get('ADVCLICKFRAUD_SCRAPE_LIMIT');

            if ($productPagesCount > $scrapeLimit) {
                $isScraper = 1;
            }

            // Execute secure atomic state persistence inside MariaDB
            $db->execute('
                INSERT INTO `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` (`ip_address`, `session_token`, `pages_visited`, `date_add`, `date_upd`)
                VALUES ("' . pSQL($ip) . '", "' . pSQL($sessionToken) . '", "' . pSQL(json_encode($visitedPages)) . '", NOW(), NOW())
                ON DUPLICATE KEY UPDATE `pages_visited` = "' . pSQL(json_encode($visitedPages)) . '", `date_upd` = NOW()
            ');
        }

        $fraudScore = 0;
        if ($isBot) {
            $fraudScore += 60;
        }
        if ($isScraper) {
            $fraudScore = 100;
        }

        // Search for existing logs inside active configuration monitoring window
        $existingLog = $db->getRow('
            SELECT * FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` 
            WHERE `ip_address` = "' . pSQL($ip) . '" 
            AND `date_add` >= "' . pSQL($dateThreshold) . '"
        ');

        if ($existingLog) {
            $newClickCount = (int)$existingLog['click_count'] + ($is_ad_click ? 1 : 0);
            $limit = (int)Configuration::get('ADVCLICKFRAUD_CLICK_LIMIT');
            $extraScore = ($newClickCount > $limit) ? 40 : 10;

            $finalScore = min(100, (int)$existingLog['fraud_score'] + $extraScore);
            if ($isScraper || $isBot) {
                $finalScore = 100;
            }

            $db->update('adv_click_fraud_logs', array(
                'click_count' => $newClickCount,
                'fraud_score' => $finalScore,
                'is_scraper' => (int)($existingLog['is_scraper'] || $isScraper),
                'is_bot' => (int)($existingLog['is_bot'] || $isBot),
                'date_upd' => date('Y-m-d H:i:s')
            ), 'id_log = ' . (int)$existingLog['id_log']);
        } else {
            if ($is_ad_click || $isScraper || $isBot) {
                $db->insert('adv_click_fraud_logs', array(
                    'ip_address' => pSQL($ip),
                    'gclid' => pSQL($gclid),
                    'utm_source' => $is_ad_click ? ($utm_source ? pSQL($utm_source) : 'google_ads') : null,
                    'user_agent' => pSQL($userAgent),
                    'referrer' => pSQL($referrer),
                    'is_bot' => (int)$isBot,
                    'is_scraper' => (int)$isScraper,
                    'fraud_score' => (int)$fraudScore,
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s')
                ));
            }
        }
    }

    /**
     * Receives raw background telemetric payloads from front-end beacon threads
     */
    public static function updateSessionTelemetry($token, $ip, $data)
    {
        $db = Db::getInstance();
        $existingSession = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` WHERE `session_token` = "' . pSQL($token) . '"');

        $currentPage = isset($data['page']) ? $data['page'] : '';
        $resolution = isset($data['resolution']) ? $data['resolution'] : '';
        $mouseMoves = isset($data['mouseMoves']) ? (int)$data['mouseMoves'] : 0;
        $keyPresses = isset($data['keyPresses']) ? (int)$data['keyPresses'] : 0;
        $duration = isset($data['duration']) ? (int)$data['duration'] : 0;

        if ($existingSession) {
            $visitedPages = array();
            if (!empty($existingSession['pages_visited'])) {
                $decoded = json_decode($existingSession['pages_visited'], true);
                if (is_array($decoded)) {
                    $visitedPages = $decoded;
                }
            }
            
            if (!in_array($currentPage, $visitedPages) && !empty($currentPage)) {
                $visitedPages[] = $currentPage;
            }

            $db->update('adv_click_fraud_sessions', array(
                'duration' => (int)$duration,
                'pages_visited' => pSQL(json_encode($visitedPages)),
                'mouse_movements' => (int)($existingSession['mouse_movements'] + $mouseMoves),
                'key_presses' => (int)($existingSession['key_presses'] + $keyPresses),
                'date_upd' => date('Y-m-d H:i:s')
            ), 'id_session = ' . (int)$existingSession['id_session']);
        } else {
            $visitedPages = !empty($currentPage) ? json_encode(array($currentPage)) : json_encode(array());
            $db->insert('adv_click_fraud_sessions', array(
                'ip_address' => pSQL($ip),
                'session_token' => pSQL($token),
                'duration' => (int)$duration,
                'pages_visited' => pSQL($visitedPages),
                'mouse_movements' => (int)$mouseMoves,
                'key_presses' => (int)$keyPresses,
                'screen_resolution' => pSQL($resolution),
                'date_add' => date('Y-m-d H:i:s'),
                'date_upd' => date('Y-m-d H:i:s')
            ));
        }

        $minD = (int)Configuration::get('ADVCLICKFRAUD_MIN_DURATION');
        $maxD = (int)Configuration::get('ADVCLICKFRAUD_MAX_DURATION');

        if ($duration > $minD && $duration < $maxD && $mouseMoves == 0 && $keyPresses == 0) {
            $db->execute('UPDATE `' . _DB_PREFIX_ . 'adv_click_fraud_logs` SET `fraud_score` = LEAST(100, `fraud_score` + 25) WHERE `ip_address` = "' . pSQL($ip) . '"');
        }
    }

    /**
     * Delete historical records exceeding retention days threshold parameter
     */
    public static function cleanOldLogs()
    {
        $days = (int)Configuration::get('ADVCLICKFRAUD_RETENTION_DAYS');
        if ($days <= 0) {
            $days = 30;
        }
        
        $dateLimit = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `date_upd` < "' . pSQL($dateLimit) . '"');
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` WHERE `date_upd` < "' . pSQL($dateLimit) . '"');
    }

    /**
     * Get unique pagination slice sorted by dynamic backoffice states parameters
     */
    public static function getAllLogs($limit = 50, $offset = 0, $orderBy = 'date_upd', $orderWay = 'DESC')
    {
        $allowedColumns = array(
            'ip_address', 'utm_source', 'click_count', 'total_pages_visited', 
            'duration', 'mouse_movements', 'key_presses', 'fraud_score', 'date_upd'
        );
        
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'date_upd';
        }
        
        $orderWay = (strtoupper($orderWay) === 'ASC') ? 'ASC' : 'DESC';

        $sql = '
            SELECT l.*, s.duration, s.mouse_movements, s.key_presses, s.screen_resolution,
                   IFNULL(JSON_LENGTH(s.pages_visited), 0) as total_pages_visited
            FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` l
            LEFT JOIN `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` s ON l.ip_address = s.ip_address
            ORDER BY ' . pSQL($orderBy) . ' ' . pSQL($orderWay) . '
            LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;

        return Db::getInstance()->executeS($sql);
    }

    /**
     * Fetch complete database logs row volume
     */
    public static function getTotalLogsCount()
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs`');
    }

    /**
     * Return structural aggregates for core overview boxes widgets
     */
    public static function getGlobalStats()
    {
        $db = Db::getInstance();
        return array(
            'total_clicks' => (int)$db->getValue('SELECT SUM(click_count) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs`'),
            'total_fraud' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `fraud_score` >= 70'),
            'avg_duration' => (int)$db->getValue('SELECT AVG(duration) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions`'),
            'bot_count' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `is_bot` = 1 OR `is_scraper` = 1')
        );
    }
}
