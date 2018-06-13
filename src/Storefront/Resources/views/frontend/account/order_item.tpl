{block name="frontend_account_order_item_overview_row"}
    <div class="order--item panel--tr">

        {* Order date *}
        {block name="frontend_account_order_item_date"}
            <div class="order--date panel--td column--date">

                {block name="frontend_account_order_item_date_label"}
                    <div class="column--label">
                        {s name="OrderColumnDate" namespace="frontend/account/orders"}{/s}:
                    </div>
                {/block}

                {block name="frontend_account_order_item_date_value"}
                    <div class="column--value">
                        {$offerPosition.datum|date}
                    </div>
                {/block}
            </div>
        {/block}

        {* Order id *}
        {block name="frontend_account_order_item_number"}
            <div class="order--number panel--td column--id is--bold">

                {block name="frontend_account_order_item_number_label"}
                    <div class="column--label">
                        {s name="OrderColumnId" namespace="frontend/account/orders"}{/s}:
                    </div>
                {/block}

                {block name="frontend_account_order_item_number_value"}
                    <div class="column--value">
                        {$offerPosition.ordernumber}
                    </div>
                {/block}
            </div>
        {/block}

        {* Dispatch type *}
        {block name="frontend_account_order_item_dispatch"}
            <div class="order--dispatch panel--td column--dispatch">

                {block name="frontend_account_order_item_dispatch_label"}
                    <div class="column--label">
                        {s name="OrderColumnDispatch" namespace="frontend/account/orders"}{/s}:
                    </div>
                {/block}

                {block name="frontend_account_order_item_dispatch_value"}
                    <div class="column--value">
                        {if $offerPosition.dispatch.name}
                            {$offerPosition.dispatch.name}
                        {else}
                            {s name="OrderInfoNoDispatch"}{/s}
                        {/if}
                    </div>
                {/block}
            </div>
        {/block}

        {* Order status *}
        {block name="frontend_account_order_item_status"}
            <div class="order--status panel--td column--status">

                {block name="frontend_account_order_item_status_label"}
                    <div class="column--label">
                        {s name="OrderColumnStatus" namespace="frontend/account/orders"}{/s}:
                    </div>
                {/block}

                {block name="frontend_account_order_item_status_value"}
                    <div class="column--value">
                        <span class="order--status-icon status--{$offerPosition.status}">&nbsp;</span>
                        {if $offerPosition.status==0}
                            {s name="OrderItemInfoNotProcessed"}{/s}
                        {elseif $offerPosition.status==1}
                            {s name="OrderItemInfoInProgress"}{/s}
                        {elseif $offerPosition.status==2}
                            {s name="OrderItemInfoCompleted"}{/s}
                        {elseif $offerPosition.status==3}
                            {s name="OrderItemInfoPartiallyCompleted"}{/s}
                        {elseif $offerPosition.status==4}
                            {s name="OrderItemInfoCanceled"}{/s}
                        {elseif $offerPosition.status==5}
                            {s name="OrderItemInfoReadyForShipping"}{/s}
                        {elseif $offerPosition.status==6}
                            {s name="OrderItemInfoPartiallyShipped"}{/s}
                        {elseif $offerPosition.status==7}
                            {s name="OrderItemInfoShipped"}{/s}
                        {elseif $offerPosition.status==8}
                            {s name="OrderItemInfoClarificationNeeded"}{/s}
                        {/if}
                    </div>
                {/block}
            </div>
        {/block}

        {* Order actions *}
        {block name="frontend_account_order_item_actions"}
            <div class="order--actions panel--td column--actions">
                <a href="#order{$offerPosition.ordernumber}"
                   title="{"{s name="OrderActionSlide"}{/s}"|escape} {$offerPosition.ordernumber}"
                   class="btn is--small"
                   data-collapse-panel="true"
                   data-collapseTarget="#order{$offerPosition.ordernumber}">
                    {s name="OrderActionSlide"}{/s}
                </a>
            </div>
        {/block}
    </div>
{/block}

{* Order details *}
{block name="frontend_account_order_item_detail"}
    {include file="frontend/account/order_item_details.tpl"}
{/block}