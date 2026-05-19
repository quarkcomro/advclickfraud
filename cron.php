<?php
/**
 * Advanced Click Fraud Detector and Analytics - Automation Cron Engine
 *
 * @author    Expert Developer
 * @copyright 2026 Expert Developer
 * @license   Commercial
 */

// Req 5: Autonomous Local Cron Endpoint bypassing heavy framework loading overhead for speed performance
require_once(dirname(__FILE__).'/../../config/config.inc.php');
require_once(dirname(__FILE__).'/../../init.php');

$moduleName = 'advclickfraud';
$secureToken = md5($moduleName . _COOKIE_KEY_);
$providedToken = Tools::getValue('secure_key');

if (empty($providedToken) || $secureToken !== $providedToken) {
    header('HTTP/1.1 403 Forbidden');
    exit('Automated Engine: Authentication Token Mismatch.');
}

require_once _PS_MODULE_DIR_ . 'advclickfraud/classes/ClickFraudLog.php';

// Triggers background routine automation tasks natively
ClickFraudLog::cleanOldLogs();

// Req 5: Write static structural export down to disc directly for network performance caching bypasses
$threshold = (int)Configuration::get('ADVCLICKFRAUD_EXPORT_THRESHOLD');
$ips = Db::getInstance()->executeS('SELECT DISTINCT `ip_address` FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` WHERE `fraud_score` >= ' . (int)$threshold);

$file_path = dirname(__FILE__) . '/blocked_ips.txt';
$file_content = "";
if (!empty($ips)) {
    foreach ($ips as $ip) {
        $file_content .= $ip['ip_address'] . "\n";
    }
}

file_put_contents($file_path, $file_content);

echo "Automated Click Fraud routines executed successfully. Block list written to static text buffer storage.";
exit;
