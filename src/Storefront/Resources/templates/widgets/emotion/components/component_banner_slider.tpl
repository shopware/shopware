{block name="frontend_widgets_banner_slider"}
    <div class="emotion--banner-slider image-slider"
         data-image-slider="true"
         data-thumbnails="false"
         data-lightbox="false"
         data-loopSlides="true"
         data-animationSpeed="{$Data.banner_slider_scrollspeed}"
         data-arrowControls="{if $Data.banner_slider_arrows}true{else}false{/if}"
         data-autoSlideInterval="{$Data.banner_slider_rotatespeed}"
         data-autoSlide="{if $Data.banner_slider_rotation}true{else}false{/if}"
         data-imageSelector=".image-slider--item">

        {if $Data.banner_slider_title}
            <div class="banner-slider--title">{$Data.banner_slider_title}</div>
        {/if}

        {block name="frontend_widgets_banner_slider_container"}
            <div class="banner-slider--container image-slider--container">

                {block name="frontend_widgets_banner_slider_slide"}
                    <div class="banner-slider--slide image-slider--slide">

                        {foreach $Data.values as $banner}
                            {block name="frontend_widgets_banner_slider_item"}
                                <div class="banner-slider--item image-slider--item"
                                     data-coverImage="true"
                                     data-containerSelector=".banner-slider--banner"
                                     data-width="{$banner.fileInfo.width}"
                                     data-height="{$banner.fileInfo.height}">

                                    {block name="frontend_widgets_banner_slider_banner"}
                                        <div class="banner-slider--banner">

                                            {block name="frontend_widgets_banner_slider_banner_picture"}
                                                {if $banner.thumbnails}
                                                    {$baseSource = $banner.thumbnails[0].source}
                                                    {$srcSet = ''}
                                                    {$itemSize = ''}

                                                    {foreach $element.viewports as $viewport}
                                                        {$cols = ($viewport.endCol - $viewport.startCol) + 1}
                                                        {$elementSize = $cols * $cellWidth}
                                                        {$size = "{$elementSize}vw"}

                                                        {if $breakpoints[$viewport.alias]}

                                                            {if $viewport.alias === 'xl' && !$emotionFullscreen}
                                                                {$size = "calc({$elementSize / 100} * {$baseWidth}px)"}
                                                            {/if}

                                                            {$size = "(min-width: {$breakpoints[$viewport.alias]}) {$size}"}
                                                        {/if}

                                                        {$itemSize = "{$size}{if $itemSize}, {$itemSize}{/if}"}
                                                    {/foreach}

                                                    {foreach $banner.thumbnails as $image}
                                                        {$srcSet = "{if $srcSet}{$srcSet}, {/if}{$image.source} {$image.maxWidth}w"}

                                                        {if $image.retinaSource}
                                                            {$srcSet = "{if $srcSet}{$srcSet}, {/if}{$image.retinaSource} {$image.maxWidth * 2}w"}
                                                        {/if}
                                                    {/foreach}
                                                {else}
                                                    {$baseSource = $banner.source}
                                                {/if}

                                                <img src="{$baseSource}"
                                                     class="banner-slider--image"
                                                     {if $srcSet}sizes="{$itemSize}" srcset="{$srcSet}"{/if}
                                                     {if $banner.altText}alt="{$banner.altText|escape}" {/if}/>
                                            {/block}
                                        </div>
                                    {/block}

                                    {if $banner.link}
                                        {block name="frontend_widgets_banner_slider_link"}
                                            <a class="banner-slider--link" href="{$banner.link}" title="{$banner.title|escape}">
                                                {$banner.altText}
                                            </a>
                                        {/block}
                                    {/if}
                                </div>
                            {/block}
                        {/foreach}
                    </div>
                {/block}

                {block name="frontend_widgets_banner_slider_navigation"}
                    {if $Data.banner_slider_numbers}
                        <div class="image-slider--dots">
                            {foreach $Data.values as $link}
                                <div class="dot--link">{$link@iteration}</div>
                            {/foreach}
                        </div>
                    {/if}
                {/block}
            </div>
        {/block}
    </div>
{/block}