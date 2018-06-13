{namespace name="frontend/listing/get_category"}

{block name="widgets_listing_get_category"}
    <div class="offcanvas--overlay">

        {block name="widgets_listing_get_category_mainmenu"}
            <div class="overlay--headline">
                {block name="widgets_listing_get_category_mainmenu_link"}
                    <a class="navigation--link link--go-main" href="#" title="{s name="MainMenu"}{/s}">
                        <i class="icon--menu"></i> {s name="MainMenu"}{/s}
                    </a>
                {/block}
            </div>
        {/block}

        {block name="widgets_listing_get_category_name"}
            <div class="overlay--category">
                {block name="widgets_listing_get_category_name_link"}
                    <a href="{$category.link}" title="{$category.name|escape}">
                        <span class="category--headline">{$category.name}</span>
                    </a>
                {/block}
            </div>
        {/block}

        {block name="widgets_listing_get_category_categories"}
            <ul class="sidebar--navigation categories--sublevel navigation--list" role="menu">

                {* Go back button *}
                {block name="widgets_listing_get_category_categories_back"}
                    <li class="navigation--entry" role="menuitem">
                        {block name="widgets_listing_get_category_categories_back_link"}
                            <a href="{url module=widgets controller=listing action=getCategory categoryId={$category.parentId}}" data-parentId="{$category.parentId}" class="navigation--link is--back-button link--go-back" title="{s name="ButtonBack"}{/s}">
                                {block name="widgets_listing_get_category_categories_back_link_arrow_left"}
                                    <span class="is--icon-left">
                                        <i class="icon--arrow-left"></i>
                                    </span>
                                {/block}

                                {block name="widgets_listing_get_category_categories_back_link_name"}
                                    {s name="ButtonBack"}{/s}
                                {/block}
                            </a>
                        {/block}
                    </li>
                {/block}

                {* Show this category button *}
                {block name="widgets_listing_get_category_categories_show"}
                    <li class="navigation--entry" role="menuitem">
                        {block name="widgets_listing_get_category_categories_show_link"}
                            <a href="{$category.link}" title="{s name="ButtonShowPrepend"}{/s} {$category.name|escape} {s name="ButtonShowAppend"}{/s}" class="navigation--link is--display-button">
                                {block name="widgets_listing_get_category_categories_show_link_name"}
                                    {s name="ButtonShowPrepend"}{/s} {$category.name} {s name="ButtonShowAppend"}{/s}
                                {/block}
                            </a>
                        {/block}
                    </li>
                {/block}

                {* sub categories *}
                {foreach $category.children as $children}
                    {block name="widgets_listing_get_category_categories_item"}
                        {if $children.active}
                            <li class="navigation--entry" role="menuitem">
                                {block name="widgets_listing_get_category_categories_item_link"}
                                    <a href="{$children.link}" title="{$children.name|escape}"
                                       class="navigation--link{if $children.childrenCount} link--go-forward{/if}"
                                       data-category-id="{$children.id}"
                                       data-fetchUrl="{url module=widgets controller=listing action=getCategory categoryId={$children.id}}">

                                        {block name="widgets_listing_get_category_categories_item_link_name"}
                                            {$children.name}
                                        {/block}

                                        {block name="widgets_listing_get_category_categories_item_link_children"}
                                            {if $children.childrenCount}
                                                <span class="is--icon-right">
                                                    <i class="icon--arrow-right"></i>
                                                </span>
                                            {/if}
                                        {/block}
                                    </a>
                                {/block}
                            </li>
                        {/if}
                    {/block}
                {/foreach}
            </ul>
        {/block}
    </div>
{/block}