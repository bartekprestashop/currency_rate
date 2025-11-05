<?php

namespace CurrencyRate\Service;

use CurrencyRate\Util\Logger;
use Exception;

class NbpApiClient
{
    private const BASE_URL = 'https://api.nbp.pl/api/';

    /**
     * Fetch exchange rates tables from NBP API.
     *
     * @param string $table One of 'A', 'B', or 'C'. Defaults to 'A'.
     * @param string $date  'today', specific 'YYYY-MM-DD', or a date range 'YYYY-MM-DD/YYYY-MM-DD'.
     *
     * @return array|null Decoded JSON as associative array.
     * @throws Exception When the HTTP request fails or decoding is impossible.
     */
    public function getRates(string $table = 'A', string $date = 'today'): ?array
    {
        // Preserve slashes in range dates while encoding individual parts
        $encodedTable = rawurlencode($table);
        if (strpos($date, '/') !== false) {
            [$from, $to] = explode('/', $date, 2);
            $encodedDate = rawurlencode($from) . '/' . rawurlencode($to);
        } else {
            $encodedDate = rawurlencode($date);
        }
        $url = self::BASE_URL . 'exchangerates/tables/' . $encodedTable . '/' . $encodedDate . '/?format=json';

        // Use a stream context to set a timeout and a UA to avoid some hosts blocking the request
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'ignore_errors' => true,
                'header' => [
                    'Accept: application/json',
                    'User-Agent: CurrencyRateModule/1.0 (+https://prestashop.com)'
                ],
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        Logger::info('NBP API request: ' . $url);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new Exception('NBP API request failed');
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Failed to decode NBP API response: ' . json_last_error_msg() .' '.var_export($data, true));
        }

        return $data;
    }
}
