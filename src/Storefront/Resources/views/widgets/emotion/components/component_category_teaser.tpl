{block name="widget_emotion_component_category_teaser_panel"}
    {if $Data}
        <div class="emotion--category-teaser">

            {* Category teaser image *}
            {block name="widget_emotion_component_category_teaser_image"}
                {if isset($Data.media)}
                    {$media = $Data.media}
                {else}
                    {$media = $Data.image}
                {/if}

                {$images = $media.thumbnails}
            {/block}

            {* Category teaser lnk *}
            {block name="widget_emotion_component_category_teaser_link"}

                {if $Data.blog_category}
                    {$url = "{url controller=blog action=index sCategory=$Data.category_selection}"}
                {else}
                    {$url = "{url controller=cat action=index sCategory=$Data.category_selection}"}
                {/if}

                {strip}
                <style type="text/css">

                    #teaser--{$Data.objectId} {
                        background-image: url('{$images[0].source}');
                    }

                    {if isset($images[0].retinaSource)}
                    @media screen and (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
                        #teaser--{$Data.objectId} {
                            background-image: url('{$images[0].retinaSource}');
                        }
                    }
                    {/if}

                    @media screen and (min-width: 48em) {
                        #teaser--{$Data.objectId} {
                            background-image: url('{$images[1].source}');
                        }
                    }

                    {if isset($images[1].retinaSource)}
                    @media screen and (min-width: 48em) and (-webkit-min-device-pixel-ratio: 2),
                           screen and (min-width: 48em) and (min-resolution: 192dpi) {
                        #teaser--{$Data.objectId} {
                            background-image: url('{$images[1].retinaSource}');
                        }
                    }
                    {/if}

                    @media screen and (min-width: 78.75em) {
                        .is--fullscreen #teaser--{$Data.objectId} {
                            background-image: url('{$images[2].source}');
                        }
                    }

                    {if isset($images[2].retinaSource)}
                    @media screen and (min-width: 78.75em) and (-webkit-min-device-pixel-ratio: 2),
                           screen and (min-width: 78.75em) and (min-resolution: 192dpi) {
                        .is--fullscreen #teaser--{$Data.objectId} {
                            background-image: url('{$images[2].retinaSource}');
                        }
                    }
                    {/if}
                </style>
                {/strip}

                <a href="{$url}"
                   title="{$Data.categoryName|strip_tags|escape}"
                   class="category-teaser--link"
                   id="teaser--{$Data.objectId}">

                    {* Category teaser title *}
                    {block name="widget_emotion_component_category_teaser_title"}
                        <span class="category-teaser--title">
                            {$Data.categoryName}
                        </span>
                    {/block}
                </a>
            {/block}
        </div>
    {/if}
{/block}