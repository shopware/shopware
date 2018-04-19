{* Product-Streams slider *}
{block name='frontend_detail_index_streams_slider'}
    <div class="product-streams--content">
        {include file="frontend/_includes/product_slider.tpl"
                 sliderMode="ajax"
                 sliderInitOnEvent="onShowContent-productStreamSliderId-{$relatedProductStream.id}"
                 sliderAjaxCtrlUrl="{url module=widgets controller=emotion action=productStreamArticleSlider streamId=$relatedProductStream.id productBoxLayout="slider"}"
                 sliderAjaxMaxShow="40"}
    </div>
{/block}