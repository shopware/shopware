{* Sorting filter which will be included in the "listing/listing_actions.tpl" *}
{namespace name="frontend/listing/listing_actions"}

{$hideSortings = $sCategoryContent.hide_sortings || $sortings|count == 0}

<form class="action--sort action--content block{if $hideSortings} is--hidden{/if}" method="get" data-action-form="true">

    {* Necessary to reset the page to the first one *}
    <input type="hidden" name="{$shortParameters.sPage}" value="1">

    {* Sorting label *}
    {block name='frontend_listing_actions_sort_label'}
        <label class="sort--label action--label">{s name='ListingLabelSort'}{/s}</label>
    {/block}

    {* Sorting field *}
    {block name='frontend_listing_actions_sort_field'}
        {$listingMode = {config name=listingMode}}

        <div class="sort--select select-field">
            <select name="{$shortParameters.sSort}"
                    class="sort--field action--field"
                    data-auto-submit="true"
                    {if $listingMode != 'full_page_reload'}data-loadingindicator="false"{/if}>

                {foreach $sortings as $sorting}
                    {block name="frontend_listing_actions_sort_field_release"}
                        <option value="{$sorting->getId()}"{if $sSort eq $sorting->getId()} selected="selected"{/if}>{$sorting->getLabel()}</option>
                    {/block}
                {/foreach}

                {block name='frontend_listing_actions_sort_values'}{/block}
            </select>
        </div>
    {/block}
</form>
