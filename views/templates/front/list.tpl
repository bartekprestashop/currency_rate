{**
 * Demo full-width template for currency_rate module
 * This template is rendered by the front controller: currency_rateDemoModuleFrontController
 * It uses the theme's layout-full-width.tpl layout via controller's getLayout()
 *}
{extends file="page.tpl"}

{block name='page_content'}
<div class="container-fluid">
  <div class="row">
    <div class="col-xs-12">
      <h1>NBP rates from last 30 days</h1>

      {if isset($data) && $data}
        <div class="table-responsive">
          <table class="table table-striped">
            <thead>
              <tr>
                {assign var=currSort value=$sort|default:'effective_date'}
                {assign var=currDir value=$dir|default:'desc'}

                {assign var=isEff value=($currSort=='effective_date')}
                {assign var=effNext value=($isEff && $currDir=='asc') ? 'desc' : 'asc'}
                <th>
                  <a href="{$pagination.baseUrl|escape:'html':'UTF-8'}?page=1&sort=effective_date&dir={$effNext|escape:'html':'UTF-8'}">
                    Effective date
                    {if $isEff}
                      {if $currDir=='asc'}▲{else}▼{/if}
                    {else}
                      ⇵
                    {/if}
                  </a>
                </th>

                {assign var=isCur value=($currSort=='currency_code')}
                {assign var=curNext value=($isCur && $currDir=='asc') ? 'desc' : 'asc'}
                <th>
                  <a href="{$pagination.baseUrl|escape:'html':'UTF-8'}?page=1&sort=currency_code&dir={$curNext|escape:'html':'UTF-8'}">
                    Currency
                    {if $isCur}
                      {if $currDir=='asc'}▲{else}▼{/if}
                    {else}
                      ⇵
                    {/if}
                  </a>
                </th>

                <th>Table</th>

                {assign var=isRate value=($currSort=='rate')}
                {assign var=rateNext value=($isRate && $currDir=='asc') ? 'desc' : 'asc'}
                <th class="text-right">
                  <a href="{$pagination.baseUrl|escape:'html':'UTF-8'}?page=1&sort=rate&dir={$rateNext|escape:'html':'UTF-8'}">
                    Rate
                    {if $isRate}
                      {if $currDir=='asc'}▲{else}▼{/if}
                    {else}
                      ⇵
                    {/if}
                  </a>
                </th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$data item=row}
                <tr>
                  <td>{$row.effective_date|escape:'html':'UTF-8'}</td>
                  <td>{$row.currency_code|escape:'html':'UTF-8'}</td>
                  <td>{$row.table_type|escape:'html':'UTF-8'}</td>
                  <td class="text-right">{$row.rate|floatval}</td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        </div>

        {if isset($pagination)}
          <div class="row">
            <div class="col-xs-12">
              <nav aria-label="Pagination">
                <ul class="pagination">
                  {assign var=curr value=$pagination.page}
                  {assign var=totalPages value=$pagination.totalPages}
                  {assign var=currSort value=$sort|default:'effective_date'}
                  {assign var=currDir value=$dir|default:'desc'}

                  {if $curr > 1}
                    <li class="page-item">
                      <a class="page-link" href="{$pagination.baseUrl|escape:'html':'UTF-8'}?page={$curr-1}&sort={$currSort|escape:'html':'UTF-8'}&dir={$currDir|escape:'html':'UTF-8'}" rel="prev">&laquo; Prev</a>
                    </li>
                  {else}
                    <li class="page-item disabled"><span class="page-link">&laquo; Prev</span></li>
                  {/if}

                  {assign var=start value=max(1, $curr-2)}
                  {assign var=end value=min($totalPages, $curr+2)}
                  {section name=i start=$start loop=$end+1 step=1}
                    {if $smarty.section.i.index == $curr}
                      <li class="page-item active"><span class="page-link">{$smarty.section.i.index}</span></li>
                    {else}
                      <li class="page-item"><a class="page-link" href="{$pagination.baseUrl|escape:'html':'UTF-8'}?page={$smarty.section.i.index}&sort={$currSort|escape:'html':'UTF-8'}&dir={$currDir|escape:'html':'UTF-8'}">{$smarty.section.i.index}</a></li>
                    {/if}
                  {/section}

                  {if $curr < $totalPages}
                    <li class="page-item">
                      <a class="page-link" href="{$pagination.baseUrl|escape:'html':'UTF-8'}?page={$curr+1}&sort={$currSort|escape:'html':'UTF-8'}&dir={$currDir|escape:'html':'UTF-8'}" rel="next">Next &raquo;</a>
                    </li>
                  {else}
                    <li class="page-item disabled"><span class="page-link">Next &raquo;</span></li>
                  {/if}
                </ul>
                <p class="text-muted">Page {$curr} of {$totalPages} &middot; {$pagination.total} records</p>
              </nav>
            </div>
          </div>
        {/if}
      {else}
        <p>No rates available for the selected period.</p>
      {/if}

    </div>
  </div>
</div>
{/block}