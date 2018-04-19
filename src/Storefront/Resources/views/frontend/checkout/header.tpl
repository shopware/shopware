<header class="header-main">

    {* Hide top bar navigation *}
    {block name='frontend_index_top_bar_container'}{/block}

    <div class="container header--navigation">

        {* Logo container *}
        {block name='frontend_index_logo_container'}
            <div class="logo-main block-group" role="banner">

                {* Main shop logo *}
                {block name='frontend_index_logo'}
                    <div class="logo--shop block">
                        <a class="logo--link" href="{url controller='index'}" title="{"{config name=shopName}"|escapeHtml} - {"{s name='IndexLinkDefault' namespace="frontend/index/index"}{/s}"|escape}">
                            <picture>
                                <source srcset="{link file=$theme.desktopLogo}" media="(min-width: 78.75em)">
                                <source srcset="{link file=$theme.tabletLandscapeLogo}" media="(min-width: 64em)">
                                <source srcset="{link file=$theme.tabletLogo}" media="(min-width: 48em)">

                                <img srcset="{link file=$theme.mobileLogo}" alt="{"{config name=shopName}"|escapeHtml} - {"{s name='IndexLinkDefault' namespace="frontend/index/index"}{/s}"|escape}" />
                            </picture>
                        </a>
                    </div>
                {/block}

                {* Support Info *}
                {block name='frontend_index_logo_supportinfo'}
                    <div class="logo--supportinfo block">
                        {s name='RegisterSupportInfo' namespace='frontend/register/index'}{/s}
                    </div>
                {/block}

                {* Trusted Shops *}
                {block name='frontend_index_logo_trusted_shops'}

                {/block}
            </div>
        {/block}

        {* Hide Shop navigation *}
        {block name='frontend_index_shop_navigation'}{/block}
    </div>
</header>

{* Hide Maincategories navigation top *}
{block name='frontend_index_navigation_categories_top'}{/block}
