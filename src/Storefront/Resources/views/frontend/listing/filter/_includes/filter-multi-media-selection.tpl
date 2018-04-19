{extends file="parent:frontend/listing/filter/_includes/filter-multi-selection.tpl"}

{block name="frontend_listing_filter_facet_multi_selection_input"}
    {$name = "__{$facet->getFieldName()|escape:'htmlall'}__{$option->getId()|escape:'htmlall'}"}
    {if $singleSelection}
        {$name = {$facet->getFieldName()|escape:'htmlall'} }
    {/if}

    <input type="{$inputType}"
       id="__{$facet->getFieldName()|escape:'htmlall'}__{$option->getId()|escape:'htmlall'}"
       name="{$name}"
       title="{$option->getLabel()|escape:'htmlall'}"
       value="{$option->getId()|escape:'htmlall'}"
       {if $option->isActive()}checked="checked" {/if}/>
{/block}