{extends file='frontend/index/index.tpl'}

{* Breadcrumb *}
{block name='frontend_index_start' prepend}
    {$sBreadcrumb = [['name'=>"{s name='NoteTitle'}{/s}", 'link'=>{url}]]}
{/block}

{* Account Sidebar *}
{block name="frontend_index_left_categories" prepend}
    {block name="frontend_account_sidebar"}
        {include file="frontend/account/sidebar.tpl"}
    {/block}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div class="content{if $sUserLoggedIn} account--content{else} note--content{/if}" data-compare-ajax="true">

        {* Infotext *}
        {block name="frontend_note_index_welcome"}
            <div class="account--welcome panel">
                {block name="frontend_note_index_welcome_headline"}
                    <h1 class="panel--title">{s name="NoteHeadline"}{/s}</h1>
                {/block}

                {block name="frontend_note_index_welcome_content"}
                    <div class="panel--body is--wide">
                        <p>{s name="NoteText"}{/s}</p>
                        <p>{s name="NoteText2"}{/s}</p>
                    </div>
                {/block}
            </div>
        {/block}

        {block name="frontend_note_index_overview"}
            <div class="note--overview">
                {if $sNotes}
                    {block name="frontend_note_index_table"}
                        <div class="panel--table is--rounded">

                            {block name="frontend_note_index_table_items"}
                                {foreach $sNotes as $sBasketItem}
                                    {include file="frontend/note/item.tpl"}
                                {/foreach}
                            {/block}
                        </div>
                    {/block}
                {/if}
            </div>
        {/block}

    </div>
{/block}

{* Hide article information *}
{block name="frontend_note_index_table_head_name"}{/block}

{* Hide unit price *}
{block name="frontend_note_index_table_head_price"}{/block}
{block name="frontend_note_index_table_columns"}{/block}