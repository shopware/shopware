
{foreach from=$sCategoryTree item=categoryTree}
    {if $categoryTree.hideOnSitemap}
        {continue}
    {/if}
    {if $depth==1}
    {elseif $depth==2}
    {/if}
    {if $categoryTree.sub}
    <ul class="sitemap--navigation-entry list--unstyled">
            <li>
        <a href="{$categoryTree.link}" title="{$categoryTree.name|escape}" class="sitemap--navigation-link is--active">{$categoryTree.name}</a>
        {if $depth>=1}<ul class="sitemap--navigation-entry-inner list--unstyled">{/if}{include file="frontend/sitemap/recurse.tpl" sCategoryTree=$categoryTree.sub depth=$depth+1}{if $depth>=1}</ul>{/if}
        </li>
    </ul>
    {else}
        {if $depth==1}<ul class="sitemap--navigation-entry list--unstyled">{/if}<li>
        <a href="{$categoryTree.link}" title="{$categoryTree.name|escape}" class="sitemap--navigation-link">{$categoryTree.name}</a></li>{if $depth==1}</ul>{/if}
    {/if}
{/foreach}
