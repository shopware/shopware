{namespace name="frontend/listing/listing_actions"}

{block name="frontend_listing_filter_facet_value_tree"}
    {$type = 'value-tree'}
    {$listingMode = {config name="listingMode"}}
    {if $listingMode == 'filter_ajax_reload'}
        {$type = 'value-tree-single'}
    {/if}

    <div class="filter-panel filter--value-tree facet--{$facet->getFacetName()|escape:'htmlall'}"
         data-filter-type="{$type}"
         data-facet-name="{$facet->getFacetName()}"
         data-field-name="{$facet->getFieldName()|escape:'htmlall'}">

        {block name="frontend_listing_filter_facet_value_tree_flyout"}
            <div class="filter-panel--flyout">

                {block name="frontend_listing_filter_facet_value_tree_title"}
                    <label class="filter-panel--title">
                        {$facet->getLabel()|escape}
                    </label>
                {/block}

                {block name="frontend_listing_filter_facet_value_tree_icon"}
                    <span class="filter-panel--icon"></span>
                {/block}

                {block name="frontend_listing_filter_facet_value_tree_content"}
                    <div class="filter-panel--content">

                        {block name="frontend_listing_filter_facet_value_tree_list"}

                            {function name=valueTree level=0}
                                <ul class="filter-panel--option-list{if $level > 0} sub-level level--{$level}{/if}">
                                    {foreach $options as $option}

                                        {block name="frontend_listing_filter_facet_value_tree_option"}
                                            <li class="filter-panel--option value-tree--option">

                                                {block name="frontend_listing_filter_facet_value_tree_option_container"}
                                                    <div class="option--container value-tree--container">
                                                        {$disabled = !$option->isActive() && $facet->isActive()}

                                                        {block name="frontend_listing_filter_facet_value_tree_input"}
                                                            <span class="filter-panel--input{if $disabled} is--disabled{/if}">
                                                                <input type="checkbox"
                                                                       data-parent-id="{$parent}"
                                                                       id="__{$facet->getFieldName()|escape:'htmlall'}__{$option->getId()|escape:'htmlall'}"
                                                                       name="__{$facet->getFieldName()|escape:'htmlall'}__{$option->getId()|escape:'htmlall'}"
                                                                       value="{$option->getId()|escape:'htmlall'}"
                                                                       {if $option->isActive()}checked="checked"{elseif $disabled}disabled="disabled"{/if}/>

                                                                <span class="input--state">&nbsp;</span>
                                                            </span>
                                                        {/block}

                                                        {block name="frontend_listing_filter_facet_value_tree_label"}
                                                            <label class="filter-panel--label value-tree--label"
                                                                   for="__{$facet->getFieldName()|escape:'htmlall'}__{$option->getId()|escape:'htmlall'}">
                                                                {$option->getLabel()|escape}
                                                            </label>
                                                        {/block}
                                                    </div>
                                                {/block}

                                                {if !empty($option->getValues())}
                                                    {valueTree options=$option->getValues() level=$level+1 parent=$option->getId()}
                                                {/if}
                                            </li>
                                        {/block}
                                    {/foreach}
                                </ul>
                            {/function}

                            {valueTree options=$facet->getValues() parent=null}
                        {/block}
                    </div>
                {/block}
            </div>
        {/block}
    </div>
{/block}
