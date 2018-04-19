{extends file='frontend/account/index.tpl'}

{block name="frontend_index_header_javascript_jquery" append}
<script>
    {* Configuration of the partner chart *}
    jQuery.partnerChart =  {ldelim}
        'timeUnit': '{s name='PartnerStatisticLabelTimeUnit'}{/s}',
        'netAmountLabel': '{s name='PartnerStatisticLabelNetTurnover'}{/s}'
    {rdelim};

    jQuery.datePickerRegional = {ldelim}
        closeText: "{s name='PartnerDatePickerCloseText'}{/s}",
        prevText: "{s name='PartnerDatePickerPrevText'}{/s}",
        nextText: "{s name='PartnerDatePickerNextText'}{/s}",
        currentText: "{s name='PartnerDatePickerCurrentText'}{/s}",
        monthNames: [{s name='PartnerDatePickerMonthNames'}{/s}],
        monthNamesShort: [{s name='PartnerDatePickerMonthShortNames'}{/s}],
        dayNames: [{s name='PartnerDatePickerDayNames'}{/s}],
        dayNamesShort: [{s name='PartnerDatePickerDayShortNames'}{/s}],
        dayNamesMin: [{s name='PartnerDatePickerDayMinNames'}{/s}],
        weekHeader: "{s name='PartnerDatePickerWeekHeader'}{/s}",
        dateFormat: "{s name='PartnerDatePickerDateFormat'}{/s}",
        firstDay: 1,
        isRTL: false,
        showMonthAfterYear: false,
        yearSuffix: "",
        showOn: "button",
        buttonText:"",
        onSelect: function (dateText, inst) {ldelim}
            $(this).parents('form').submit();
        {rdelim}
    {rdelim};

    $('*[data-datepicker="true"]').datepicker(jQuery.datePickerRegional);
</script>
{/block}

{block name="frontend_index_header_javascript_jquery_lib" append}
    <script type="text/javascript" src="{link file='frontend/_public/src/js/jquery.ui.datepicker.js'}"></script>
    <script type="text/javascript" src="{link file='frontend/_public/src/js/vendors/raphael/raphael.js'}"></script>
    <script type="text/javascript" src="{link file='frontend/_public/src/js/vendors/raphael/popup.js'}"></script>
    <script type="text/javascript" src="{link file='frontend/_public/src/js/vendors/raphael/analytics.js'}"></script>
{/block}

{* Breadcrumb *}
{block name='frontend_index_start' append}
    {$sBreadcrumb[] = ['name'=>"{s name='Provisions'}{/s}", 'link'=>{url}]}
{/block}

{* Main content *}
{block name='frontend_index_content'}

{* Partner Provision overview *}
<div class="content account--content partner-statistic">
    {* Welcome text *}
    {block name="frontend_account_orders_welcome"}
        <div class="account--welcome panel">
            {block name="frontend_account_orders_welcome_headline"}
                <h1 class="panel--title">{s name="PartnerStatisticHeader"}{/s}</h1>
            {/block}
        </div>
    {/block}
    <div class="partner-statistic-body panel has--border is--rounded">
        <div class="period--selection--form">
            {block name='frontend_account_partner_statistic_listing_actions_top'}
                <div class="top">
                    {block name="frontend_account_partner_statistic_listing_date"}
                        <form method="post" action="{url controller='account' action='partnerStatistic'}">
                            <div class="date-filter">
                                <label class="date-filter--label" for="datePickerFrom">{s name='PartnerStatisticLabelFromDate'}{/s}</label>
                                <div class="date-filter--input">
                                    <input id="datePickerFrom" class="datepicker text" data-datepicker="true" name="fromDate" type="text" value="{$partnerStatisticFromDate}"/>
                                </div>
                            </div>
                            <div class="date-filter">
                                <label class="date-filter--label" for="datePickerTo">{s name='PartnerStatisticLabelToDate'}{/s}</label>
                                <div class="date-filter--input">
                                    <input id="datePickerTo" class="datepicker text" data-datepicker="true" name="toDate" type="text" value="{$partnerStatisticToDate}"/>
                                </div>
                            </div>
                            <input type="submit" class="btn is--primary btn--filter is--small" value="{s name="PartnerStatisticSubmitFilter"}{/s}"/>
                        </form>
                    {/block}
                </div>
            {/block}
        </div>
        {if $sPartnerOrders}
        <table id="data" class="is--hidden">
            <tbody>
            <tr>
                {foreach $sPartnerOrderChartData as $chartItem}
                    <td>{$chartItem.netTurnOver|number_format:2:".":""}</td>
                {/foreach}
            </tr>
            </tbody>
            <tfoot>
            <tr>
                {foreach $sPartnerOrderChartData as $chartItem}
                    <th>{$chartItem.timeScale}</th>
                {/foreach}
            </tr>
            </tfoot>
        </table>
        <div id="holder" class="chart--holder"></div>

        <div class="partner_statistic_overview_active panel">

            {block name="frontend_account_statistic_overview_table"}
                <div class="panel--table">
                    {block name="frontend_account_partner_statistic_table_head"}
                        <div class="orders--table-header panel--tr">

                            <div class="panel--th column--date">
                                {s name="PartnerStatisticColumnDate"}{/s}
                            </div>

                            <div class="panel--th column--id">
                                {s name="PartnerStatisticColumnId"}{/s}
                            </div>

                            <div class="panel--th column--price">
                                {s name="PartnerStatisticColumnNetAmount"}{/s}
                            </div>

                            <div class="panel--th column--total">
                                {s name="PartnerStatisticColumnProvision"}{/s}
                            </div>
                        </div>
                    {/block}

                    {foreach $sPartnerOrders as $partnerOrder}
                        {if $partnerOrder@last}
                            {$lastitem=1}
                        {else}
                            {$lastitem=0}
                        {/if}

                        {include file="frontend/account/partner_statistic_item.tpl" lastitem=$lastitem}
                    {/foreach}
                </div>
            {/block}
        </div>
    </div>
    {else}
        {block name="frontend_account_partner_statistic_info_empty"}
            <div class="account--no-orders-info">
                {include file="frontend/_includes/messages.tpl" type="warning" content="{s name='PartnerStatisticInfoEmpty'}{/s}"}
            </div>
        {/block}
    {/if}
</div>
{/block}