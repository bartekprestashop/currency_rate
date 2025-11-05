<?php

namespace CurrencyRate\Model;

if (!defined('_PS_VERSION_')) {
    exit;
}

use ObjectModel;

class NbpRate extends ObjectModel
{
    /** @var int Primary key */
    public $id_nbp;

    /** @var string ISO currency code (3 letters) */
    public $currency_code;

    /** @var string Table type (A/B/C) */
    public $table_type = 'A';

    /** @var float Decimal exchange rate */
    public $rate;

    /** @var string Effective date (Y-m-d) */
    public $effective_date;

    /** @var string Date add (Y-m-d H:i:s) */
    public $date_add;

    public static $definition = [
        'table' => 'nbp_rate',
        'primary' => 'id_nbp',
        'fields' => [
            // 'id_nbp' is the primary key
            'currency_code' => [
                'type' => self::TYPE_STRING,
                'required' => true,
                'size' => 3,
                'validate' => 'isLanguageIsoCode',
            ],
            'table_type' => [
                'type' => self::TYPE_STRING,
                'required' => true,
                'size' => 1,
                'validate' => 'isGenericName',
            ],
            'rate' => [
                'type' => self::TYPE_FLOAT,
                'required' => true,
                'validate' => 'isUnsignedFloat',
            ],
            'effective_date' => [
                'type' => self::TYPE_DATE,
                'required' => true,
                'validate' => 'isDateFormat',
            ],
            'date_add' => [
                'type' => self::TYPE_DATE,
                'required' => true,
                'validate' => 'isDateFormat',
            ],
        ],
    ];

    /**
     * Date_add is automatically set when adding
     */
    public function add($auto_date = true, $null_values = false)
    {
        if ($auto_date && empty($this->date_add)) {
            $this->date_add = date('Y-m-d H:i:s');
        }
        return parent::add($auto_date, $null_values);
    }
}
