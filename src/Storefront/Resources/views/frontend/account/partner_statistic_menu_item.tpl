{if $partnerId}
    {*Partner Statistic Menu Item*}
    <li class="navigation--entry">
        <a href="{url controller='account' action='partnerStatistic'}" class="navigation--link">
            {s name="AccountLinkPartnerStatistic" namespace="frontend/account/sidebar"}{/s}
        </a>
    </li>
{/if}