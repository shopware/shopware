{extends file='parent:frontend/home/index.tpl'}

{block name='frontend_index_header_canonical'}
    <link rel="canonical" href="{url controller=campaign emotionId=$landingPage.id}" />
{/block}

{block name='frontend_index_header_title'}{strip}
    {if $seo_title}
        {$seo_title|escapeHtml} | {{config name=sShopname}|escapeHtml}
    {else}
        {$smarty.block.parent}
    {/if}
{/strip}{/block}

{* Keywords *}
{block name="frontend_index_header_meta_keywords"}{if $seo_keywords}{$seo_keywords|escapeHtml}{/if}{/block}

{* Description *}
{block name="frontend_index_header_meta_description"}{if $seo_description}{$seo_description|escapeHtml}{/if}{/block}

{* Promotion *}
{block name='frontend_home_index_promotions'}
    {foreach $landingPage.emotions as $emotion}

        <div class="content--emotions">
            <div class="emotion--wrapper"
                 data-controllerUrl="{url module=widgets controller=emotion action=index emotionId=$emotion.id controllerName=$Controller}"
                 data-availableDevices="{$emotion.devices}">
            </div>
        </div>
    {/foreach}
{/block}
