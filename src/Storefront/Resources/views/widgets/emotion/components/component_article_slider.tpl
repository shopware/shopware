{* Slider panel *}
{block name="widget_emotion_component_product_slider"}
    <div class="emotion--product-slider panel{if !$Data.no_border} has--border{/if}">

        {* Title *}
        {block name="widget_emotion_component_product_slider_title"}
            {if $Data.article_slider_title}
                <div class="panel--title is--underline product-slider--title">
                    {$Data.article_slider_title}
                </div>
            {/if}
        {/block}

        {* Slider content based on the configuration *}
        {block name="widget_emotion_component_product_slider_content"}
            {$sliderMode = 'ajax'}
            {if $Data.article_slider_type == 'selected_article' || $Data.values|@count}
                {$articles = $Data.values}
                {$sliderMode = ''}
            {/if}

            {$sliderAjaxUrl = {url module=widgets controller=emotion action=emotionArticleSlider sort=$Data.article_slider_type}}
            {if $Data.article_slider_type === 'product_stream'}
                {$sliderAjaxUrl = {url module=widgets controller=emotion action=productStreamArticleSlider streamId=$Data.article_slider_stream}}
            {/if}

            {include file="frontend/_includes/product_slider.tpl"
                    articles=$articles
                    productSliderCls="product-slider--content"
                    sliderMode=$sliderMode
                    sliderAjaxCtrlUrl=$sliderAjaxUrl
                    sliderAjaxCategoryID=$Data.article_slider_category
                    sliderAjaxMaxShow=$Data.article_slider_max_number
                    sliderArrowControls={($Data.article_slider_arrows != 1) ? 'false' : ''}
                    sliderAnimationSpeed=$Data.article_slider_scrollspeed
                    sliderAutoSlideSpeed={($Data.article_slider_rotatespeed) ? ($Data.article_slider_rotatespeed / 1000) : ''}
                    sliderAutoSlide={($Data.article_slider_rotation) ? 'true' : ''}
                    productBoxLayout="emotion"
                    fixedImageSize="true"}
        {/block}
    </div>
{/block}
