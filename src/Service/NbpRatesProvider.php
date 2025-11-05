<?php

namespace CurrencyRate\Service;

use Exception;
use CurrencyRate\Model\NbpRate;
use CurrencyRate\Util\Logger;

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Service that imports NBP exchange rates into the `nbp_rate` ObjectModel table.
 */
class NbpRatesProvider
{
    /** @var NbpApiClient */
    private $api;

    /** Cron lock key names and settings */
    private const CFG_ADD_LOGS = 'CURRENCY_RATE_ADD_LOGS';
    private const CFG_CRON_TOKEN = 'CURRENCY_RATE_CRON_TOKEN';
    private const CFG_CRON_LOCK = 'CURRENCY_RATE_CRON_LOCK';
    private const CFG_CRON_LOCK_TS = 'CURRENCY_RATE_CRON_LOCK_TS';
    private const CFG_LAST_IMPORT_A = 'CURRENCY_RATE_LAST_IMPORT_DATE_A';
    private const CFG_HISTORY_DAYS = 'CURRENCY_RATE_NBP_HISTORY_DAYS';

    /** Lock expiry in seconds (avoid overlapping cron runs). */
    private const LOCK_TTL = 15 * 60; // 15 minutes

    public function __construct(?NbpApiClient $api = null)
    {
        $this->api = $api ?: new NbpApiClient();
    }

    /**
     * Import rates for a specific date (Y-m-d) and table (A by default).
     * Returns a summary with inserted and skipped counts.
     *
     * @param string $date  Format Y-m-d, or 'today'.
     * @param string $table One of A|B (table C has bids/asks, not implemented here).
     * @return array
     * @throws Exception
     */
    public function importDate(string $date, string $table = 'A'): array
    {
        $dateNorm = $this->normalizeDate($date);
        $table = strtoupper($table);

        $data = $this->api->getRates($table, $dateNorm);
        $summary = $this->persistTablesPayload($data, $table);
        // Prune old history after each import
        $summary['pruned'] = $this->pruneOldRates();
        return $summary;
    }

    /**
     * Import rates for a range inclusive (YYYY-MM-DD/YYYY-MM-DD).
     * Uses NBP range endpoint to minimize calls.
     */
    public function importRange(string $fromDate, string $toDate, string $table = 'A'): array
    {
        $from = $this->normalizeDate($fromDate);
        $to = $this->normalizeDate($toDate);
        $table = strtoupper($table);

        $data = $this->api->getRates($table, $from . '/' . $to);
        $summary = $this->persistTablesPayload($data, $table);
        // Prune old history after each import
        $summary['pruned'] = $this->pruneOldRates();
        return $summary;
    }

    /**
     * Safe import for today, guarded by lock + idempotency using LAST_IMPORT_DATE.
     */
    public function importTodaySafely(string $table = 'A'): array
    {
        $table = strtoupper($table);
        $today = date('Y-m-d');

        // Already imported today?
        $lastKey = $this->getLastImportKeyForTable($table);
        $last = \Configuration::get($lastKey);
        if ($last === $today) {
            return [
                'status' => 'skipped',
                'reason' => 'already_imported_today',
                'table' => $table,
                'effective_dates' => [$today],
                'inserted' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        }

        // Acquire lock
        if (!$this->acquireLock()) {
            return [
                'status' => 'skipped',
                'reason' => 'locked',
                'table' => $table,
                'effective_dates' => [],
                'inserted' => 0,
                'skipped' => 0,
                'errors' => 0,
            ];
        }

        try {
            $summary = $this->importDate('today', $table);
            // Mark last import date if at least one date imported
            if (!empty($summary['effective_dates'])) {
                // Choose the latest date in the set
                $latest = max($summary['effective_dates']);
                if ($latest) {
                    \Configuration::updateValue($lastKey, $latest);
                }
            } else {
                // When API returns nothing (holiday), still mark as today to avoid refetching multiple times a day
                \Configuration::updateValue($lastKey, $today);
            }
            return array_merge(['status' => 'ok', 'table' => $table], $summary);
        } catch (\Throwable $e) {
            Logger::error('Cron import error: ' . $e->getMessage());
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'table' => $table,
                'inserted' => 0,
                'skipped' => 0,
                'errors' => 1,
                'effective_dates' => [],
            ];
        } finally {
            $this->releaseLock();
        }
    }

    /**
     * Persist NBP tables JSON into nbp_rate table.
     * NBP tables payload structure (table A/B):
     * [
     *   {
     *     "table": "A",
     *     "effectiveDate": "2025-11-05",
     *     "rates": [ {"currency": "dolar amerykański", "code": "USD", "mid": 3.95}, ... ]
     *   }, ... (multiple days in range)
     * ]
     */
    private function persistTablesPayload(?array $payload, string $table): array
    {
        $inserted = 0;
        $skipped = 0;
        $errors = 0;
        $dates = [];

        if (!is_array($payload)) {
            throw new Exception('Unexpected NBP payload');
        }

        foreach ($payload as $day) {
            if (!isset($day['effectiveDate'], $day['rates']) || !is_array($day['rates'])) {
                continue;
            }
            $date = $day['effectiveDate'];
            $dates[$date] = true;

            foreach ($day['rates'] as $rateRow) {
                $code = $rateRow['code'] ?? null;
                $rate = $rateRow['mid'] ?? null; // For table A/B
                if (!$code || $rate === null) {
                    continue;
                }
                $nr = new NbpRate();
                $nr->currency_code = $code;
                $nr->table_type = $table;
                $nr->rate = (float)$rate;
                $nr->effective_date = $date;
                try {
                    if ($nr->add()) {
                        $inserted++;
                    } else {
                        // When add() returns false, treat as skipped
                        $skipped++;
                    }
                } catch (\PrestaShopDatabaseException $e) {
                    // Duplicate key -> skip; otherwise count as error
                    if (stripos($e->getMessage(), 'Duplicate') !== false) {
                        $skipped++;
                    } else {
                        $errors++;
                        Logger::error('DB error inserting rate ' . $code . ' ' . $date . ': ' . $e->getMessage());
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    Logger::error('Error inserting rate ' . $code . ' ' . $date . ': ' . $e->getMessage());
                }
            }
        }

        return [
            'inserted' => $inserted,
            'skipped' => $skipped,
            'errors' => $errors,
            'effective_dates' => array_keys($dates),
        ];
    }

    private function normalizeDate(string $date): string
    {
        if ($date === 'today') {
            return date('Y-m-d');
        }
        // Accept YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }
        // Try strtotime
        $ts = strtotime($date);
        if ($ts !== false) {
            return date('Y-m-d', $ts);
        }
        throw new Exception('Invalid date format: ' . $date);
    }

    private function acquireLock(): bool
    {
        $now = time();
        $locked = (int) \Configuration::get(self::CFG_CRON_LOCK);
        $lockedAt = (int) \Configuration::get(self::CFG_CRON_LOCK_TS);
        if ($locked && ($now - $lockedAt) < self::LOCK_TTL) {
            return false;
        }
        \Configuration::updateValue(self::CFG_CRON_LOCK, 1);
        \Configuration::updateValue(self::CFG_CRON_LOCK_TS, (string)$now);
        return true;
    }

    private function releaseLock(): void
    {
        \Configuration::updateValue(self::CFG_CRON_LOCK, 0);
        \Configuration::updateValue(self::CFG_CRON_LOCK_TS, '0');
    }

    private function getLastImportKeyForTable(string $table): string
    {
        switch ($table) {
            case 'A':
            default:
                return self::CFG_LAST_IMPORT_A;
        }
    }

    /**
     * Delete NBP rates older than configured history window.
     * Uses Configuration CURRENCY_RATE_NBP_HISTORY_DAYS (default 30).
     * Returns number of rows scheduled for deletion (counted before delete).
     */
    private function pruneOldRates(): int
    {
        // Read and sanitize retention days
        $daysRaw = (string) \Configuration::get(self::CFG_HISTORY_DAYS);
        $days = (int) $daysRaw;
        if ($days <= 0) {
            // Non-positive means keep all history
            return 0;
        }
        // Safety clamp: up to 10 years
        $days = max(1, min(3650, $days));
        $cutoffDate = date('Y-m-d', strtotime('-' . $days . ' days'));

        $tableName = _DB_PREFIX_ . 'nbp_rate';
        $where = '`effective_date` < \'' . pSQL($cutoffDate) . '\'';

        // Count rows first
        $sqlCount = 'SELECT COUNT(*) FROM `' . bqSQL($tableName) . '` WHERE ' . $where;
        $toDelete = (int) \Db::getInstance()->getValue($sqlCount);
        if ($toDelete <= 0) {
            return 0;
        }

        // Perform deletion using core helper to add prefix safely
        \Db::getInstance()->delete('nbp_rate', $where);

        // Optional logging
        $addLogs = (bool) (int) \Configuration::get(self::CFG_ADD_LOGS);
        if ($addLogs) {
            Logger::info('Pruned old NBP rates: ' . $toDelete . ' rows older than ' . $cutoffDate . ' (keep ' . $days . ' days)');
        }

        return $toDelete;
    }

    /**
     * Read helper: fetch last N days of rates from DB.
     *
     * @param int $days Range length (default 30). Clamped to [1, 365].
     * @param string|null $table Optional table filter ('A'|'B'|'C').
     * @return array<int, array{currency_code:string, table_type:string, rate:float, effective_date:string, date_add:string}>
     */
    public function fetchLastDaysFromDb(int $days = 30, ?string $table = null): array
    {
        // Clamp and compute from date
        $days = max(1, min(365, (int) $days));
        $fromDate = date('Y-m-d', strtotime('-' . $days . ' days'));

        // Use ObjectModel collection instead of raw SQL
        $collection = new \PrestaShopCollection(NbpRate::class);
        // Filters
        $collection->where('effective_date', '>=', pSQL($fromDate));
        if ($table) {
            $collection->where('table_type', '=', pSQL(strtoupper($table)));
        }
        // Ordering
        $collection->orderBy('effective_date', 'DESC');
        $collection->orderBy('currency_code', 'ASC');

        $rows = [];
        foreach ($collection as $item) {
            if ($item instanceof NbpRate) {
                $rows[] = [
                    'currency_code'   => (string) $item->currency_code,
                    'table_type'      => (string) $item->table_type,
                    'rate'            => (float) $item->rate,
                    'effective_date'  => (string) $item->effective_date,
                    'date_add'        => (string) $item->date_add,
                ];
            }
        }

        return $rows;
    }

    /**
     * Paginated fetch for last N days.
     * @return array{rows: array<int, array{currency_code:string, table_type:string, rate:float, effective_date:string, date_add:string}>, total:int}
     */
    public function fetchLastDaysFromDbPaginated(int $days, int $page, int $perPage, ?string $table = null, ?string $sort = null, ?string $dir = null): array
    {
        $days = max(1, min(365, (int)$days));
        $page = max(1, (int)$page);
        $perPage = max(1, min(200, (int)$perPage));
        $fromDate = date('Y-m-d', strtotime('-' . $days . ' days'));

        $where = 'WHERE `effective_date` >= \'' . pSQL($fromDate) . '\'';
        if ($table) {
            $where .= ' AND `table_type` = \'' . pSQL(strtoupper($table)) . '\'';
        }

        // Sorting
        $allowed = [
            'effective_date' => '`effective_date`',
            'currency_code'  => '`currency_code`',
            'rate'           => '`rate`',
        ];
        $sort = strtolower((string) $sort);
        if (!isset($allowed[$sort])) {
            $sort = 'effective_date';
        }
        $dir = strtolower((string) $dir);
        $dir = ($dir === 'asc') ? 'ASC' : 'DESC';

        // Build ORDER BY with stable secondary keys
        $orderByParts = [];
        $orderByParts[] = $allowed[$sort] . ' ' . $dir;
        // Secondary sorting to make list stable and predictable
        if ($sort !== 'effective_date') {
            $orderByParts[] = '`effective_date`' . ' DESC';
        }
        if ($sort !== 'currency_code') {
            $orderByParts[] = '`currency_code`' . ' ASC';
        }

        $tableName = _DB_PREFIX_ . 'nbp_rate';

        // Total count
        $sqlTotal = 'SELECT COUNT(*) FROM `' . bqSQL($tableName) . '` ' . $where;
        $total = (int) \Db::getInstance()->getValue($sqlTotal);

        // Rows
        $offset = ($page - 1) * $perPage;
        $offset = max(0, (int)$offset);
        $sqlRows = 'SELECT `currency_code`, `table_type`, `rate`, `effective_date`, `date_add`'
            . ' FROM `' . bqSQL($tableName) . '` '
            . $where
            . ' ORDER BY ' . implode(', ', $orderByParts)
            . ' LIMIT ' . (int)$perPage . ' OFFSET ' . (int)$offset;

        $rows = (array) \Db::getInstance()->executeS($sqlRows) ?: [];
        // Cast values similar to non-paginated method
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'currency_code'   => (string) $r['currency_code'],
                'table_type'      => (string) $r['table_type'],
                'rate'            => (float) $r['rate'],
                'effective_date'  => (string) $r['effective_date'],
                'date_add'        => (string) $r['date_add'],
            ];
        }

        return [
            'rows' => $out,
            'total' => $total,
        ];
    }
}
