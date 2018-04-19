{namespace name="frontend/listing/listing_actions"}

{block name="frontend_listing_actions_filter_submit_button"}
    <div class="filter--actions{if $clsSuffix} {$clsSuffix}{/if}">
        <button type="submit"
                class="btn is--primary filter--btn-apply is--large is--icon-right"
                disabled="disabled">
            <span class="filter--count"></span>
            {s name="ListingFilterApplyButton"}{/s}
            <i class="icon--cycle"></i>
        </button>
    </div>
{/block}