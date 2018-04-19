{block name='frontend_detail_product_quick_view'}
    <div class="product--quick-view">
        {block name='frontend_detail_product_quick_view_inner'}

            {block name='frontend_detail_product_quick_view_image_link'}
                <a class="quick-view--image-link" href="{$sArticle.linkDetails}" title="{if $sArticle.image.res.description}{$sArticle.image.res.description|escape}{else}{$sArticle.articlename|escape}{/if}">
                    {block name='frontend_detail_product_quick_view_image'}

                        {$alt = $sArticle.articlename|escape}

                        {if $sArticle.image.description}
                            {$alt = $sArticle.image.description|escape}
                        {/if}

                        <span class="quick-view--image">
                            {block name='frontend_detail_product_quick_view_image_inner'}
                                {if $sArticle.image.thumbnails}
                                    <img srcset="{$sArticle.image.thumbnails[1].sourceSet}" alt="{$alt}" />
                                {else}
                                    {block name='product_quick_view_image_fallback'}
                                        <img src="{link file='frontend/_public/src/img/no-picture.jpg'}" alt="{$alt}">
                                    {/block}
                                {/if}
                            {/block}
                        </span>
                    {/block}
                </a>
            {/block}

            {block name='frontend_detail_product_quick_view_header'}
                <div class="quick-view--header">
                    {block name='frontend_detail_product_quick_view_header_inner'}
                        {block name='frontend_detail_product_quick_view_title'}
                            <a href="{$sArticle.linkDetails}" class="quick-view--title" title="{$sArticle.articleName|escape}">
                                {block name='frontend_detail_product_quick_view_title_inner'}
                                    {$sArticle.articleName|escape}
                                {/block}
                            </a>
                        {/block}

                        {block name='frontend_detail_product_quick_view_supplier'}
                            <div class="quick-view--supplier">
                                {block name='frontend_detail_product_quick_view_supplier_inner'}
                                    {$sArticle.supplierName|escape}
                                {/block}
                            </div>
                        {/block}
                    {/block}
                </div>
            {/block}

            {block name='frontend_detail_product_quick_view_description_title'}
                <div class="quick-view--description-title">
                    {block name='frontend_detail_product_quick_view_description_title_inner'}
                        {s name="DetailDescriptionHeader" namespace="frontend/detail/description"}{/s}
                    {/block}
                </div>
            {/block}

            {block name='frontend_detail_product_quick_view_description'}
                <div class="quick-view--description">
                    {block name='frontend_detail_product_quick_view_description_inner'}
                        {$sArticle.description_long}
                    {/block}
                </div>
            {/block}
        {/block}
    </div>
{/block}
