<div class="table--header block-group">
    {block name='frontend_checkout_cart_header_field_labels'}

        {* Product name and information *}
        {block name='frontend_checkout_cart_header_name'}
            <div class="panel--th column--product block">
                {s name="CartColumnName"}{/s}
            </div>
        {/block}

        {* Product quantity *}
        {block name='frontend_checkout_cart_header_quantity'}
            <div class="panel--th column--quantity block is--align-right">
                {s name="CartColumnQuantity"}{/s}
            </div>
        {/block}

        {* Unit price *}
        {block name='frontend_checkout_cart_header_price'}
            <div class="panel--th column--unit-price block is--align-right">
                {s name='CartColumnPrice'}{/s}
            </div>
        {/block}

        {* Product tax rate *}
        {block name='frontend_checkout_cart_header_tax'}{/block}

        {* Accumulated product price *}
        {block name='frontend_checkout_cart_header_total'}
            <div class="panel--th column--total-price block is--align-right">
                {s name="CartColumnTotal"}{/s}
            </div>
        {/block}

        {* Action column *}
        {block name='frontend_checkout_cart_header_actions'}
            <div class="panel--th column--actions block">
                &nbsp;
            </div>
        {/block}
    {/block}
</div>
