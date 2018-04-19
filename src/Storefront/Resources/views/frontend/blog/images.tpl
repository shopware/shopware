{* Article picture *}
{if $sArticle.preview.thumbnails[0]}
    <div class="blog--detail-image-container block">

        {* Main Image *}
        {block name='frontend_blog_images_main_image'}

            {$alt = $sArticle.title|escape}

            {if $sArticle.preview.description}
                {$alt = $sArticle.preview.description|escape}
            {/if}

            <div class="blog--detail-images block">
                <a href="{$sArticle.preview.source}"
                   data-lightbox="true"
                   title="{$alt}"
                   class="link--blog-image">

                    <img srcset="{$sArticle.preview.thumbnails[1].sourceSet}"
                         class="blog--image panel has--border is--rounded"
                         alt="{$alt}"
                         title="{$alt|truncate:160}" />
                </a>
            </div>
        {/block}

        {* Thumbnails *}
        {if $sArticle.media}
            {block name='frontend_blog_images_thumbnails'}
                <div class="blog--detail-thumbnails block">
                    {foreach $sArticle.media as $sArticleMedia}

                        {$alt = $sArticle.title|escape}

                        {if $sArticleMedia.description}
                            {$alt = $sArticleMedia.description}
                        {/if}

                        {if !$sArticleMedia.preview}
                            <a href="{$sArticleMedia.source}"
                               data-lightbox="true"
                               class="blog--thumbnail panel has--border is--rounded block"
                               title="{s name="BlogThumbnailText" namespace="frontend/blog/detail"}{/s}: {$alt}">

                               <img srcset="{$sArticleMedia.thumbnails[0].sourceSet}"
                                    class="blog--thumbnail-image"
                                    alt="{s name="BlogThumbnailText" namespace="frontend/blog/detail"}{/s}: {$alt}"
                                    title="{s name="BlogThumbnailText" namespace="frontend/blog/detail"}{/s}: {$alt|truncate:160}" />
                            </a>
                        {/if}
                    {/foreach}
                </div>
            {/block}
        {/if}
    </div>
{/if}