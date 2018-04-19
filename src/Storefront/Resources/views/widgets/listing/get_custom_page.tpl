{namespace name="frontend/listing/get_category"}

{$parent = $customPage.parent}

{block name="widgets_listing_get_custom_page"}
    <div class="offcanvas--overlay">

        {block name="widgets_listing_get_custom_page_mainmenu"}
            <div class="overlay--headline">
                {block name="widgets_listing_get_custom_page_mainmenu_link"}
                    <a class="navigation--link link--go-main" href="#" title="{s name="MainMenu"}{/s}">
                        <i class="icon--menu"></i> {s name="MainMenu"}{/s}
                    </a>
                {/block}
            </div>
        {/block}

        {if $parent}
            {block name="widgets_listing_get_custom_page_name"}
                <div class="overlay--category">
                    {block name="widgets_listing_get_custom_page_name_link"}
                        <a href="{if $parent.link}{$parent.link}{else}{url controller='custom' sCustom=$parent.id title=$parent.description}{/if}" title="{$parent.description|escape}">
                            <span class="category--headline">{$parent.description}</span>
                        </a>
                    {/block}
                </div>
            {/block}
        {/if}

        {block name="widgets_listing_get_custom_page_categories"}
            <ul class="sidebar--navigation categories--sublevel navigation--list" role="menu">

                {if $parent}
                    {* Go back button *}
                    {block name="widgets_listing_get_custom_page_categories_back"}
                        <li class="navigation--entry" role="menuitem">
                            {block name="widgets_listing_get_custom_page_categories_back_link"}
                                <a href="{if $parent.parentID}{url module=widgets controller=listing action=getCustomPage pageId={$parent.parentID}}{/if}" data-parentId="{$parent.parentID}" class="navigation--link is--back-button link--go-back" title="{s name="ButtonBack"}{/s}">
                                    {block name="widgets_listing_get_custom_page_categories_back_link_arrow_left"}
                                        <span class="is--icon-left">
                                            <i class="icon--arrow-left"></i>
                                        </span>
                                    {/block}

                                    {block name="widgets_listing_get_custom_page_categories_back_link_name"}
                                        {s name="ButtonBack"}{/s}
                                    {/block}
                                </a>
                            {/block}
                        </li>
                    {/block}

                    {* Show this category button *}
                    {block name="widgets_listing_get_custom_page_categories_show"}
                        <li class="navigation--entry" role="menuitem">
                            {block name="widgets_listing_get_custom_page_categories_show_link"}
                                <a href="{if $parent.link}{$parent.link}{else}{url controller='custom' sCustom=$parent.id title=$parent.description}{/if}" title="{$parent.description|escape} {s name="ButtonShow"}{/s}" class="navigation--link is--display-button">
                                    {block name="widgets_listing_get_custom_page_categories_show_link_name"}
                                        {s name="ButtonShowPrepend"}{/s} {$parent.description} {s name="ButtonShowAppend"}{/s}
                                    {/block}
                                </a>
                            {/block}
                        </li>
                    {/block}
                {/if}

                {* sub categories *}
                {foreach $customPage.children as $child}
                    {block name="widgets_listing_get_custom_page_categories_item"}
                        <li class="navigation--entry" role="menuitem">
                            {block name="widgets_listing_get_custom_page_categories_item_link"}
                                <a href="{if $child.link}{$child.link}{else}{url controller='custom' sCustom=$child.id title=$child.description}{/if}" title="{$child.description|escape}"
                                   class="navigation--link{if $child.childrenCount} link--go-forward{/if}"
                                   data-category-id="{$child.id}"
                                   data-fetchUrl="{url module=widgets controller=listing action=getCustomPage pageId={$child.id}}">

                                    {block name="widgets_listing_get_custom_page_categories_item_link_name"}
                                        {$child.description}
                                    {/block}

                                    {block name="widgets_listing_get_custom_page_categories_item_link_children"}
                                        {if $child.childrenCount}
                                            <span class="is--icon-right">
                                                <i class="icon--arrow-right"></i>
                                            </span>
                                        {/if}
                                    {/block}
                                </a>
                            {/block}
                        </li>
                    {/block}
                {/foreach}
            </ul>
        {/block}
    </div>
{/block}