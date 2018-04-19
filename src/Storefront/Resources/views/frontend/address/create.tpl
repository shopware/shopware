{extends file="frontend/address/index.tpl"}
{namespace name="frontend/address/index"}

{* Breadcrumb *}
{block name="frontend_index_start" append}
    {$sBreadcrumb[] = ["name"=>"{s name="AddressesTitleCreate"}Create new address{/s}", "link"=>{url}]}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div class="account--address account--address-form account--content" data-register="true">

        {* Address headline *}
        {block name="frontend_address_form_headline"}
            <div class="account--welcome">
                <h1 class="panel--title">
                    {s name="AddressesTitleCreate"}Create new address{/s}
                </h1>
            </div>
        {/block}

        {block name="frontend_address_form_content"}
            {if $sTarget}
                {$url={url controller=address action=create sTarget=$sTarget sTargetAction=$sTargetAction}}
            {else}
                {$url={url controller=address action=create}}
            {/if}
            <div class="panel has--border is--rounded">
                <form name="frmAddresses" method="post" action="{$url}">
                    {include file="frontend/address/form.tpl" formAction="{$url}"}
                </form>
            </div>
        {/block}

    </div>
{/block}