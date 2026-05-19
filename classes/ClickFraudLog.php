<?php
/**
 * ClickFraudLog Class Model
 * Multistore tracking implementation via id_shop restriction columns.
 */

class ClickFraudLog extends ObjectModel
{
    public static function evaluateVisitor($ip, $is_ad_click, $gclid, $utm_source, $is_product_page, $id_shop = 1)
    {
        $db = Db::getInstance();
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        $isBot = preg_match('/bot|crawl|slurp|spider|mediapartners|headless|curl|wget|headlesschrome/i', $userAgent) ? 1 : 0;
        
        if ($is_product_page && empty($referrer)) {
            $isBot = 1; 
        }

        $timeWindow = (int)Configuration::get('ADVCLICKFRAUD_TIME_WINDOW', null, null, $id_shop);
        $dateThreshold = date('Y-m-d H:i:s', time() - $timeWindow);

        $sessionToken = md5($ip . date('Y-m-d H') . $id_shop);
        $currentPage = Tools::getHttpHost(true) . $_SERVER['REQUEST_URI'];
        $isScraper = 0;

        if ($is_product_page) {
            $sessionData = $db->getRow('
                SELECT `pages_visited` FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` 
                WHERE `session_token` = "' . pSQL($sessionToken) . '" AND `id_shop` = ' . (int)$id_shop
            ');
            
            $visitedPages = $sessionData ? json_decode($sessionData['pages_visited'], true) : [];
            if (!is_array($visitedPages)) {
                $visitedPages = [];
            }
            
            if (!in_array($currentPage, $visitedPages)) {
                $visitedPages[] = $currentPage;
            }
            
            $productPagesCount = count($visitedPages);
            $scrapeLimit = (int)Configuration::get('ADVCLICKFRAUD_SCRAPE_LIMIT', null, null, $id_shop);

            if ($productPagesCount > $scrapeLimit) {
                $isScraper = 1;
            }

            $db->execute('
                INSERT INTO `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` (`id_shop`, `ip_address`, `session_token`, `pages_visited`, `date_add`, `date_upd`)
                VALUES (' . (int)$id_shop . ', "' . pSQL($ip) . '", "' . pSQL($sessionToken) . '", "' . pSQL(json_encode($visitedPages)) . '", NOW(), NOW())
                ON DUPLICATE KEY UPDATE `pages_visited` = "' . pSQL(json_encode($visitedPages)) . '", `date_upd` = NOW()
            ');
        }

        $fraudScore = 0;
        if ($isBot) $fraudScore += 60;
        if ($isScraper) $fraudScore = 100;

        $existingLog = $db->getRow('
            SELECT * FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` 
            WHERE `ip_address` = "' . pSQL($ip) . '" AND `id_shop` = ' . (int)$id_shop . ' AND `date_add` >= "' . pSQL($dateThreshold) . '"
        ');

        if ($existingLog) {
            $newClickCount = (int)$existingLog['click_count'] + ($is_ad_click ? 1 : 0);
            $limit = (int)Configuration::get('ADVCLICKFRAUD_CLICK_LIMIT', null, null, $id_shop);
            $extraScore = ($newClickCount > $limit) ? 40 : 10;

            $finalScore = min(100, (int)$existingLog['fraud_score'] + $extraScore);
            if ($isScraper || $isBot) {
                $finalScore = 100;
            }

            $db->update('adv_click_fraud_logs', [
                'click_count' => $newClickCount,
                'fraud_score' => $finalScore,
                'is_scraper' => (int)($existingLog['is_scraper'] || $isScraper),
                'is_bot' => (int)($existingLog['is_bot'] || $isBot),
                'date_upd' => date('Y-m-d H:i:s')
            ], 'id_log = ' . (int)$existingLog['id_log']);
        } else {
            if ($is_ad_click || $isScraper || $isBot) {
                $db->insert('adv_click_fraud_logs', [
                    'id_shop' => (int)$id_shop,
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
                ]);
            }
        }
    }

    public static function updateSessionTelemetry($token, $ip, $data, $id_shop = 1)
    {
        $db = Db::getInstance();
        $existingSession = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` WHERE `session_token` = "' . pSQL($token) . '" AND `id_shop` = ' . (int)$id_shop);

        $currentPage = isset($data['page']) ? $data['page'] : '';
        $resolution = isset($data['resolution']) ? $data['resolution'] : '';
        $mouseMoves = isset($data['mouseMoves']) ? (int)$data['mouseMoves'] : 0;
        $keyPresses = isset($data['keyPresses']) ? (int)$data['keyPresses'] : 0;
        $duration = isset($data['duration']) ? (int)$data['duration'] : 0;

        if ($existingSession) {
            $visitedPages = json_decode($existingSession['pages_visited'], true);
            if (!is_array($visitedPages)) $visitedPages = [];
            if (!in_array($currentPage, $visitedPages) && !empty($currentPage)) {
                $visitedPages[] = $currentPage;
            }

            $db->update('adv_click_fraud_sessions', [
                'duration' => (int)$duration,
                'pages_visited' => pSQL(json_encode($visitedPages)),
                'mouse_movements' => (int)($existingSession['mouse_movements'] + $mouseMoves),
                'key_presses' => (int)($existingSession['key_presses'] + $keyPresses),
                'date_upd' => date('Y-m-d H:i:s')
            ], 'id_session = ' . (int)$existingSession['id_session']);
        } else {
            $visitedPages = !empty($currentPage) ? json_encode([$currentPage]) : json_encode([]);
            $db->insert('adv_click_fraud_sessions', [
                'id_shop' => (int)$id_shop,
                'ip_address' => pSQL($ip),
                'session_token' => pSQL($token),
                'duration' => (int)$duration,
                'pages_visited' => pSQL($visitedPages),
                'mouse_movements' => (int)$mouseMoves,
                'key_presses' => (int)$keyPresses,
                'screen_resolution' => pSQL($resolution),
                'date_add' => date('Y-m-d H:i:s'),
                'date_upd' => date('Y-m-d H:i:s')
            ]);
        }

        $minD = (int)Configuration::get('ADVCLICKFRAUD_MIN_DURATION', null, null, $id_shop);
        $maxD = (int)Configuration::get('ADVCLICKFRAUD_MAX_DURATION', null, null, $id_shop);

        if ($duration > $minD && $duration < $maxD && $mouseMoves == 0 && $keyPresses == 0) {
            $db->execute('UPDATE `' . _DB_PREFIX_ . 'adv_click_fraud_logs` SET `fraud_score` = LEAST(100, `fraud_score` + 25) WHERE `ip_address` = "' . pSQL($ip) . '" AND `id_shop` = ' . (int)$id_shop);
        }
    }

    public static function cleanOldLogs($id_shop = 1)
    {
        $days = (int)Configuration::get('ADVCLICKFRAUD_RETENTION_DAYS', null, null, $id_shop);
        if ($days <= 0) $days = 30;
        
        $dateLimit = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `date_upd` < "' . pSQL($dateLimit) . '" AND `id_shop` = ' . (int)$id_shop);
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` WHERE `date_upd` < "' . pSQL($dateLimit) . '" AND `id_shop` = ' . (int)$id_shop);
    }

    public static function getAllLogs($limit = 50, $offset = 0, $orderBy = 'date_upd', $orderWay = 'DESC', $id_shop = 1)
    {
        $allowedColumns = ['ip_address', 'utm_source', 'click_count', 'total_pages_visited', 'duration', 'mouse_movements', 'key_presses', 'fraud_score', 'date_upd'];
        if (!in_array($orderBy, $allowedColumns)) $orderBy = 'date_upd';
        $orderWay = (strtoupper($orderWay) === 'ASC') ? 'ASC' : 'DESC';

        return Db::getInstance()->executeS('
            SELECT l.*, s.duration, s.mouse_movements, s.key_presses, s.screen_resolution,
                   IFNULL(JSON_LENGTH(s.pages_visited), 0) as total_pages_visited
            FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` l
            LEFT JOIN `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` s ON l.ip_address = s.ip_address AND s.id_shop = ' . (int)$id_shop
            WHERE l.id_shop = ' . (int)$id_shop . '
            ORDER BY ' . pSQL($orderBy) . ' ' . pSQL($orderWay) . '
            LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset
        ');
    }

    public static function getTotalLogsCount($id_shop = 1)
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `id_shop` = ' . (int)$id_shop);
    }

    public static function getGlobalStats($id_shop = 1)
    {
        $db = Db::getInstance();
        return [
            'total_clicks' => (int)$db->getValue('SELECT SUM(click_count) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `id_shop` = ' . (int)$id_shop),
            'total_fraud' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `fraud_score` >= 70 AND `id_shop` = ' . (int)$id_shop),
            'avg_duration' => (int)$db->getValue('SELECT AVG(duration) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` WHERE `id_shop` = ' . (int)$id_shop),
            'bot_count' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE (`is_bot` = 1 OR `is_scraper` = 1) AND `id_shop` = ' . (int)$id_shop)
        ];
    }
}
