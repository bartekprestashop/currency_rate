<?php
/**
 * Front controller: list (full-width layout)
 * URL example:
 *   /module/currency_rate/list
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

use CurrencyRate\Service\NbpRatesProvider;

class currency_rateListModuleFrontController extends ModuleFrontController
{

    public function initContent()
    {
        parent::initContent();

        // Parameters
        $days = 30; // keep last 30 days scope like before
        $page = max(1, (int)\Tools::getValue('page', 1));
        $perPage = (int) \Configuration::get('CURRENCY_RATE_DEMO_PER_PAGE');
        if ($perPage <= 0) { $perPage = 30; }

        // Sorting params
        $sort = (string) \Tools::getValue('sort', 'effective_date');
        $dir = strtolower((string) \Tools::getValue('dir', 'desc'));
        $allowedSorts = ['effective_date', 'currency_code', 'rate'];
        if (!in_array($sort, $allowedSorts, true)) { $sort = 'effective_date'; }
        if ($dir !== 'asc' && $dir !== 'desc') { $dir = 'desc'; }

        // Data provider
        $provider = new NbpRatesProvider();
        $result = $provider->fetchLastDaysFromDbPaginated($days, $page, $perPage, null, $sort, $dir);
        $rows = $result['rows'] ?? [];
        $total = (int) ($result['total'] ?? 0);
        $totalPages = (int) max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
            $result = $provider->fetchLastDaysFromDbPaginated($days, $page, $perPage, null, $sort, $dir);
            $rows = $result['rows'] ?? [];
            $total = (int) ($result['total'] ?? 0);
        }

        $baseParams = [];
        $baseUrl = $this->context->link->getModuleLink($this->module->name, 'list', $baseParams, true);

        $this->context->smarty->assign([
            'data' => $rows,
            'sort' => $sort,
            'dir' => $dir,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
                'baseUrl' => $baseUrl,
            ],
        ]);

        $this->setTemplate('module:currency_rate/views/templates/front/list.tpl');
    }
}
