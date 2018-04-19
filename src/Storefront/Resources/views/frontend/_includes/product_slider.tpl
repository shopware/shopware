{* Config *}
{block name="frontend_common_product_slider_config"}
    {$productSliderCls = ($productSliderCls)?$productSliderCls:""}
    {$productBoxLayout = ($productBoxLayout)?$productBoxLayout:"slider"}
    {$sliderMode = ($sliderMode)?$sliderMode:""}
    {$sliderOrientation = ($sliderOrientation)?$sliderOrientation:""}
    {$sliderItemMinWidth = ($sliderItemMinWidth)?$sliderItemMinWidth:""}
    {$sliderItemMinHeight = ($sliderItemMinHeight)?$sliderItemMinHeight:""}
    {$sliderItemsPerSlide = ($sliderItemsPerSlide)?$sliderItemsPerSlide:""}
    {$sliderItemsPerPage = ($sliderItemsPerPage)?$sliderItemsPerPage:""}
    {$sliderAutoSlide = ($sliderAutoSlide)?$sliderAutoSlide:""}
    {$sliderAutoSlideDirection = ($sliderAutoSlideDirection)?$sliderAutoSlideDirection:""}
    {$sliderAutoSlideSpeed = ($sliderAutoSlideSpeed)?$sliderAutoSlideSpeed:""}
    {$sliderAutoScroll = ($sliderAutoScroll)?$sliderAutoScroll:""}
    {$sliderAutoScrollDirection = ($sliderAutoScrollDirection)?$sliderAutoScrollDirection:""}
    {$sliderAutoScrollSpeed = ($sliderAutoScrollSpeed)?$sliderAutoScrollSpeed:""}
    {$sliderScrollDistance = ($sliderScrollDistance)?$sliderScrollDistance:""}
    {$sliderAnimationSpeed = ($sliderAnimationSpeed)?$sliderAnimationSpeed:""}
    {$sliderArrowControls = ($sliderArrowControls)?$sliderArrowControls:""}
    {$sliderArrowAction = ($sliderArrowAction)?$sliderArrowAction:""}
    {$sliderWrapperCls = ($sliderWrapperCls)?$sliderWrapperCls:""}
    {$sliderHorizontalCls = ($sliderHorizontalCls)?$sliderHorizontalCls:""}
    {$sliderVerticalCls = ($sliderVerticalCls)?$sliderVerticalCls:""}
    {$sliderArrowCls = ($sliderArrowCls)?$sliderArrowCls:""}
    {$sliderPrevArrowCls = ($sliderPrevArrowCls)?$sliderPrevArrowCls:""}
    {$sliderNextArrowCls = ($sliderNextArrowCls)?$sliderNextArrowCls:""}
    {$sliderAjaxCtrlUrl = ($sliderAjaxCtrlUrl)?$sliderAjaxCtrlUrl:""}
    {$sliderAjaxCategoryID = ($sliderAjaxCategoryID)?$sliderAjaxCategoryID:""}
    {$sliderAjaxMaxShow = ($sliderAjaxMaxShow)?$sliderAjaxMaxShow:""}
    {$sliderAjaxShowLoadingIndicator = ($sliderAjaxShowLoadingIndicator)?$sliderAjaxShowLoadingIndicator:""}
    {$sliderInitOnEvent = ($sliderInitOnEvent)?$sliderInitOnEvent:""}
    {$fixedImageSize = ($fixedImageSize)?$fixedImageSize:""}
{/block}

{* Template *}
{block name="frontend_common_product_slider_component"}
    <div class="product-slider {$productSliderCls}"
         {if $sliderMode}data-mode="{$sliderMode}"{/if}
         {if $sliderOrientation}data-orientation="{$sliderOrientation}"{/if}
         {if $sliderItemMinWidth}data-itemMinWidth="{$sliderItemMinWidth}"{/if}
         {if $sliderItemMinHeight}data-itemMinHeight="{$sliderItemMinHeight}"{/if}
         {if $sliderItemsPerSlide}data-itemsPerSlide="{$sliderItemsPerSlide}"{/if}
         {if $sliderItemsPerPage}data-itemsPerPage="{$sliderItemsPerPage}"{/if}
         {if $sliderAutoSlide}data-autoSlide="{$sliderAutoSlide}"{/if}
         {if $sliderAutoSlideDirection}data-autoSlideDirection="{$sliderAutoSlideDirection}"{/if}
         {if $sliderAutoSlideSpeed}data-autoSlideSpeed="{$sliderAutoSlideSpeed}"{/if}
         {if $sliderAutoScroll}data-autoScroll="{$sliderAutoScroll}"{/if}
         {if $sliderAutoScrollDirection}data-autoScrollDirection="{$sliderAutoScrollDirection}"{/if}
         {if $sliderAutoScrollSpeed}data-autoScrollSpeed="{$sliderAutoScrollSpeed}"{/if}
         {if $sliderScrollDistance}data-scrollDistance="{$sliderScrollDistance}"{/if}
         {if $sliderAnimationSpeed}data-animationSpeed="{$sliderAnimationSpeed}"{/if}
         {if $sliderArrowControls}data-arrowControls="{$sliderArrowControls}"{/if}
         {if $sliderArrowAction}data-arrowAction="{$sliderArrowAction}"{/if}
         {if $sliderWrapperCls}data-wrapperCls="{$sliderWrapperCls}"{/if}
         {if $sliderHorizontalCls}data-horizontalCls="{$sliderHorizontalCls}"{/if}
         {if $sliderVerticalCls}data-verticalCls="{$sliderVerticalCls}"{/if}
         {if $sliderArrowCls}data-arrowCls="{$sliderArrowCls}"{/if}
         {if $sliderPrevArrowCls}data-prevArrowCls="{$sliderPrevArrowCls}"{/if}
         {if $sliderNextArrowCls}data-nextArrowCls="{$sliderNextArrowCls}"{/if}
         {if $sliderAjaxCtrlUrl}data-ajaxCtrlUrl="{$sliderAjaxCtrlUrl}"{/if}
         {if $sliderAjaxCategoryID}data-ajaxCategoryID="{$sliderAjaxCategoryID}"{/if}
         {if $sliderAjaxMaxShow}data-ajaxMaxShow="{$sliderAjaxMaxShow}"{/if}
         {if $sliderAjaxShowLoadingIndicator}data-ajaxShowLoadingIndicator="{$sliderAjaxShowLoadingIndicator}"{/if}
         {if $sliderInitOnEvent}data-initOnEvent="{$sliderInitOnEvent}"{/if}
         data-product-slider="true">

        {block name="frontend_common_product_slider_container"}
            <div class="product-slider--container">
                {include file="frontend/_includes/product_slider_items.tpl" articles=$articles}
            </div>
        {/block}

    </div>
{/block}