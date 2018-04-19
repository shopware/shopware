{extends file='frontend/index/index.tpl'}

{block name='frontend_index_content'}
    <div class="detail-error content listing--content">

        <h1 class="detail-error--headline">{s name='DetailRelatedHeader'}{/s}</h1>

        {if $sRelatedArticles}
            <h2 class="detail-error--articles">{s name='DetailRelatedHeaderSimilarArticles'}{/s}</h2>

            <div class="detail-error--listing listing">
                {foreach from=$sRelatedArticles item=sArticleSub key=key name="counter"}
                    {include file="frontend/listing/box_article.tpl" sArticle=$sArticleSub}
                {/foreach}
            </div>
        {/if}
    </div>
{/block}