<?php
/**
 * Advanced Click Fraud Detector and Analytics
 * Frontend Telemetry AJAX Receiver Controller
 *
 * @author    Expert Developer
 * @copyright 2026 Expert Developer
 * @license   Commercial
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class AdvClickFraudTrackModuleFrontController extends ModuleFrontController
{
    /**
     * Parse raw asynchronous JSON telemetry streams injected from the client browser
     */
    public function initContent()
    {
        // Disable template generation to provide raw high-speed performance response
        $this->ajax = true; 
        parent::initContent();

        // Capture raw input stream payload compatible with fetch or sendBeacon APIs
        $rawPayload = file_get_contents('php://input');
        $data = json_decode($rawPayload, true);

        if (!$data || empty($data['token'])) {
            header('HTTP/1.1 400 Bad Request');
            exit('Error: Invalid Telemetry Payload Signature.');
        }

        $token = $data['token'];
        
        // Resolve structural proxy or reverse-proxy cloudflare real visitor IP metrics
        $ip = isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? $_SERVER['HTTP_CF_CONNECTING_IP'] : Tools::getRemoteAddr();

        // Forward behavioral dataset variables recursively back inside model layers
        require_once _PS_MODULE_DIR_ . 'advclickfraud/classes/ClickFraudLog.php';
        ClickFraudLog::updateSessionTelemetry($token, $ip, $data);

        // Output fast system acknowledgement payload signature
        header('Content-Type: application/json');
        echo json_encode(array('status' => 'success'));
        exit;
    }
}
