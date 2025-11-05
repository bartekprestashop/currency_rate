{if isset($product)}
  <div class="cr-weight-extra" data-module="currency_rate">

    <button type="button" class="btn btn-link p-0 cr-open-modal" data-target="#cr-modal-{$product.id_product|default:0}">
      CHECK PRICE IN OTHER CURRENCY
      <i class="material-icons">open_in_new</i>
    </button>

    <div id="cr-modal-{$product.id_product|default:0}" class="cr-modal" role="dialog" aria-hidden="true" aria-labelledby="cr-modal-{$product.id_product|default:0}-title">
      <div class="cr-modal__dialog" role="document">
        <div class="cr-modal__header">
          <h3 id="cr-modal-{$product.id_product|default:0}-title" class="cr-modal__title">Currency Rate Modal</h3>
          <button type="button" class="cr-modal__close cr-modal-close" aria-label="Close">&times;</button>
        </div>
        <div class="cr-modal__body">
          <p>Prices in different currencies : </p>
          <ul>
            <li>Product ID: {$product.id_product|default:'n/a'}</li>
            {foreach from=$price_in_curriencies item='priceCurrency'}
              <li>Price: {$priceCurrency}</li>
            {/foreach}
          </ul>
        </div>
        <div class="cr-modal__footer">
          <button type="button" class="btn btn-primary cr-modal-close">Close</button>
        </div>
      </div>
    </div>

    {literal}
    <style>
      .cr-modal { position: fixed; inset: 0; display: none; align-items: center; justify-content: center; background: rgba(0,0,0,0.5); z-index: 1050; padding: 1rem; }
      .cr-modal.is-open { display: flex; }
      .cr-modal__dialog { background: #fff; border-radius: .25rem; max-width: 520px; width: 100%; box-shadow: 0 10px 30px rgba(0,0,0,.3); overflow: hidden; }
      .cr-modal__header { display:flex; align-items:center; justify-content:space-between; padding:.75rem 1rem; border-bottom: 1px solid #eee; }
      .cr-modal__title { margin: 0; font-size: 1.1rem; }
      .cr-modal__close { background:none; border:0; font-size:1.5rem; line-height:1; cursor:pointer; padding:.25rem .5rem; }
      .cr-modal__body { padding: 1rem; }
      .cr-modal__footer { padding: .75rem 1rem; border-top: 1px solid #eee; text-align: right; }
    </style>

    <script>
      (function(){
        function openModal(modal){ if(!modal) return; modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); }
        function closeModal(modal){ if(!modal) return; modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); }

        document.addEventListener('click', function(e){
          // Open
          var openBtn = e.target.closest && e.target.closest('.cr-open-modal');
          if (openBtn) {
            var sel = openBtn.getAttribute('data-target');
            var modal = sel ? document.querySelector(sel) : null;
            if (modal) { openModal(modal); }
            e.preventDefault();
            return;
          }
          // Close by button
          var closeBtn = e.target.closest && e.target.closest('.cr-modal-close');
          if (closeBtn) {
            var modalEl = closeBtn.closest('.cr-modal');
            closeModal(modalEl);
            e.preventDefault();
            return;
          }
          // Close by clicking backdrop (outside dialog)
          if (e.target.classList && e.target.classList.contains('cr-modal')) {
            closeModal(e.target);
            e.preventDefault();
            return;
          }
        });
        // Close on ESC
        document.addEventListener('keydown', function(e){
          if (e.key === 'Escape') {
            var open = document.querySelector('.cr-modal.is-open');
            if (open) { closeModal(open); }
          }
        });
      })();
    </script>
    {/literal}
  </div>
{/if}
