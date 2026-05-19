<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ClickFraudLog extends ObjectModel
{
    public static function evaluateVisitor($ip, $is_ad_click, $gclid, $utm_source, $is_product_page)
    {
        $db = Db::getInstance();
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        $isBot = preg_match('/bot|crawl|slurp|spider|mediapartners|headless|curl|wget/i', $userAgent) ? 1 : 0;
        $timeWindow = (int)Configuration::get('ADVCLICKFRAUD_TIME_WINDOW');
        $dateThreshold = date('Y-m-d H:i:s', time() - $timeWindow);

        $existingLog = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `ip_address` = "' . pSQL($ip) . '" AND `date_add` >= "' . pSQL($dateThreshold) . '"');
        $existingSession = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` WHERE `ip_address` = "' . pSQL($ip) . '"');

        $fraudScore = $isBot ? 75 : 0;
        $isScraper = 0;

        // LOGICĂ DETECȚIE SCRAPERI DE PREȚURI
        if ($is_product_page) {
            // Numărăm câte pagini de produs a accesat acest IP în ultima oră
            if ($existingSession) {
                $visitedPages = json_decode($existingSession['pages_visited'], true);
                $productPagesCount = is_array($visitedPages) ? count($visitedPages) : 0;
                $scrapeLimit = (int)Configuration::get('ADVCLICKFRAUD_SCRAPE_LIMIT');

                if ($productPagesCount > $scrapeLimit) {
                    $isScraper = 1;
                    $fraudScore = max($fraudScore, 90); // Scraperii agresivi primesc direct scor penalizator mare
                }
            }
        }

        if ($existingLog) {
            $newClickCount = (int)$existingLog['click_count'] + ($is_ad_click ? 1 : 0);
            $limit = (int)Configuration::get('ADVCLICKFRAUD_CLICK_LIMIT');
            
            $extraScore = ($newClickCount > $limit) ? 40 : 10;

            // Verificare istoric telemetrie bazat pe constantele din UI
            if ($existingSession && (int)$existingSession['mouse_movements'] == 0 && (int)$existingSession['duration'] <= (int)Configuration::get('ADVCLICKFRAUD_MIN_DURATION')) {
                $extraScore += 30; 
            }

            $finalScore = min(100, (int)$existingLog['fraud_score'] + $extraScore);
            if ($isScraper) {
                $finalScore = 100;
            }

            $db->update('adv_click_fraud_logs', [
                'click_count' => $newClickCount,
                'fraud_score' => $finalScore,
                'is_scraper' => (int)($existingLog['is_scraper'] || $isScraper),
                'date_upd' => date('Y-m-d H:i:s')
            ], 'id_log = ' . (int)$existingLog['id_log']);
        } else {
            if ($is_ad_click) {
                $db->insert('adv_click_fraud_logs', [
                    'ip_address' => pSQL($ip),
                    'gclid' => pSQL($gclid),
                    'utm_source' => $utm_source ? pSQL($utm_source) : 'google_ads',
                    'user_agent' => pSQL($userAgent),
                    'referrer' => pSQL($referrer),
                    'is_bot' => (int)$isBot,
                    'is_scraper' => (int)$isScraper,
                    'fraud_score' => (int)$fraudScore,
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s')
                ]);
            } elseif ($isScraper) {
                // Înregistrăm scraperul chiar dacă nu a venit din reclame
                $db->insert('adv_click_fraud_logs', [
                    'ip_address' => pSQL($ip),
                    'user_agent' => pSQL($userAgent),
                    'is_scraper' => 1,
                    'fraud_score' => 100,
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

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

        // EVALUARE INACTIVITATE DIN CONSTANTE DINAMICE UI
        $minD = (int)Configuration::get('ADVCLICKFRAUD_MIN_DURATION');
        $maxD = (int)Configuration::get('ADVCLICKFRAUD_MAX_DURATION');

        if ($duration > $minD && $duration < $maxD && $mouseMoves == 0 && $keyPresses == 0) {
            $db->execute('UPDATE `' . _DB_PREFIX_ . 'adv_click_fraud_logs` SET `fraud_score` = LEAST(100, `fraud_score` + 25) WHERE `ip_address` = "' . pSQL($ip) . '"');
        }
    }

    public static function cleanOldLogs()
    {
        $days = (int)Configuration::get('ADVCLICKFRAUD_RETENTION_DAYS');
        if ($days <= 0) $days = 30;
        
        $dateLimit = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `date_upd` < "' . pSQL($dateLimit) . '"');
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` WHERE `date_upd` < "' . pSQL($dateLimit) . '"');
    }

    public static function getAllLogs($limit = 50, $offset = 0, $orderBy = 'date_upd', $orderWay = 'DESC')
    {
        // Validăm coloanele permise pentru a preveni SQL Injection
        $allowedColumns = [
            'ip_address', 'utm_source', 'click_count', 'total_pages_visited', 
            'duration', 'mouse_movements', 'key_presses', 'fraud_score', 'date_upd'
        ];
        
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'date_upd';
        }
        
        $orderWay = (strtoupper($orderWay) === 'ASC') ? 'ASC' : 'DESC';
    
        // Folosim o subinterogare pentru total_pages_visited pentru a putea sorta eficient după ea
        $sql = '
            SELECT l.*, s.duration, s.mouse_movements, s.key_presses, s.screen_resolution,
                   IFNULL(JSON_LENGTH(s.pages_visited), 0) as total_pages_visited
            FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` l
            LEFT JOIN `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` s ON l.ip_address = s.ip_address
            ORDER BY ' . pSQL($orderBy) . ' ' . pSQL($orderWay) . '
            LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
    
        return Db::getInstance()->executeS($sql);
    }

    public static function getTotalLogsCount()
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs`');
    }

    public static function getGlobalStats()
    {
        $db = Db::getInstance();
        return [
            'total_clicks' => (int)$db->getValue('SELECT SUM(click_count) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs`'),
            'total_fraud' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `fraud_score` >= 70'),
            'avg_duration' => (int)$db->getValue('SELECT AVG(duration) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions`'),
            'bot_count' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `is_bot` = 1 OR `is_scraper` = 1')
        ];
    }
}<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ClickFraudLog extends ObjectModel
{
    public static function evaluateVisitor($ip, $is_ad_click, $gclid, $utm_source, $is_product_page)
    {
        $db = Db::getInstance();
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        $isBot = preg_match('/bot|crawl|slurp|spider|mediapartners|headless|curl|wget/i', $userAgent) ? 1 : 0;
        $timeWindow = (int)Configuration::get('ADVCLICKFRAUD_TIME_WINDOW');
        $dateThreshold = date('Y-m-d H:i:s', time() - $timeWindow);

        $existingLog = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `ip_address` = "' . pSQL($ip) . '" AND `date_add` >= "' . pSQL($dateThreshold) . '"');
        $existingSession = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` WHERE `ip_address` = "' . pSQL($ip) . '"');

        $fraudScore = $isBot ? 75 : 0;
        $isScraper = 0;

        // LOGICĂ DETECȚIE SCRAPERI DE PREȚURI
        if ($is_product_page) {
            // Numărăm câte pagini de produs a accesat acest IP în ultima oră
            if ($existingSession) {
                $visitedPages = json_decode($existingSession['pages_visited'], true);
                $productPagesCount = is_array($visitedPages) ? count($visitedPages) : 0;
                $scrapeLimit = (int)Configuration::get('ADVCLICKFRAUD_SCRAPE_LIMIT');

                if ($productPagesCount > $scrapeLimit) {
                    $isScraper = 1;
                    $fraudScore = max($fraudScore, 90); // Scraperii agresivi primesc direct scor penalizator mare
                }
            }
        }

        if ($existingLog) {
            $newClickCount = (int)$existingLog['click_count'] + ($is_ad_click ? 1 : 0);
            $limit = (int)Configuration::get('ADVCLICKFRAUD_CLICK_LIMIT');
            
            $extraScore = ($newClickCount > $limit) ? 40 : 10;

            // Verificare istoric telemetrie bazat pe constantele din UI
            if ($existingSession && (int)$existingSession['mouse_movements'] == 0 && (int)$existingSession['duration'] <= (int)Configuration::get('ADVCLICKFRAUD_MIN_DURATION')) {
                $extraScore += 30; 
            }

            $finalScore = min(100, (int)$existingLog['fraud_score'] + $extraScore);
            if ($isScraper) {
                $finalScore = 100;
            }

            $db->update('adv_click_fraud_logs', [
                'click_count' => $newClickCount,
                'fraud_score' => $finalScore,
                'is_scraper' => (int)($existingLog['is_scraper'] || $isScraper),
                'date_upd' => date('Y-m-d H:i:s')
            ], 'id_log = ' . (int)$existingLog['id_log']);
        } else {
            if ($is_ad_click) {
                $db->insert('adv_click_fraud_logs', [
                    'ip_address' => pSQL($ip),
                    'gclid' => pSQL($gclid),
                    'utm_source' => $utm_source ? pSQL($utm_source) : 'google_ads',
                    'user_agent' => pSQL($userAgent),
                    'referrer' => pSQL($referrer),
                    'is_bot' => (int)$isBot,
                    'is_scraper' => (int)$isScraper,
                    'fraud_score' => (int)$fraudScore,
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s')
                ]);
            } elseif ($isScraper) {
                // Înregistrăm scraperul chiar dacă nu a venit din reclame
                $db->insert('adv_click_fraud_logs', [
                    'ip_address' => pSQL($ip),
                    'user_agent' => pSQL($userAgent),
                    'is_scraper' => 1,
                    'fraud_score' => 100,
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

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

        // EVALUARE INACTIVITATE DIN CONSTANTE DINAMICE UI
        $minD = (int)Configuration::get('ADVCLICKFRAUD_MIN_DURATION');
        $maxD = (int)Configuration::get('ADVCLICKFRAUD_MAX_DURATION');

        if ($duration > $minD && $duration < $maxD && $mouseMoves == 0 && $keyPresses == 0) {
            $db->execute('UPDATE `' . _DB_PREFIX_ . 'adv_click_fraud_logs` SET `fraud_score` = LEAST(100, `fraud_score` + 25) WHERE `ip_address` = "' . pSQL($ip) . '"');
        }
    }

    public static function cleanOldLogs()
    {
        $days = (int)Configuration::get('ADVCLICKFRAUD_RETENTION_DAYS');
        if ($days <= 0) $days = 30;
        
        $dateLimit = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `date_upd` < "' . pSQL($dateLimit) . '"');
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` WHERE `date_upd` < "' . pSQL($dateLimit) . '"');
    }

    public static function getAllLogs($limit = 50, $offset = 0, $orderBy = 'date_upd', $orderWay = 'DESC')
    {
        // Validăm coloanele permise pentru a preveni SQL Injection
        $allowedColumns = [
            'ip_address', 'utm_source', 'click_count', 'total_pages_visited', 
            'duration', 'mouse_movements', 'key_presses', 'fraud_score', 'date_upd'
        ];
        
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'date_upd';
        }
        
        $orderWay = (strtoupper($orderWay) === 'ASC') ? 'ASC' : 'DESC';
    
        // Folosim o subinterogare pentru total_pages_visited pentru a putea sorta eficient după ea
        $sql = '
            SELECT l.*, s.duration, s.mouse_movements, s.key_presses, s.screen_resolution,
                   IFNULL(JSON_LENGTH(s.pages_visited), 0) as total_pages_visited
            FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` l
            LEFT JOIN `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` s ON l.ip_address = s.ip_address
            ORDER BY ' . pSQL($orderBy) . ' ' . pSQL($orderWay) . '
            LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
    
        return Db::getInstance()->executeS($sql);
    }

    public static function getTotalLogsCount()
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs`');
    }

    public static function getGlobalStats()
    {
        $db = Db::getInstance();
        return [
            'total_clicks' => (int)$db->getValue('SELECT SUM(click_count) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs`'),
            'total_fraud' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `fraud_score` >= 70'),
            'avg_duration' => (int)$db->getValue('SELECT AVG(duration) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions`'),
            'bot_count' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `is_bot` = 1 OR `is_scraper` = 1')
        ];
    }
}<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class ClickFraudLog extends ObjectModel
{
    public static function evaluateVisitor($ip, $is_ad_click, $gclid, $utm_source, $is_product_page)
    {
        $db = Db::getInstance();
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        $isBot = preg_match('/bot|crawl|slurp|spider|mediapartners|headless|curl|wget/i', $userAgent) ? 1 : 0;
        $timeWindow = (int)Configuration::get('ADVCLICKFRAUD_TIME_WINDOW');
        $dateThreshold = date('Y-m-d H:i:s', time() - $timeWindow);

        $existingLog = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `ip_address` = "' . pSQL($ip) . '" AND `date_add` >= "' . pSQL($dateThreshold) . '"');
        $existingSession = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` WHERE `ip_address` = "' . pSQL($ip) . '"');

        $fraudScore = $isBot ? 75 : 0;
        $isScraper = 0;

        // LOGICĂ DETECȚIE SCRAPERI DE PREȚURI
        if ($is_product_page) {
            // Numărăm câte pagini de produs a accesat acest IP în ultima oră
            if ($existingSession) {
                $visitedPages = json_decode($existingSession['pages_visited'], true);
                $productPagesCount = is_array($visitedPages) ? count($visitedPages) : 0;
                $scrapeLimit = (int)Configuration::get('ADVCLICKFRAUD_SCRAPE_LIMIT');

                if ($productPagesCount > $scrapeLimit) {
                    $isScraper = 1;
                    $fraudScore = max($fraudScore, 90); // Scraperii agresivi primesc direct scor penalizator mare
                }
            }
        }

        if ($existingLog) {
            $newClickCount = (int)$existingLog['click_count'] + ($is_ad_click ? 1 : 0);
            $limit = (int)Configuration::get('ADVCLICKFRAUD_CLICK_LIMIT');
            
            $extraScore = ($newClickCount > $limit) ? 40 : 10;

            // Verificare istoric telemetrie bazat pe constantele din UI
            if ($existingSession && (int)$existingSession['mouse_movements'] == 0 && (int)$existingSession['duration'] <= (int)Configuration::get('ADVCLICKFRAUD_MIN_DURATION')) {
                $extraScore += 30; 
            }

            $finalScore = min(100, (int)$existingLog['fraud_score'] + $extraScore);
            if ($isScraper) {
                $finalScore = 100;
            }

            $db->update('adv_click_fraud_logs', [
                'click_count' => $newClickCount,
                'fraud_score' => $finalScore,
                'is_scraper' => (int)($existingLog['is_scraper'] || $isScraper),
                'date_upd' => date('Y-m-d H:i:s')
            ], 'id_log = ' . (int)$existingLog['id_log']);
        } else {
            if ($is_ad_click) {
                $db->insert('adv_click_fraud_logs', [
                    'ip_address' => pSQL($ip),
                    'gclid' => pSQL($gclid),
                    'utm_source' => $utm_source ? pSQL($utm_source) : 'google_ads',
                    'user_agent' => pSQL($userAgent),
                    'referrer' => pSQL($referrer),
                    'is_bot' => (int)$isBot,
                    'is_scraper' => (int)$isScraper,
                    'fraud_score' => (int)$fraudScore,
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s')
                ]);
            } elseif ($isScraper) {
                // Înregistrăm scraperul chiar dacă nu a venit din reclame
                $db->insert('adv_click_fraud_logs', [
                    'ip_address' => pSQL($ip),
                    'user_agent' => pSQL($userAgent),
                    'is_scraper' => 1,
                    'fraud_score' => 100,
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }

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

        // EVALUARE INACTIVITATE DIN CONSTANTE DINAMICE UI
        $minD = (int)Configuration::get('ADVCLICKFRAUD_MIN_DURATION');
        $maxD = (int)Configuration::get('ADVCLICKFRAUD_MAX_DURATION');

        if ($duration > $minD && $duration < $maxD && $mouseMoves == 0 && $keyPresses == 0) {
            $db->execute('UPDATE `' . _DB_PREFIX_ . 'adv_click_fraud_logs` SET `fraud_score` = LEAST(100, `fraud_score` + 25) WHERE `ip_address` = "' . pSQL($ip) . '"');
        }
    }

    public static function cleanOldLogs()
    {
        $days = (int)Configuration::get('ADVCLICKFRAUD_RETENTION_DAYS');
        if ($days <= 0) $days = 30;
        
        $dateLimit = date('Y-m-d H:i:s', strtotime("-$days days"));
        
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `date_upd` < "' . pSQL($dateLimit) . '"');
        Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` WHERE `date_upd` < "' . pSQL($dateLimit) . '"');
    }

    public static function getAllLogs($limit = 50, $offset = 0, $orderBy = 'date_upd', $orderWay = 'DESC')
    {
        // Validăm coloanele permise pentru a preveni SQL Injection
        $allowedColumns = [
            'ip_address', 'utm_source', 'click_count', 'total_pages_visited', 
            'duration', 'mouse_movements', 'key_presses', 'fraud_score', 'date_upd'
        ];
        
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'date_upd';
        }
        
        $orderWay = (strtoupper($orderWay) === 'ASC') ? 'ASC' : 'DESC';
    
        // Folosim o subinterogare pentru total_pages_visited pentru a putea sorta eficient după ea
        $sql = '
            SELECT l.*, s.duration, s.mouse_movements, s.key_presses, s.screen_resolution,
                   IFNULL(JSON_LENGTH(s.pages_visited), 0) as total_pages_visited
            FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` l
            LEFT JOIN `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` s ON l.ip_address = s.ip_address
            ORDER BY ' . pSQL($orderBy) . ' ' . pSQL($orderWay) . '
            LIMIT ' . (int)$limit . ' OFFSET ' . (int)$offset;
    
        return Db::getInstance()->executeS($sql);
    }

    public static function getTotalLogsCount()
    {
        return (int)Db::getInstance()->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs`');
    }

    public static function getGlobalStats()
    {
        $db = Db::getInstance();
        return [
            'total_clicks' => (int)$db->getValue('SELECT SUM(click_count) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs`'),
            'total_fraud' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `fraud_score` >= 70'),
            'avg_duration' => (int)$db->getValue('SELECT AVG(duration) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions`'),
            'bot_count' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `is_bot` = 1 OR `is_scraper` = 1')
        ];
    }
}
