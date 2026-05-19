<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class AdvClickFraudExportModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->ajax = true;
        parent::initContent();

        // Cheie de securitate simplă pentru a preveni scanarea publică a IP-urilor blocate
        $secureToken = Tools::encrypt($this->module->name);
        $providedToken = Tools::getValue('secure_key');

        if ($secureToken !== $providedToken) {
            header('HTTP/1.1 403 Forbidden');
            exit('Acces interzis. Token invalid.');
        }

        // Preluăm doar IP-urile care au atins pragul de Fraudă Critică (scor >= 70)
        $ips = Db::getInstance()->executeS('
            SELECT DISTINCT `ip_address` 
            FROM `' . _DB_PREFIX_ . 'adv_click_fraud_logs` 
            WHERE `fraud_score` >= 70
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
