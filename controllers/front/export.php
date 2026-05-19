<?php
/**
 * AdWord Engine TXT Excluder API Link
 * Secures exports per precise Context Shop ID queries.
 */

class AdvClickFraudExportModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->ajax = true;
        parent::initContent();

        $secureToken = md5($this->module->name . _COOKIE_KEY_);
        $providedToken = Tools::getValue('secure_key');
        $id_shop = (int)Tools::getValue('id_shop', $this->context->shop->id);

        if ($secureToken !== $providedToken) {
            header('HTTP/1.1 403 Forbidden');
            exit('Access Denied');
        }

        $ips = Db::getInstance()->executeS('
            SELECT DISTINCT `ip_address` 
            FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` 
            WHERE `fraud_score` >= 70 AND `id_shop` = ' . (int)$id_shop
        ');

        header('Content-Type: text/plain; charset=utf-8');
        if (!empty($ips)) {
            foreach ($ips as $ip) {
                echo $ip['ip_address'] . "\n";
            }
        }
        exit;
    }
}
