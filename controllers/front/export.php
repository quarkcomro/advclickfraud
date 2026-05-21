<?php
/**
 * Advanced Click Fraud Detector and Analytics
 * Automated Data Export Controller with Subnet Aggregation
 *
 * @author    Expert Developer
 * @copyright 2026 Expert Developer
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdvClickFraudExportModuleFrontController extends ModuleFrontController
{
    /**
     * Compute and stream the absolute list of malicious IPs and collapsed CIDR /24 subnets
     */
    public function initContent()
    {
        // Suppress layout template loading for high speed streaming response
        $this->ajax = true;
        parent::initContent();

        // Security key verification token matching cookie configuration signatures
        $secureToken = md5($this->module->name . _COOKIE_KEY_);
        $providedToken = Tools::getValue('secure_key');

        if ($secureToken !== $providedToken) {
            header('HTTP/1.1 403 Forbidden');
            exit('Access Denied: Invalid Security Token Configuration.');
        }

        // Fetch user defined export percentage threshold level
        $exportThreshold = (int)Configuration::get('ADVCLICKFRAUD_EXPORT_THRESHOLD');
        if ($exportThreshold <= 0) {
            $exportThreshold = 70;
        }

        $db = Db::getInstance();
        
        // Select distinct target IPs matching or exceeding active penalty scores thresholds
        $query = '
            SELECT DISTINCT l.`ip_address` 
            FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` l
            WHERE l.`fraud_score` >= ' . (int)$exportThreshold . '
            AND l.`ip_address` NOT IN (
                SELECT DISTINCT `ip_or_cidr` FROM `' . _DB_PREFIX_ . 'adv_click_fraud_whitelist` WHERE `ip_or_cidr` NOT LIKE "%/%"
            )';

        $ips = $db->executeS($query);
        
        // Fetch structural whitelist rules to apply cross filtering subnets bypass parameters
        $cidrRules = $db->executeS('SELECT `ip_or_cidr` FROM `' . _DB_PREFIX_ . 'adv_click_fraud_whitelist` WHERE `ip_or_cidr` LIKE "%/%"');

        header('Content-Type: text/plain; charset=utf-8');
        
        if (!empty($ips)) {
            $rawIpsList = array();
            $subnetCounter = array();

            // First run filtering: strip manually whitelisted blocks and group candidates
            foreach ($ips as $ipRow) {
                $currentIp = $ipRow['ip_address'];
                $isWhitelistedCidr = false;

                if (!empty($cidrRules)) {
                    foreach ($cidrRules as $rule) {
                        if ($this->checkIpInCidr($currentIp, $rule['ip_or_cidr'])) {
                            $isWhitelistedCidr = true;
                            break;
                        }
                    }
                }

                if (!$isWhitelistedCidr && strpos($currentIp, '.') !== false) {
                    $rawIpsList[] = $currentIp;
                    
                    // Group tracking by Class C network prefixes
                    $ipParts = explode('.', $currentIp);
                    if (count($ipParts) === 4) {
                        $subnetPrefix = $ipParts[0] . '.' . $ipParts[1] . '.' . $ipParts[2];
                        if (!isset($subnetCounter[$subnetPrefix])) {
                            $subnetCounter[$subnetPrefix] = 0;
                        }
                        $subnetCounter[$subnetPrefix]++;
                    }
                }
            }

            // Second run optimization: output collapsed subnets or individual static items rows
            $printedSubnets = array();

            foreach ($rawIpsList as $ip) {
                $ipParts = explode('.', $ip);
                $subnetPrefix = $ipParts[0] . '.' . $ipParts[1] . '.' . $ipParts[2];

                // Hardening: If more than 1 distinct IP hit the log inside the same class, collapse to a /24 range automatically
                if ($subnetCounter[$subnetPrefix] > 1) {
                    $cidrBlock = $subnetPrefix . '.0/24';
                    if (!in_array($cidrBlock, $printedSubnets)) {
                        echo $cidrBlock . "\n";
                        $printedSubnets[] = $cidrBlock;
                    }
                } else {
                    // Check if this independent item is not already masked inside a printed block range
                    if (!in_array($subnetPrefix . '.0/24', $printedSubnets)) {
                        echo $ip . "\n";
                    }
                }
            }
        }
        exit;
    }

    /**
     * Mathematical helper verifying if an IPv4 address string fits inside a custom CIDR rule range
     */
    private function checkIpInCidr($ip, $cidr)
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
