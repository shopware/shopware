{block name="frontend_widgets_manufacturer_slider"}
    <div class="emotion--manufacturer panel{if !$Data.no_border} has--border{/if}">

        {* Manufacturer title *}
        {block name="frontend_widgets_manufacturer_slider_title"}
            {if $Data.manufacturer_slider_title}
                <div class="panel--title is--underline manufacturer--title">
                    {$Data.manufacturer_slider_title}
                </div>
            {/if}
        {/block}

        {* Manufacturer Content *}
        {block name="frontend_widgets_manufacturer_slider_content"}
            <div class="manufacturer--content">

                {block name="frontend_widgets_manufacturer_slider_container"}
                    <div class="manufacturer--slider product-slider"
                         data-product-slider="true"
                         data-itemMinWidth="280"
                         data-arrowControls="{if $Data.manufacturer_slider_arrows == 1}true{else}false{/if}"
                         data-autoSlide="{if $Data.manufacturer_slider_rotation == 1}true{else}false{/if}"
                         {if $Data.manufacturer_slider_scrollspeed}data-animationSpeed="{$Data.manufacturer_slider_scrollspeed}"{/if}
                         {if $Data.manufacturer_slider_rotatespeed}data-autoSlideSpeed="{$Data.manufacturer_slider_rotatespeed / 1000}"{/if}>

                        <div class="product-slider--container">
                            {foreach $Data.values as $supplier}
                                {if !$supplier.link}
                                    {$supplier.link = {url module=frontend controller=listing action=manufacturer sSupplier=$supplier.id}}
                                {/if}

                                {block name="frontend_widgets_manufacturer_slider_item"}
                                    <div class="manufacturer--item product-slider--item">

                                        {block name="frontend_widgets_manufacturer_slider_item_link"}
                                            <a href="{$supplier.link}" title="{$supplier.name|escape}" class="manufacturer--link">
                                                {if $supplier.image}
                                                    {block name="frontend_widgets_manufacturer_slider_item_image"}
                                                        <img class="manufacturer--image" src="{$supplier.image}" alt="{$supplier.name|escape}" />
                                                    {/block}
                                                {else}
                                                    {block name="frontend_widgets_manufacturer_slider_item_text"}
                                                        <span class="manufacturer--name">{$supplier.name}</span>
                                                    {/block}
                                                {/if}
                                            </a>
                                        {/block}
                                    </div>
                                {/block}
                            {/foreach}
                        </div>
                    </div>
                {/block}
            </div>
        {/block}
    </div>
{/block}
