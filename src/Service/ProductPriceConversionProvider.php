<?php

namespace CurrencyRate\Service;

if (!defined('_PS_VERSION_')) {
    exit;
}

use Context;

/**
 * Provides product price conversions using newest NBP rates stored in DB.
 */
class ProductPriceConversionProvider
{
    /**
     * Compute formatted prices of given product in allowed currencies using newest NBP rates.
     *
     * - Reads product amount (tax incl.) from product array, ArrayAccess (ProductLazyArray) or Product::getPriceStatic fallback.
     * - Converts from current context currency to PLN using newest rate, then PLN -> target.
     * - Formats with Locale::formatPrice when available; otherwise generic formatting.
     * - Silently handles any errors and returns an empty array on failure.
     *
     * @param mixed      $product Product array or ProductLazyArray; null to skip
     * @param string[]   $allowedIsoCodes Uppercase ISO 4217 codes to display
     * @return string[]  List of strings like "EUR: €12.34"
     */
    public function getPriceInCurrencies($product, array $allowedIsoCodes): array
    {
        try {
            // Determine current display currency ISO from context
            $context = \Context::getContext();
            $currentCurrencyIso = (isset($context->currency) && !empty($context->currency->iso_code))
                ? strtoupper($context->currency->iso_code)
                : 'PLN';

            $allowed = array_values(array_filter(array_map(function ($c) { return strtoupper(trim($c)); }, $allowedIsoCodes)));
            if (!$product || empty($allowed)) {
                return [];
            }

            // Determine amount (tax incl.)
            $amount = null;
            $priceAmount = $this->getProductField($product, 'price_amount');
            $price = $this->getProductField($product, 'price');
            $idProduct = $this->getProductField($product, 'id_product');

            if (is_numeric($priceAmount)) {
                $amount = (float) $priceAmount;
            } elseif (is_numeric($price)) {
                $amount = (float) $price;
            } elseif ($idProduct) {
                $amount = (float) \Product::getPriceStatic((int) $idProduct, true);
            }
            if ($amount === null) {
                return [];
            }

            // Need newest rates for allowed + current currency
            $needIsos = $allowed;
            if (!in_array($currentCurrencyIso, $needIsos, true)) {
                $needIsos[] = $currentCurrencyIso;
            }

            $rates = $this->fetchLatestRatesFor($needIsos);

            // Convert current -> PLN
            $amountPln = null;
            if ($currentCurrencyIso === 'PLN') {
                $amountPln = $amount;
            } elseif (isset($rates[$currentCurrencyIso])) {
                $amountPln = $amount * $rates[$currentCurrencyIso];
            }
            if ($amountPln === null) {
                return [];
            }

            $out = [];
            foreach ($allowed as $targetIso) {
                if (!isset($rates[$targetIso]) || $rates[$targetIso] <= 0) {
                    continue;
                }
                $targetAmount = $amountPln / $rates[$targetIso];
                $out[] = $targetIso . ': ' . $this->formatAmount($targetAmount, $targetIso);
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Safe accessor for product fields from array, ArrayAccess (e.g., ProductLazyArray) or object.
     * Returns null when missing or on access error.
     *
     * @param mixed  $product
     * @param string $key
     * @return mixed|null
     */
    private function getProductField($product, string $key)
    {
        try {
            if (is_array($product)) {
                return array_key_exists($key, $product) ? $product[$key] : null;
            }
            if ($product instanceof \ArrayAccess) {
                // Some ArrayAccess implementations may throw on offsetExists; guard with try
                try {
                    if ($product->offsetExists($key)) {
                        return $product->offsetGet($key);
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }
            if (is_object($product)) {
                if (isset($product->$key)) {
                    return $product->$key;
                }
                // common getter naming
                $method = 'get' . str_replace(' ', '', ucwords(str_replace('_', ' ', $key)));
                if (method_exists($product, $method)) {
                    return $product->$method();
                }
            }
        } catch (\Throwable $e) {
            // ignore access errors
        }
        return null;
    }

    /**
     * Fetch newest (by effective_date) rates for given ISO codes from nbp_rate (table A).
     * Returns a map ISO => rate (PLN per 1 unit of ISO).
     *
     * @param string[] $isoCodes Uppercase ISO list
     * @return array<string,float>
     */
    private function fetchLatestRatesFor(array $isoCodes): array
    {
        $isoCodes = array_values(array_filter(array_map(function ($c) { return strtoupper(trim($c)); }, $isoCodes)));
        if (empty($isoCodes)) {
            return [];
        }
        $inParts = [];
        foreach ($isoCodes as $iso) {
            if ($iso !== '') {
                $inParts[] = "'" . pSQL($iso) . "'";
            }
        }
        if (empty($inParts)) {
            return [];
        }
        $table = _DB_PREFIX_ . 'nbp_rate';
        $sql = 'SELECT r.currency_code, r.rate, r.effective_date FROM ' . $table . ' r '
            . 'INNER JOIN (SELECT currency_code, MAX(effective_date) AS max_date FROM ' . $table
            . " WHERE table_type='A' AND currency_code IN (" . implode(',', $inParts) . ") GROUP BY currency_code) x "
            . "ON (x.currency_code = r.currency_code AND x.max_date = r.effective_date) WHERE r.table_type='A'";
        $rows = \Db::getInstance()->executeS($sql) ?: [];
        $rates = [];
        foreach ($rows as $row) {
            $iso = strtoupper($row['currency_code']);
            $rates[$iso] = (float) $row['rate'];
        }
        return $rates;
    }

    /**
     * Format price using Locale::formatPrice when available; otherwise a generic fallback.
     */
    private function formatAmount(float $amount, string $iso): string
    {
        try {
            $context = \Context::getContext();
            if (isset($context->currentLocale) && $context->currentLocale && method_exists($context->currentLocale, 'formatPrice')) {
                return $context->currentLocale->formatPrice($amount, $iso);
            }
        } catch (\Throwable $e) {
            // ignore and use fallback
        }
        // Fallback formatting when Locale is not available
        return number_format($amount, 2, '.', ' ') . ' ' . $iso;
    }
}
