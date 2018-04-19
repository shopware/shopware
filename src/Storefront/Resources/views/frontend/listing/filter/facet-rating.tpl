{namespace name="frontend/listing/listing_actions"}

{block name="frontend_listing_filter_facet_rating"}
    <div class="filter-panel filter--rating facet--{$facet->getFacetName()|escape:'htmlall'}"
         data-filter-type="rating"
         data-facet-name="{$facet->getFacetName()}"
         data-field-name="{$facet->getFieldName()|escape:'htmlall'}">

        {block name="frontend_listing_filter_facet_rating_flyout"}
            <div class="filter-panel--flyout">

                {block name="frontend_listing_filter_facet_rating_title"}
                    <label class="filter-panel--title">
                        {$facet->getLabel()|escape}
                    </label>
                {/block}

                {block name="frontend_listing_filter_facet_rating_icon"}
                    <span class="filter-panel--icon"></span>
                {/block}

                {block name="frontend_listing_filter_facet_rating_content"}
                    <div class="filter-panel--content">

                        {block name="frontend_listing_filter_facet_rating_stars"}
                            <div class="filter-panel--star-rating">
                                {foreach $facet->getValues() as $value}
                                    {block name="frontend_listing_filter_facet_rating_container"}
                                        <label for="rating_{$value->getId()}" class="rating-star--outer-container{if $value->isActive()} is--active{/if}">
                                            <input class="is--hidden" type="checkbox" name="rating" id="rating_{$value->getId()}" value="{$value->getId()}" {if $value->isActive()}checked="checked" {/if}/>

                                            {for $i = 1 to $value->getId()}
                                                <i class="icon--star"></i>
                                            {/for}

                                            {for $i = $value->getId() + 1 to 5}
                                                <i class="icon--star-empty"></i>
                                            {/for}
                                            <span class="rating-star--suffix">{s name="RatingStarSuffix"}& more{/s}</span>
                                        </label>
                                    {/block}
                                {/foreach}
                            </div>
                        {/block}
                    </div>
                {/block}
            </div>
        {/block}
    </div>
{/block}
