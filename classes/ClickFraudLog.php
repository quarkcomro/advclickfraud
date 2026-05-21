<?php
/**
 * Advanced Click Fraud Detector and Analytics
 * Core Analytic Data Model
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
     * Incorporates automatic IP and CIDR subnet Whitelist routing bypass filters
     * Includes Reverse DNS validation routine for legitimate search engine spiders
     * Incorporates dynamic Class C /24 subnet aggregate penalty tracking algorithms
     */
    public static function evaluateVisitor($ip, $is_ad_click, $gclid, $utm_source, $is_product_page, $fingerprint = null)
    {
        $db = Db::getInstance();

        // 1. DYNAMIC WHITELIST ROUTING BYPASS FILTER
        $whitelistItems = $db->executeS('SELECT `ip_or_cidr` FROM `' . _DB_PREFIX_ . 'adv_click_fraud_whitelist`');
        if (!empty($whitelistItems)) {
            foreach ($whitelistItems as $item) {
                $rule = $item['ip_or_cidr'];
                if (strpos($rule, '/') !== false) {
                    if (self::checkIpInCidr($ip, $rule)) {
                        return; // Whitelisted subnet match found: silently bypass tracking evaluation matrix
                    }
                } else {
                    if ($ip === $rule) {
                        return; // Whitelisted static IP match found: silently bypass tracking evaluation matrix
                    }
                }
            }
        }

        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        $isBot = preg_match('/bot|crawl|slurp|spider|mediapartners|headless|curl|wget|headlesschrome/i', $userAgent) ? 1 : 0;
        
        // 2. REVERSE DNS DOUBLE-VERIFICATION MATRIX FOR LEGITIMATE SEARCH ENGINE SPIDERS
        if ($isBot && preg_match('/googlebot|bingbot|msnbot/i', $userAgent)) {
            $rdnsName = gethostbyaddr($ip);
            $isVerifiedSpider = false;
            $botDescription = 'Verified Search Engine Spider';

            if (preg_match('/\.googlebot\.com$/i', $rdnsName) || preg_match('/\.google\.com$/i', $rdnsName)) {
                $isVerifiedSpider = true;
                $botDescription = 'Verified Official Googlebot Server';
            } elseif (preg_match('/\.search\.msn\.com$/i', $rdnsName)) {
                $isVerifiedSpider = true;
                $botDescription = 'Verified Official Bingbot Server';
            }

            if ($isVerifiedSpider) {
                $forwardIp = gethostbyname($rdnsName);
                if ($forwardIp === $ip) {
                    $db->execute("
                        INSERT IGNORE INTO `" . _DB_PREFIX_ . "adv_click_fraud_whitelist` (`ip_or_cidr`, `description`, `date_add`)
                        VALUES ('" . pSQL($ip) . "', '" . pSQL($botDescription) . "', NOW())
                    ");
                    return; 
                }
            }
            
            $isBot = 1;
            $isScraper = 1;
        }

        if ($is_product_page && empty($referrer)) {
            $isBot = 1; 
        }

        $timeWindow = (int)Configuration::get('ADVCLICKFRAUD_TIME_WINDOW');
        $dateThreshold = date('Y-m-d H:i:s', time() - $timeWindow);

        $sessionToken = md5($ip . date('Y-m-d H'));
        $currentPage = Tools::getHttpHost(true) . $_SERVER['REQUEST_URI'];

        $isScraperLog = 0;

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
                $isScraperLog = 1;
            }

            $db->execute('
                INSERT INTO `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` (`ip_address`, `session_token`, `device_fingerprint`, `pages_visited`, `date_add`, `date_upd`)
                VALUES ("' . pSQL($ip) . '", "' . pSQL($sessionToken) . '", ' . ($fingerprint ? '"' . pSQL($fingerprint) . '"' : 'NULL') . ', "' . pSQL(json_encode($visitedPages)) . '", NOW(), NOW())
                ON DUPLICATE KEY UPDATE `pages_visited` = "' . pSQL(json_encode($visitedPages)) . '", ' . ($fingerprint ? '`device_fingerprint` = "' . pSQL($fingerprint) . '", ' : '') . ' `date_upd` = NOW()
            ');
        }

        // Calculate basic dynamic penalty fraud scores metrics levels
        $fraudScore = 0;
        if ($isBot) {
            $fraudScore += 60;
        }
        if ($isScraperLog || (isset($isScraper) && $isScraper)) {
            $fraudScore = 100;
        }

        // 3. DEVICE FINGERPRINT EVASION CORRELATION CHECK
        if (!empty($fingerprint) && strpos($fingerprint, 'dev_') === 0 && strlen($fingerprint) > 10) {
            $fingerprintConflict = $db->getValue('
                SELECT COUNT(DISTINCT `ip_address`) 
                FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs`
                WHERE `device_fingerprint` = "' . pSQL($fingerprint) . '"
                AND `ip_address` != "' . pSQL($ip) . '"
                AND `date_add` >= "' . pSQL($dateThreshold) . '"
            ');

            if ((int)$fingerprintConflict > 0) {
                $fraudScore = 100;
            }
        }

        // 4. CLASS C /24 SUBNET AGGREGATE ROTATION DETECTION LOGIC
        // We isolate the first three octets of IPv4 to trace distributed proxy cycling
        if (strpos($ip, '.') !== false) {
            $ipParts = explode('.', $ip);
            if (count($ipParts) === 4) {
                $subnetClassC = $ipParts[0] . '.' . $ipParts[1] . '.' . $ipParts[2] . '.%';
                
                // Aggregate total ad clicks or product view flags hit by this whole /24 subnet class range
                $subnetClicksAgg = (int)$db->getValue('
                    SELECT SUM(`click_count`) 
                    FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs`
                    WHERE `ip_address` LIKE "' . pSQL($subnetClassC) . '"
                    AND `date_add` >= "' . pSQL($dateThreshold) . '"
                ');

                $clickLimit = (int)Configuration::get('ADVCLICKFRAUD_CLICK_LIMIT');
                if ($subnetClicksAgg >= $clickLimit && $is_ad_click) {
                    $fraudScore = 100; // Instantly scale penalty: the subnet has collectively exhausted your configuration click thresholds
                }
            }
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
            if ($isScraperLog || $isBot || $fraudScore == 100) {
                $finalScore = 100;
            }

            $db->update('adv_click_fraud_logs', array(
                'click_count' => $newClickCount,
                'fraud_score' => $finalScore,
                'is_scraper' => (int)($existingLog['is_scraper'] || $isScraperLog),
                'is_bot' => (int)($existingLog['is_bot'] || $isBot),
                'device_fingerprint' => $fingerprint ? pSQL($fingerprint) : $existingLog['device_fingerprint'],
                'date_upd' => date('Y-m-d H:i:s')
            ), 'id_log = ' . (int)$existingLog['id_log']);
        } else {
            if ($is_ad_click || $isScraperLog || $isBot || $fraudScore == 100) {
                $initialScore = ($fraudScore == 100) ? 100 : $fraudScore;
                $db->insert('adv_click_fraud_logs', array(
                    'ip_address' => pSQL($ip),
                    'device_fingerprint' => $fingerprint ? pSQL($fingerprint) : null,
                    'gclid' => pSQL($gclid),
                    'utm_source' => $is_ad_click ? ($utm_source ? pSQL($utm_source) : 'google_ads') : null,
                    'user_agent' => pSQL($userAgent),
                    'referrer' => pSQL($referrer),
                    'is_bot' => (int)$isBot,
                    'is_scraper' => (int)$isScraperLog,
                    'fraud_score' => (int)$initialScore,
                    'click_count' => $is_ad_click ? 1 : 0,
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s')
                ));
            }
        }
    }

    /**
     * Receives raw background telemetric payloads from front-end beacon threads and matches fingerprints
     */
    public static function updateSessionTelemetry($token, $ip, $data)
    {
        $db = Db::getInstance();
        $existingSession = $db->getRow('SELECT * FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` WHERE `session_token` = "' . pSQL($token) . '"');

        $currentPage = isset($data['page']) ? $data['page'] : '';
        $resolution = isset($data['resolution']) ? $data['resolution'] : '';
        $fingerprint = isset($data['fingerprint']) ? $data['fingerprint'] : null;
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
                'device_fingerprint' => $fingerprint ? pSQL($fingerprint) : $existingSession['device_fingerprint'],
                'mouse_movements' => (int)($existingSession['mouse_movements'] + $mouseMoves),
                'key_presses' => (int)($existingSession['key_presses'] + $keyPresses),
                'date_upd' => date('Y-m-d H:i:s')
            ), 'id_session = ' . (int)$existingSession['id_session']);
        } else {
            $visitedPages = !empty($currentPage) ? json_encode(array($currentPage)) : json_encode(array());
            $db->insert('adv_click_fraud_sessions', array(
                'ip_address' => pSQL($ip),
                'session_token' => pSQL($token),
                'device_fingerprint' => $fingerprint ? pSQL($fingerprint) : null,
                'duration' => (int)$duration,
                'pages_visited' => pSQL($visitedPages),
                'mouse_movements' => (int)$mouseMoves,
                'key_presses' => (int)$keyPresses,
                'screen_resolution' => pSQL($resolution),
                'date_add' => date('Y-m-d H:i:s'),
                'date_upd' => date('Y-m-d H:i:s')
            ));
        }

        if ($fingerprint) {
            $timeWindow = (int)Configuration::get('ADVCLICKFRAUD_TIME_WINDOW');
            $dateThreshold = date('Y-m-d H:i:s', time() - $timeWindow);
            
            $targetLog = $db->getRow('SELECT `id_log` FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `ip_address` = "' . pSQL($ip) . '" AND `date_add` >= "' . pSQL($dateThreshold) . '" ORDER BY `date_add` DESC');
            if ($targetLog) {
                $db->update('adv_click_fraud_logs', array('device_fingerprint' => pSQL($fingerprint)), 'id_log = ' . (int)$targetLog['id_log']);
            }
            
            self::evaluateVisitor($ip, false, null, null, false, $fingerprint);
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
            'duration', 'mouse_movements', 'key_presses', 'fraud_score', 'date_upd', 'device_fingerprint'
        );
        
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'date_upd';
        }
        
        $orderWay = (strtoupper($orderWay) === 'ASC') ? 'ASC' : 'DESC';

        $sql = '
            SELECT l.id_log, l.ip_address, l.user_agent, l.referrer,
                   MAX(IFNULL(l.device_fingerprint, s.device_fingerprint)) as device_fingerprint,
                   MAX(l.utm_source) as utm_source,
                   SUM(l.click_count) as click_count,
                   MAX(l.is_bot) as is_bot,
                   MAX(l.is_scraper) as is_scraper,
                   MAX(l.fraud_score) as fraud_score,
                   MAX(l.date_add) as date_add,
                   MAX(l.date_upd) as date_upd,
                   MAX(s.duration) as duration, 
                   MAX(s.mouse_movements) as mouse_movements, 
                   MAX(s.key_presses) as key_presses, 
                   s.screen_resolution,
                   IFNULL(JSON_LENGTH(s.pages_visited), 0) as total_pages_visited
            FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` l
            LEFT JOIN `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` s ON l.ip_address = s.ip_address
            GROUP BY l.ip_address
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
        
        $exportThreshold = (int)Configuration::get('ADVCLICKFRAUD_EXPORT_THRESHOLD');
        if ($exportThreshold <= 0) {
            $exportThreshold = 70;
        }

        return array(
            'total_clicks' => (int)$db->getValue('SELECT SUM(click_count) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs`'),
            'total_fraud' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `fraud_score` >= ' . (int)$exportThreshold),
            'avg_duration' => (int)$db->getValue('SELECT AVG(duration) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions`'),
            'bot_count' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `is_bot` = 1 OR `is_scraper` = 1')
        );
    }

    /**
     * Clear all rows from logs and sessions database tables safely executing truncate queries
     */
    public static function truncateAllTables()
    {
        $db = Db::getInstance();
        $q1 = $db->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'adv_click_fraud_logs`');
        $q2 = $db->execute('TRUNCATE TABLE `' . _DB_PREFIX_ . 'adv_click_fraud_sessions`');
        
        return ($q1 && $q2);
    }

    /**
     * Internal mathematical helper evaluating CIDR block subnets matching
     */
    private static function checkIpInCidr($ip, $cidr)
    {
        list($subnet, $bits) = explode('/', $cidr);
        if ($bits === null) {
            $bits = 32;
        }
        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);
        $mask = -1 << (32 - $bits);
        $subnetLong &= $mask;
        
        return ($ipLong & $mask) == $subnetLong;
    }
}
