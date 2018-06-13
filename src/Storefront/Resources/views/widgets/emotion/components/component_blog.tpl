{block name="widget_emotion_component_blog"}
    <div class="emotion--blog">
        {if $Data}
            {block name="widget_emotion_component_blog_container"}
                <div class="blog--container block-group">
                    {foreach $Data.entries as $entry}
                        {$link = $entry.link}
                        {if !$link}
                            {$link = {url controller=blog action=detail sCategory=$entry.categoryId blogArticle=$entry.id}}
                        {/if}

                        {block name="widget_emotion_component_blog_entry"}
                            <div class="blog--entry blog--entry-{$entry@index} block"
                                 style="width:{{"100" / $Data.entries|count}|round:2}%">

                                {block name="widget_emotion_component_blog_entry_image"}
                                    {if $entry.media.thumbnails}

                                        {$images = $entry.media.thumbnails}

                                        {strip}
                                            <style type="text/css">

                                                #teaser--{$Data.objectId}-{$entry@index} {
                                                    background-image: url('{$images[0].source}');
                                                }

                                                {if isset($images[0].retinaSource)}
                                                @media screen and (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
                                                    #teaser--{$Data.objectId}-{$entry@index} {
                                                        background-image: url('{$images[0].retinaSource}');
                                                    }
                                                }
                                                {/if}

                                                @media screen and (min-width: 48em) {
                                                    #teaser--{$Data.objectId}-{$entry@index} {
                                                        background-image: url('{$images[1].source}');
                                                    }
                                                }

                                                {if isset($images[1].retinaSource)}
                                                @media screen and (min-width: 48em) and (-webkit-min-device-pixel-ratio: 2),
                                                screen and (min-width: 48em) and (min-resolution: 192dpi) {
                                                    #teaser--{$Data.objectId}-{$entry@index} {
                                                        background-image: url('{$images[1].retinaSource}');
                                                    }
                                                }
                                                {/if}

                                                @media screen and (min-width: 78.75em) {
                                                    .is--fullscreen #teaser--{$Data.objectId}-{$entry@index} {
                                                        background-image: url('{$images[2].source}');
                                                    }
                                                }

                                                {if isset($images[2].retinaSource)}
                                                @media screen and (min-width: 78.75em) and (-webkit-min-device-pixel-ratio: 2),
                                                screen and (min-width: 78.75em) and (min-resolution: 192dpi) {
                                                    .is--fullscreen #teaser--{$Data.objectId}-{$entry@index} {
                                                        background-image: url('{$images[2].retinaSource}');
                                                    }
                                                }
                                                {/if}
                                            </style>
                                        {/strip}

                                        <a class="blog--image"
                                           id="teaser--{$Data.objectId}-{$entry@index}"
                                           href="{$link}"
                                           title="{$entry.title|escape}">&nbsp;</a>
                                    {else}
                                        <a class="blog--image"
                                           href="{$link}"
                                           title="{$entry.title|escape}">
                                            {s name="EmotionBlogPreviewNopic"}{/s}
                                        </a>
                                    {/if}
                                {/block}

                                {block name="widget_emotion_component_blog_entry_title"}
                                    <a class="blog--title"
                                       href="{$link}"
                                       title="{$entry.title|escape}">
                                       {$entry.title|truncate:40}
                                    </a>
                                {/block}

                                {block name="widget_emotion_component_blog_entry_description"}
                                    <div class="blog--description">
                                        {if $entry.shortDescription}
                                            {$entry.shortDescription|truncate:135}
                                        {else}
                                            {$entry.description|strip_tags|truncate:135}
                                        {/if}
                                    </div>
                                {/block}
                            </div>
                        {/block}
                    {/foreach}
                </div>
            {/block}
        {/if}
    </div>
{/block}