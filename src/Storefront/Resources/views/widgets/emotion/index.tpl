{if $sEmotions|@count > 0}
    {foreach $sEmotions as $emotion}

        {block name="widgets/emotion/index/container"}

            {* Config block for overriding configuration variables of the shopping world *}
            {block name="widgets/emotion/index/config"}
                {$cellHeight = $emotion.cellHeight}
                {$cellWidth = 100 / $emotion.cols}
                {$cellSpacing = $emotion.cellSpacing}
                {$baseWidth = 1160}

                {$emotionMode = $emotion.mode}
                {$emotionGridMode = $emotion.mode}
                {$emotionFullscreen = $emotion.fullscreen}
                {$emotionCols = $emotion.cols}

                {$breakpoints = [ 's' => '30em', 'm' => '48em', 'l' => '64em', 'xl' => '78.75em' ]}

                {if $Controller == 'listing' && $theme.displaySidebar}
                    {$baseWidth = 900}
                {/if}

                {$emotionRows = []}
                {$emotionRows['base'] = 0}
            {/block}

            {block name="widgets/emotion/index/emotion"}
                <section class="emotion--container emotion--column-{$emotionCols} emotion--mode-{$emotionMode} emotion--{$emotion@index}"
                         data-emotion="true"
                         data-gridMode="{$emotionGridMode}"
                         data-fullscreen="{if $emotionFullscreen}true{else}false{/if}"
                         data-columns="{$emotionCols}"
                         data-cellSpacing="{$cellSpacing}"
                         data-cellHeight="{$cellHeight}"
                         data-baseWidth="{$baseWidth}"
                         {block name="widgets/emotion/index/attributes"}{/block}>

                    {if $emotion.elements.0}
                        {foreach $emotion.elements as $element}
                            {block name="widgets/emotion/index/element"}

                                {* Config block for overriding configuration variables of the emotion element *}
                                {block name="widgets/emotion/index/element/config"}
                                    {$template = $element.component.template}
                                    {$Data = $element.data}

                                    {$itemCls = "emotion--element"}

                                    {$itemCols = ($element.endCol - $element.startCol) + 1}
                                    {$itemRows = ($element.endRow - $element.startRow) + 1}
                                    {$itemHeight = $itemRows * ($cellHeight + $cellSpacing)}
                                    {$itemTop = ($element.startRow - 1) * ($cellHeight + $cellSpacing)}
                                    {$itemLeft = $cellWidth * ($element.startCol - 1)}
                                    {$itemStyle = "padding-left: {$cellSpacing / 16}rem; padding-bottom: {$cellSpacing / 16}rem;"}

                                    {$itemCls = "{$itemCls} col-{$itemCols}"}
                                    {$itemCls = "{$itemCls} row-{$itemRows}"}
                                    {$itemCls = "{$itemCls} start-col-{$element.startCol}"}
                                    {$itemCls = "{$itemCls} start-row-{$element.startRow}"}

                                    {foreach $element.viewports as $viewport}
                                        {$viewportCols = ($viewport.endCol - $viewport.startCol) + 1}
                                        {$viewportRows = ($viewport.endRow - $viewport.startRow) + 1}

                                        {$viewportColCls = "col-{$viewport.alias}-{$viewportCols}"}
                                        {$viewportColCls = "{$viewportColCls} start-col-{$viewport.alias}-{$viewport.startCol}"}

                                        {$viewportRowCls = "row-{$viewport.alias}-{$viewportRows}"}
                                        {$viewportRowCls = "{$viewportRowCls} start-row-{$viewport.alias}-{$viewport.startRow}"}

                                        {$itemCls = "{$itemCls} {$viewportColCls}"}
                                        {$itemCls = "{$itemCls} {$viewportRowCls}"}

                                        {if !$viewport.visible}
                                            {$itemCls = "{$itemCls} is--hidden-{$viewport.alias}"}
                                        {/if}

                                        {if !$emotionRows[$viewport.alias]}
                                            {$emotionRows[$viewport.alias] = 0}
                                        {/if}

                                        {if $emotionRows[$viewport.alias] < $viewport.endRow}
                                            {$emotionRows[$viewport.alias] = $viewport.endRow}
                                        {/if}
                                    {/foreach}

                                    {if $element.cssClass}
                                        {$itemCls = "{$itemCls} {$element.cssClass}"}
                                    {/if}

                                    {if $emotionRows['base'] < $element.endRow}
                                        {$emotionRows['base'] = $element.endRow}
                                    {/if}
                                {/block}

                                {strip}
                                <div class="{$itemCls}" style="{$itemStyle}">

                                    {block name="widgets/emotion/index/inner-element"}

                                        {if $template == 'component_article'}
                                            {include file="widgets/emotion/components/component_article.tpl"}

                                        {elseif $template == 'component_article_slider'}
                                            {include file="widgets/emotion/components/component_article_slider.tpl"}

                                        {elseif $template == 'component_banner'}
                                            {include file="widgets/emotion/components/component_banner.tpl"}

                                        {elseif $template == 'component_banner_slider'}
                                            {include file="widgets/emotion/components/component_banner_slider.tpl"}

                                        {elseif $template == 'component_blog'}
                                            {include file="widgets/emotion/components/component_blog.tpl"}

                                        {elseif $template == 'component_category_teaser'}
                                            {include file="widgets/emotion/components/component_category_teaser.tpl"}

                                        {elseif $template == 'component_html'}
                                            {include file="widgets/emotion/components/component_html.tpl"}

                                        {elseif $template == 'component_iframe'}
                                            {include file="widgets/emotion/components/component_iframe.tpl"}

                                        {elseif $template == 'component_manufacturer_slider'}
                                            {include file="widgets/emotion/components/component_manufacturer_slider.tpl"}

                                        {elseif $template == 'component_youtube'}
                                            {include file="widgets/emotion/components/component_youtube.tpl"}

                                        {elseif "widgets/emotion/components/{$template}.tpl"|template_exists}
                                            {include file="widgets/emotion/components/{$template}.tpl"}
                                        {/if}
                                    {/block}
                                </div>
                                {/strip}
                            {/block}
                        {/foreach}

                        {block name="widgets/emotion/index/sizer"}
                            {foreach $emotionRows as $alias => $rows}
                                {if $alias === 'base'}
                                    {continue}
                                {/if}

                                {$containerHeight = $rows * ($cellHeight + $cellSpacing)}
                                <div class="emotion--sizer-{$alias} col--1" data-rows="{$rows}" style="height: {$containerHeight}px;"></div>
                            {/foreach}

                            {$containerHeight = $emotionRows['base'] * ($cellHeight + $cellSpacing)}
                            <div class="emotion--sizer col-1" data-rows="{$emotionRows['base']}" style="height: {$containerHeight}px;"></div>
                        {/block}
                    {/if}
                </section>
            {/block}
        {/block}
    {/foreach}
{/if}