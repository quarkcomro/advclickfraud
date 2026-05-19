<?php
/**
 * Ajax Frontend Tracking Controller
 * Multistore payload isolation via context shop query validation parameters.
 */

class AdvClickFraudTrackModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        $this->ajax = true; 
        parent::initContent();

        $rawPayload = file_get_contents('php://input');
        $data = json_decode($rawPayload, true);

        if (!$data || empty($data['token'])) {
            header('HTTP/1.1 400 Bad Request');
            exit('Invalid Payload');
        }

        $token = $data['token'];
        $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : Tools::getRemoteAddr();
        $id_shop = (int)Tools::getValue('id_shop', $this->context->shop->id);

        require_once _PS_MODULE_DIR_ . 'advclickfraud/classes/ClickFraudLog.php';
        ClickFraudLog::updateSessionTelemetry($token, $ip, $data, $id_shop);

        header('Content-Type: application/json');
        echo json_encode(['status' => 'success']);
        exit;
    }
}
