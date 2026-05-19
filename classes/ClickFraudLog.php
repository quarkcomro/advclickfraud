<?php
class ClickFraudLog extends ObjectModel
{
    public static function logAdClick($ip, $gclid, $utm_source)
    {
        $db = Db::getInstance();
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        $referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
        
        // Analiză euristică bot din User-Agent
        $isBot = preg_match('/bot|crawl|slurp|spider|mediapartners|headless/i', $userAgent) ? 1 : 0;

        // Determinare scor de fraudă inițial
        $fraudScore = 0;
        if ($isBot) $fraudScore += 60;
        if (empty($referrer) && !empty($gclid)) $fraudScore += 20; // Reclamele din Google au de obicei un referrer valid

        // Verificăm istoricul din baza de date pentru acest IP în fereastra de timp selectată
        $timeWindow = (int)Configuration::get('ADVCLICKFRAUD_TIME_WINDOW');
        $dateThreshold = date('Y-m-d H:i:s', time() - $timeWindow);

        $existingLog = $db->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` 
             WHERE `ip_address` = "' . pSQL($ip) . '" 
             AND `date_add` >= "' . pSQL($dateThreshold) . '"'
        );

        if ($existingLog) {
            $newClickCount = (int)$existingLog['click_count'] + 1;
            $limit = (int)Configuration::get('ADVCLICKFRAUD_CLICK_LIMIT');
            
            // Creștem exponențial scorul de fraudă pe măsură ce limitele sunt încălcate
            $extraScore = ($newClickCount > $limit) ? 40 : 15;
            $finalScore = min(100, (int)$existingLog['fraud_score'] + $extraScore);

            $db->update('adv_click_fraud_logs', [
                'click_count' => $newClickCount,
                'fraud_score' => $finalScore,
                'date_upd' => date('Y-m-d H:i:s')
            ], 'id_log = ' . (int)$existingLog['id_log']);
        } else {
            $db->insert('adv_click_fraud_logs', [
                'ip_address' => pSQL($ip),
                'gclid' => pSQL($gclid),
                'utm_source' => pSQL($utm_source),
                'user_agent' => pSQL($userAgent),
                'referrer' => pSQL($referrer),
                'is_bot' => (int)$isBot,
                'fraud_score' => (int)$fraudScore,
                'date_add' => date('Y-m-d H:i:s'),
                'date_upd' => date('Y-m-d H:i:s')
            ]);
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

        // Ajustăm dinamic scorul de fraudă general bazat pe lipsa de interacțiune umană (Headless Browsers / Boți rapizi)
        if ($duration > 5 && $mouseMoves == 0 && $keyPresses == 0) {
            $db->execute('UPDATE `' . _DB_PREFIX_ . 'adv_click_fraud_logs` SET `fraud_score` = LEAST(100, `fraud_score` + 35) WHERE `ip_address` = "' . pSQL($ip) . '"');
        }
    }

    public static function getAllLogs($limit = 50)
    {
        return Db::getInstance()->executeS('
            SELECT l.*, s.duration, s.mouse_movements, s.key_presses, s.screen_resolution 
            FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` l
            LEFT JOIN `' . _DB_PREFIX_ . 'adv_click_fraud_sessions` s ON l.ip_address = s.ip_address
            ORDER BY l.date_upd DESC LIMIT ' . (int)$limit
        );
    }

    public static function getGlobalStats()
    {
        $db = Db::getInstance();
        return [
            'total_clicks' => (int)$db->getValue('SELECT SUM(click_count) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs`'),
            'total_fraud' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `fraud_score` >= 70'),
            'avg_duration' => (int)$db->getValue('SELECT AVG(duration) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_sessions`'),
            'bot_count' => (int)$db->getValue('SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `is_bot` = 1')
        ];
    }
}
