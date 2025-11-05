# currency_rate (PrestaShop 8+ module)

Author: Bartek G  
License: AFL-3.0

A lightweight module that stores NBP (Polish National Bank) currency rates in a local table and displays converted product prices (after price) on the product page for selected currencies. It is designed for PrestaShop 8.x and focuses on minimal footprint. This Module is a code sample and shows my coding style.
 
## Installation
1. Place the module in `prestashop/modules/currency_rate` (folder and main class name are `currency_rate`).
2. In Back Office: Improve → Modules → Module Manager → search for "Currency Rate" → Install.
3. On install, the module will:
   - Create the table `_nbp_rate`.
   - Register the `displayProductPriceBlock` hook.
   - Seed configuration defaults and attempt a 30‑day backfill using NBP rates (errors are logged and do not break install).
   - Create local variables && token for cron connection

## Configuration keys (Configuration)
- `CURRENCY_RATE_ALLOWED_ON_PRODUCT_PAGE` (string, default: `EUR,USD,CZK`)
    - Comma‑separated ISO codes to display under product price.
- `CURRENCY_RATE_ADD_LOGS` ("1"/"0", default: "1")
    - Enables simple module logging (see `Util\Logger` if present).
- `CURRENCY_RATE_CRON_LOCK` (int) and `CURRENCY_RATE_CRON_LOCK_TS` (string)
    - Internal flags reserved for import locking.
- `CURRENCY_RATE_DEMO_PER_PAGE` (int, default: 30)
    - Page size reserved for the demo view/template.
- `CURRENCY_RATE_DROP_TABLE_ON_UNINSTALL` ("1"/"0", default: "0")
    - If set to "1" before uninstall, drops the `_nbp_rate` table. 
- `CURRENCY_RATE_CRON_TOKEN` (string)
    - Random token generated on install; reserved for a future cron/import endpoint.

## Uninstallation
- Standard: uninstall from Module Manager.
- Table removal: set `CURRENCY_RATE_DROP_TABLE_ON_UNINSTALL = "1"` before uninstall if you want `_nbp_rate` to be dropped. By default it's preserved.

## Cron    
- Standard url will look like this : http://prestashop8.local/module/currency_rate/cron?token=CURRENCY_RATE_CRON_TOKEN where CURRENCY_RATE_CRON_TOKEN is a string created while installation
- Only for development and test this token is shown on screen when in json with message : Invalid or missing token. Please check your module configuration. Token: XX Expected: XX
- Cron will trigger only once a day, if you want to trigger it one more time reset CURRENCY_RATE_LAST_IMPORT_DATE_A

## Page with conversion rates from last 30 days  
- Url will be like : http://prestashop8.local/module/currency_rate/list

## What appears on the product page
- On each product page module is hooked to product price block
- In hook there is add link -> check price in other currencies - this shows modal 
- In modal all ALLOWED curriencies are shown - if you want to see more of them just add iso codes in DB in configuration at param CURRENCY_RATE_ALLOWED_ON_PRODUCT_PAGE.  

## Database
- There is one table that stores all imported currencies with conversion rates - nbp_rate 
- Rates are stored as PLN per 1 unit of the foreign currency (NBP table A). The newest effective_date per currency is used for conversions.

## Code map
- `currency_rate` — main module class (install/uninstall, hook registration and rendering).
- `NbpApiClient` - contains api communications function
- `NbpRatesPrivider` - drive api & npbrate logic
- `ProductPriceConverionProvider` - serves all data for prices and conversion rates

## Development notes
- Autoload: if a local `vendor/autoload.php` exists, it will be included during module construction.

## Changelog
- 0.0.1 (2025‑11‑05): This is only the module prototype - Not for production use; not fully tested. 
