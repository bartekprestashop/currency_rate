<?php
/**
 * Currency Rate module for PrestaShop 8.2+
 *
 * @author    Bartek G
 * @copyright 2025
 * @license   AFL-3.0 https://opensource.org/license/afl-3-0-php/
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use CurrencyRate\Util\Logger;
use CurrencyRate\Service\NbpRatesProvider;
use CurrencyRate\Service\ProductPriceConversionProvider;

class currency_rate extends Module
{
    public function __construct()
    {
        $this->name = 'currency_rate'; // technical name (no hyphen)
        $this->tab = 'pricing_promotion';
        $this->version = '0.0.1';
        $this->author = 'Bartek G';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->controllers = [];
        $this->ps_versions_compliancy = [
            'min' => '8.0.0',
            'max' => _PS_VERSION_,
        ];

        if (file_exists(__DIR__ . '/vendor/autoload.php')) {
            require_once __DIR__ . '/vendor/autoload.php';
        }

        parent::__construct();

        // Displayed in BO modules list
        $this->displayName = $this->trans('Currency Rate', [], 'Modules.Currencyrate.Admin');
        $this->description = $this->trans('Imports NBP currency rates into local database and exposes a cron endpoint.', [], 'Modules.Currencyrate.Admin');

        // Shown on the module card if the shop is not compliant with version
        $this->confirmUninstall = $this->trans('Are you sure you want to uninstall Currency Rate?', [], 'Modules.Currencyrate.Admin');
    }

    public function install()
    {
        $ok = parent::install() && $this->installDatabase();
        if (!$ok) {
            return false;
        }

        // Register hooks
        if (!$this->registerHook('displayProductPriceBlock')) {
            return false;
        }

        // Initialize configuration
        Configuration::updateValue('CURRENCY_RATE_DROP_TABLE_ON_UNINSTALL', "0");
        Configuration::updateValue('CURRENCY_RATE_ADD_LOGS', "1");
        Configuration::updateValue('CURRENCY_RATE_CRON_LOCK', 0);
        Configuration::updateValue('CURRENCY_RATE_CRON_LOCK_TS', '0');
        Configuration::updateValue('CURRENCY_RATE_ALLOWED_ON_PRODUCT_PAGE', 'EUR,USD,CZK');
        Configuration::updateValue('CURRENCY_RATE_NBP_HISTORY_DAYS', '30');
        if (!Configuration::get('CURRENCY_RATE_CRON_TOKEN')) {
            Configuration::updateValue('CURRENCY_RATE_CRON_TOKEN', bin2hex(random_bytes(16)));
        }
        // Front demo pagination default page size
        Configuration::updateValue('CURRENCY_RATE_DEMO_PER_PAGE', 30);

        // Backfill last 30 days (non-fatal on error)
        try {
            $provider = new NbpRatesProvider();
            $from = date('Y-m-d', strtotime('-30 days'));
            $to = date('Y-m-d');
            $summary = $provider->importRange($from, $to, 'A');
            Logger::info('Backfill completed: ' . json_encode($summary));
        } catch (\Throwable $e) {
            Logger::error('Backfill error: ' . $e->getMessage());
            // Do not fail installation on backfill issue
        }

        return true;
    }

    public function uninstall()
    {
        return $this->uninstallDatabase() && parent::uninstall();
    }

    /**
     * Install DB && local vars
     */
    protected function installDatabase()
    {
        $table = _DB_PREFIX_ . 'nbp_rate';
        $sql = sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
              `id_nbp` INT UNSIGNED NOT NULL AUTO_INCREMENT,
              `currency_code` VARCHAR(3) NOT NULL,
              `table_type` CHAR(1) NOT NULL DEFAULT "A",
              `rate` DECIMAL(12,6) NOT NULL,
              `effective_date` DATE NOT NULL,
              `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id_nbp`),
              UNIQUE KEY `currency_date_unique` (`currency_code`, `effective_date`, `table_type`)
            ) ENGINE=%s ',
            $table,
            _MYSQL_ENGINE_
        );

        return \Db::getInstance()->execute($sql);
    }

    /**
     * Drop tables if allowed to
     */
    protected function uninstallDatabase()
    {
        if(Configuration::get('CURRENCY_RATE_DROP_TABLE_ON_UNINSTALL') == "1"){
            $sql = 'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'nbp_rate`';
            return \Db::getInstance()->execute($sql);
        }
        return true;
    }

    /**
     * Hook into displayProductPriceBlock to add content for specific price blocks.
     *
     * @param array $params expects keys: 'type', 'product', and optionally 'hook_origin'
     * @return string
     */
    public function hookDisplayProductPriceBlock($params)
    {
        try {
            $type = isset($params['type']) ? (string)$params['type'] : '';
            $origin = isset($params['hook_origin']) ? (string)$params['hook_origin'] : '';

            // Act only on the weight block within the product sheet
            if ($type === 'after_price' && ($origin === '' || $origin === 'product_sheet')) {
                $product = isset($params['product']) ? $params['product'] : null;
                $priceInCurrencies = [];
                try {
                    // Read allowed currencies
                    $allowedCsv = (string) Configuration::get('CURRENCY_RATE_ALLOWED_ON_PRODUCT_PAGE');
                    $allowed = array_filter(array_map(function($c){ return strtoupper(trim($c)); }, explode(',', $allowedCsv)));

                    // Delegate conversion to provider
                    $provider = new ProductPriceConversionProvider();
                    $priceInCurrencies = $provider->getPriceInCurrencies($product, $allowed);

                } catch (\Throwable $e) {
                    // Do not break the page if anything goes wrong
                    // Optionally log: CurrencyRate\\Util\\Logger::error('Price conversion error: ' . $e->getMessage());
                }

                $this->context->smarty->assign([
                    'product' => $product,
                    'price_in_curriencies' => $priceInCurrencies,
                ]);
                return $this->fetch('module:currency_rate/views/templates/hook/price-block.tpl');
            }
        } catch (\Throwable $e) {
            // Silently fail to avoid breaking the product page
        }
        return '';
    }
}
