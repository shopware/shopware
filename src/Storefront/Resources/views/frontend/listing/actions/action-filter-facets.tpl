{**
 * Iteration for the different filter facets.
 * The file is called recursive for deeper structured facet groups.
 *}
{foreach $facets as $facet}
    {if $facet->getTemplate() !== null}
        {include file=$facet->getTemplate() facet=$facet}
    {/if}
{/foreach}