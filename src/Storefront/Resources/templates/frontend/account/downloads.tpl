{extends file="frontend/account/index.tpl"}

{* Breadcrumb *}
{block name="frontend_index_start" append}
    {$sBreadcrumb[] = ['name'=>"{s name='MyDownloadsTitle'}{/s}", 'link'=>{url}]}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div class="content account--content">

        {* Error message *}
        {block name="frontend_account_downloads_error_messages"}
            {if $sErrorCode}
                {$errorText="{s name='DownloadsInfoNotFound'}{/s}"}
                {if $sErrorCode == 1}
                    {$errorText="{s name='DownloadsInfoAccessDenied'}{/s}"}
                {/if}

                <div class="account--error">
                    {include file="frontend/_includes/messages.tpl" type="warning" content=$errorText}
                </div>
            {/if}
        {/block}

        {* Welcome text *}
        {block name="frontend_account_downloads_welcome"}
            <div class="account--welcome panel">
                {block name="frontend_account_downloads_welcome_headline"}
                    <h1 class="panel--title">{s name="DownloadsHeader"}{/s}</h1>
                {/block}

                {block name="frontend_account_downloads_welcome_content"}
                    <div class="panel--body is--wide">
                        <p>{s name='DownloadsInfoText'}{/s}</p>
                    </div>
                {/block}
            </div>
        {/block}

        {* Missing ESD articles *}
        {if !$sDownloads}
            {block name="frontend_account_downloads_info_empty"}
                <div class="account--error">
                    {include file="frontend/_includes/messages.tpl" type="warning" content="{s name='DownloadsInfoEmpty'}{/s}"}
                </div>
            {/block}
        {else}
            {block name="frontend_account_downloads_table"}
                <div class="account--downloads panel panel--table is--rounded">

                    {block name="frontend_account_downloads_table_head"}
                        <div class="downloads--table-header panel--tr">

                            {block name="frontend_account_downloads_table_head_date"}
                                <div class="panel--th column--date">{s name="DownloadsColumnDate"}{/s}</div>
                            {/block}

                            {block name="frontend_account_downloads_table_head_info"}
                                <div class="panel--th column--info">{s name="DownloadsColumnName"}{/s}</div>
                            {/block}

                            {block name="frontend_account_downloads_table_head_actions"}
                                <div class="panel--th column--actions">{s name="DownloadsColumnLink"}{/s}</div>
                            {/block}
                        </div>
                    {/block}

                    {foreach $sDownloads as $offerPosition}
                        {foreach $offerPosition.details as $article}
                            {if $article.esdarticle}

                                {block name="frontend_account_downloads_table_row"}
                                    <div class="panel--tr">

                                        {block name="frontend_account_downloads_date"}
                                            <div class="download--date panel--td column--date">
                                                {$offerPosition.datum|date}
                                            </div>
                                        {/block}

                                        {block name="frontend_account_downloads_info"}
                                            <div class="download--name panel--td column--info">
                                                {block name="frontend_account_downloads_name"}
                                                    <p class="is--bold">{$article.name}</p>
                                                {/block}

                                                {block name="frontend_account_downloads_serial"}
                                                    {if $article.serial && $offerPosition.cleared|in_array:$sDownloadAvailablePaymentStatus}
                                                        <p class="download--serial">{s name="DownloadsSerialnumber"}{/s} <span class="is--strong">{$article.serial}</span></p>
                                                    {/if}
                                                {/block}
                                            </div>
                                        {/block}

                                        {block name="frontend_account_downloads_link"}
                                            <div class="download--actions panel--td column--actions">
                                                {if $article.esdarticle && $offerPosition.cleared|in_array:$sDownloadAvailablePaymentStatus}
                                                    <a href="{$article.esdLink}" title="{"{s name='DownloadsLink'}{/s}"|escape} {$article.name|escape}" class="btn is--primary is--small">
                                                        {s name="DownloadsLink"}{/s}
                                                    </a>
                                                {/if}
                                            </div>
                                        {/block}

                                    </div>
                                {/block}
                            {/if}
                        {/foreach}
                    {/foreach}

                    {block name="frontend_account_downloads_actions_paging"}
                        <div class="account--paging panel--paging">
                            {if $sPages.previous}
                                <a href="{$sPages.previous}" class="paging--link paging--prev">
                                    <i class="icon--arrow-left"></i>
                                </a>
                            {/if}

                            {foreach $sPages.numbers as $page}
                                {if $page.markup}
                                    <a class="paging--link is--active">{$page.value}</a>
                                {else}
                                    <a href="{$page.link}" class="paging--link">{$page.value}</a>
                                {/if}
                            {/foreach}

                            {if $sPages.next}
                                <a href="{$sPages.next}" class="paging--link paging--next">
                                    <i class="icon--arrow-right"></i>
                                </a>
                            {/if}

                            {block name='frontend_account_downloads_actions_paging_count'}
                                <div class="paging--display">
                                    {s name="ListingTextSite" namespace="frontend/listing/listing_actions"}{/s}
                                    <span class="is--bold">{if $sPage}{$sPage}{else}1{/if}</span>
                                    {s name="ListingTextFrom" namespace="frontend/listing/listing_actions"}{/s}
                                    <span class="is--bold">{$sNumberPages}</span>
                                </div>
                            {/block}
                        </div>
                    {/block}

                </div>
            {/block}
        {/if}
    </div>
{/block}