{namespace name="frontend/listing/listing_actions"}

{block name="frontend_listing_filter_facet_boolean"}
    <div class="filter-panel filter--value facet--{$facet->getFacetName()|escape:'htmlall'}"
         data-filter-type="value"
         data-facet-name="{$facet->getFacetName()}"
         data-field-name="{$facet->getFieldName()|escape:'htmlall'}">

        {block name="frontend_listing_filter_facet_boolean_flyout"}
            <div class="filter-panel--flyout">

                {block name="frontend_listing_filter_facet_boolean_title"}
                    <label class="filter-panel--title" for="{$facet->getFieldName()|escape:'htmlall'}">
                        {$facet->getLabel()|escape}
                    </label>
                {/block}

                {block name="frontend_listing_filter_facet_boolean_checkbox"}
                    <span class="filter-panel--input">
                        <input type="checkbox"
                               id="{$facet->getFieldName()|escape:'htmlall'}"
                               name="{$facet->getFieldName()|escape:'htmlall'}"
                               value="1"
                               {if $facet->isActive()}checked="checked" {/if}/>

                        <span class="input--state">&nbsp;</span>
                    </span>
                {/block}
            </div>
        {/block}
    </div>
{/block}
