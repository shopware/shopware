{extends file='frontend/account/index.tpl'}

{* Breadcrumb *}
{block name='frontend_index_start' append}
    {$sBreadcrumb[] = ['name'=>"{s name='MyOrdersTitle'}{/s}", 'link'=>{url}]}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div class="content account--content">

        {* Welcome text *}
        {block name="frontend_account_orders_welcome"}
            <div class="account--welcome panel">
                {block name="frontend_account_orders_welcome_headline"}
                    <h1 class="panel--title">{s name="OrdersHeader"}{/s}</h1>
                {/block}

                {block name="frontend_account_orders_welcome_content"}
                    <div class="panel--body is--wide">
                        <p>{s name="OrdersWelcomeText"}{/s}</p>
                    </div>
                {/block}
            </div>
        {/block}

        {if !$sOpenOrders}
            {block name="frontend_account_orders_info_empty"}
                <div class="account--no-orders-info">
                    {include file="frontend/_includes/messages.tpl" type="warning" content="{s name='OrdersInfoEmpty'}{/s}"}
                </div>
            {/block}
        {else}
            {* Orders overview *}
            {block name="frontend_account_orders_overview"}
                <div class="account--orders-overview panel is--rounded">

                    {block name="frontend_account_orders_table"}
                        <div class="panel--table">
                            {block name="frontend_account_orders_table_head"}
                                <div class="orders--table-header panel--tr">

                                    {block name="frontend_account_orders_table_head_date"}
                                        <div class="panel--th column--date">{s name="OrderColumnDate"}{/s}</div>
                                    {/block}

                                    {block name="frontend_account_orders_table_head_id"}
                                        <div class="panel--th column--id">{s name="OrderColumnId"}{/s}</div>
                                    {/block}

                                    {block name="frontend_account_orders_table_head_dispatch"}
                                        <div class="panel--th column--dispatch">{s name="OrderColumnDispatch"}{/s}</div>
                                    {/block}

                                    {block name="frontend_account_orders_table_head_status"}
                                        <div class="panel--th column--status">{s name="OrderColumnStatus"}{/s}</div>
                                    {/block}

                                    {block name="frontend_account_orders_table_head_actions"}
                                        <div class="panel--th column--actions is--align-center">{s name="OrderColumnActions"}{/s}</div>
                                    {/block}
                                </div>
                            {/block}

                            {block name="frontend_account_order_item_overview"}
                                {foreach $sOpenOrders as $offerPosition}
                                    {include file="frontend/account/order_item.tpl"}
                                {/foreach}
                            {/block}
                        </div>
                    {/block}

                    {block name="frontend_account_orders_actions_paging"}
                        <div class="account--paging panel--paging">
                            {if $sPages.previous}
                                <a href="{$sPages.previous}" class="btn paging--link paging--prev">
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

                            {block name='frontend_account_orders_actions_paging_count'}
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