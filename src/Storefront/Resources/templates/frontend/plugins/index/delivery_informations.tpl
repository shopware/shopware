{* Delivery informations *}
{block name='frontend_widgets_delivery_infos'}
    <div class="product--delivery">
        {if $sArticle.shippingfree}
            <p class="delivery--information">
                <span class="delivery--text delivery--text-shipping-free">
                    <i class="delivery--status-icon delivery--status-shipping-free"></i>
                    {s name="DetailDataInfoShippingfree"}{/s}
                </span>
            </p>
        {/if}
        {if isset($sArticle.active) && !$sArticle.active}
            <link itemprop="availability" href="http://schema.org/LimitedAvailability" />
            <p class="delivery--information">
                <span class="delivery--text  delivery--text-not-available">
                    <i class="delivery--status-icon delivery--status-not-available"></i>
                    {s name="DetailDataInfoNotAvailable"}{/s}
                </span>
            </p>
        {elseif $sArticle.sReleaseDate && $sArticle.sReleaseDate|date_format:"%Y%m%d" > $smarty.now|date_format:"%Y%m%d"}
            <link itemprop="availability" href="http://schema.org/PreOrder" />
            <p class="delivery--information">
                <span class="delivery--text delivery--text-more-is-coming">
                    <i class="delivery--status-icon delivery--status-more-is-coming"></i>
                    {s name="DetailDataInfoShipping"}{/s} {$sArticle.sReleaseDate|date:'date_long'}
                </span>
            </p>
        {elseif $sArticle.esd}
            <link itemprop="availability" href="http://schema.org/InStock" />
            <p class="delivery--information">
                <span class="delivery--text delivery--text-available">
                    <i class="delivery--status-icon delivery--status-available"></i>
                    {s name="DetailDataInfoInstantDownload"}{/s}
                </span>
            </p>
        {elseif {config name="instockinfo"} && $sArticle.modus == 0 && $sArticle.instock > 0 && $sArticle.quantity > $sArticle.instock}
            <link itemprop="availability" href="http://schema.org/LimitedAvailability" />
            <p class="delivery--information">
                <span class="delivery--text delivery--text-more-is-coming">
                    <i class="delivery--status-icon delivery--status-more-is-coming"></i>
                    {s name="DetailDataInfoPartialStock"}{/s}
                </span>
            </p>
        {elseif $sArticle.instock >= $sArticle.minpurchase}
            <link itemprop="availability" href="http://schema.org/InStock" />
            <p class="delivery--information">
                <span class="delivery--text delivery--text-available">
                    <i class="delivery--status-icon delivery--status-available"></i>
                    {s name="DetailDataInfoInstock"}{/s}
                </span>
            </p>
        {elseif $sArticle.shippingtime}
            <link itemprop="availability" href="http://schema.org/LimitedAvailability" />
            <p class="delivery--information">
                <span class="delivery--text delivery--text-more-is-coming">
                    <i class="delivery--status-icon delivery--status-more-is-coming"></i>
                    {s name="DetailDataShippingtime"}{/s} {$sArticle.shippingtime} {s name="DetailDataShippingDays"}{/s}
                </span>
            </p>
        {else}
            <link itemprop="availability" href="http://schema.org/LimitedAvailability" />
            <p class="delivery--information">
                <span class="delivery--text delivery--text-not-available">
                    <i class="delivery--status-icon delivery--status-not-available"></i>
                    {s name="DetailDataNotAvailable"}{config name=notavailable}{/s}
                </span>
            </p>
        {/if}
    </div>
{/block}
