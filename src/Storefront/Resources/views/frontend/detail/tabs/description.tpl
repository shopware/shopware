{namespace name="frontend/detail/description"}

{* Offcanvas buttons *}
{block name='frontend_detail_description_buttons_offcanvas'}
    <div class="buttons--off-canvas">
        {block name='frontend_detail_description_buttons_offcanvas_inner'}
            <a href="#" title="{"{s name="OffcanvasCloseMenu" namespace="frontend/detail/description"}{/s}"|escape}" class="close--off-canvas">
                <i class="icon--arrow-left"></i>
                {s name="OffcanvasCloseMenu" namespace="frontend/detail/description"}{/s}
            </a>
        {/block}
    </div>
{/block}

{block name="frontend_detail_description"}
<div class="content--description">

    {* Headline *}
    {block name='frontend_detail_description_title'}
        <div class="content--title">
            {s name="DetailDescriptionHeader"}{/s} "{$sArticle.articleName}"
        </div>
    {/block}

    {* Product description *}
    {block name='frontend_detail_description_text'}
        <div class="product--description" itemprop="description">
            {$sArticle.description_long}
        </div>
    {/block}

    {* Properties *}
    {block name='frontend_detail_description_properties'}
        {if $sArticle.sProperties}
            <div class="product--properties panel has--border">
                <table class="product--properties-table">
                    {foreach $sArticle.sProperties as $sProperty}
                        <tr class="product--properties-row">
                            {* Property label *}
                            {block name='frontend_detail_description_properties_label'}
                                <td class="product--properties-label is--bold">{$sProperty.name|escape}:</td>
                            {/block}

                            {* Property content *}
                            {block name='frontend_detail_description_properties_content'}
                                <td class="product--properties-value">{$sProperty.value|escape}</td>
                            {/block}
                        </tr>
                    {/foreach}
                </table>
            </div>
        {/if}
    {/block}

    {* Product - Further links *}
    {block name='frontend_detail_description_links'}

        {* Further links title *}
        {block name='frontend_detail_description_links_title'}
            <div class="content--title">
                {s name="ArticleTipMoreInformation"}{/s} "{$sArticle.articleName}"
            </div>
        {/block}

        {* Links list *}
        {block name='frontend_detail_description_links_list'}
            <ul class="content--list list--unstyled">
                {block name='frontend_detail_actions_contact'}
                    <li class="list--entry">
                        <a href="{$sInquiry}" rel="nofollow" class="content--link link--contact" title="{"{s name='DetailLinkContact' namespace="frontend/detail/actions"}{/s}"|escape}">
                            <i class="icon--arrow-right"></i> {s name="DetailLinkContact" namespace="frontend/detail/actions"}{/s}
                        </a>
                    </li>
                {/block}

                {foreach $sArticle.sLinks as $information}
                    {if $information.supplierSearch}

                        {* Vendor landing page link *}
                        {block name='frontend_detail_description_links_supplier'}
                            <li class="list--entry">
                                <a href="{url controller='listing' action='manufacturer' sSupplier=$sArticle.supplierID}"
                                   target="{$information.target}"
                                   class="content--link link--supplier"
                                   title="{"{s name="DetailDescriptionLinkInformation"}{/s}"|escape}">

                                    <i class="icon--arrow-right"></i> {s name="DetailDescriptionLinkInformation"}{/s}
                                </a>
                            </li>
                        {/block}
                    {else}

                        {* Links which will be added throught the administration *}
                        {block name='frontend_detail_description_links_link'}
                            <li class="list--entry">
                                <a href="{$information.link}"
                                   target="{if $information.target}{$information.target}{else}_blank{/if}"
                                   class="content--link link--further-links"
                                   title="{$information.description|escapeHtml}">
                                    <i class="icon--arrow-right"></i> {$information.description|escapeHtml}
                                </a>
                            </li>
                        {/block}
                    {/if}
                {/foreach}
            </ul>
        {/block}
    {/block}

    {* Downloads *}
    {block name='frontend_detail_description_downloads'}
        {if $sArticle.sDownloads}

            {* Downloads title *}
            {block name='frontend_detail_description_downloads_title'}
                <div class="content--title">
                    {s name="DetailDescriptionHeaderDownloads"}{/s}
                </div>
            {/block}

            {* Downloads list *}
            {block name='frontend_detail_description_downloads_content'}
                <ul class="content--list list--unstyled">
                    {foreach $sArticle.sDownloads as $download}
                        {block name='frontend_detail_description_downloads_content_link'}
                            <li class="list--entry">
                                <a href="{$download.filename}" target="_blank" class="content--link link--download" title="{"{s name="DetailDescriptionLinkDownload"}{/s}"|escape} {$download.description|escape}">
                                    <i class="icon--arrow-right"></i> {s name="DetailDescriptionLinkDownload"}{/s} {$download.description}
                                </a>
                            </li>
                        {/block}
                    {/foreach}
                </ul>
            {/block}
        {/if}
    {/block}

    {* Comment - Item open text fields attr3 *}
    {block name='frontend_detail_description_our_comment'}
        {if $sArticle.attr3}

            {* Comment title  *}
            {block name='frontend_detail_description_our_comment_title'}
                <div class="content--title">
                    {s name='DetailDescriptionComment'}{/s} "{$sArticle.articleName}"
                </div>
            {/block}

            {block name='frontend_detail_description_our_comment_title_content'}
                <blockquote class="content--quote">{$sArticle.attr3}</blockquote>
            {/block}
        {/if}
    {/block}
</div>
{/block}
