<?php
/**
 * Front controller: cron
 * URL example:
 *   /module/currency_rate/cron?token=YOUR_TOKEN
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use CurrencyRate\Util\Logger;
use CurrencyRate\Service\NbpRatesProvider;
use CurrencyRate\Model\NbpRate;

class currency_rateCronModuleFrontController extends ModuleFrontController
{
    public $display_header = false;
    public $display_footer = false;
    public $ajax = true;

    public function initContent()
    {
        parent::initContent();
        header('Content-Type: application/json');

        $start = microtime(true);
        $token = Tools::getValue('token');
        $expected = Configuration::get('CURRENCY_RATE_CRON_TOKEN');
        if (!$token || !$expected || !hash_equals((string)$expected, (string)$token)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'forbidden',
                'message' => 'Invalid or missing token. Please check your module configuration. Token: ' . $token . ' Expected: ' . $expected . '',
            ]);
            exit;
        }

        try {
            $provider = new NbpRatesProvider();
            $summary = $provider->importTodaySafely('A');
            $summary['duration_ms'] = (int) round((microtime(true) - $start) * 1000);
            $summary['module'] = 'currency_rate';
            $summary['controller'] = 'cron';

            if ($summary['status'] === 'error') {
                http_response_code(500);
            } elseif ($summary['status'] === 'skipped') {
                http_response_code(200);
            }

            echo json_encode($summary);
        } catch (\Throwable $e) {
            Logger::error('Cron fatal error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage(),
            ]);
        }
        exit;
    }
}
