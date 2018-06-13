{extends file="frontend/address/index.tpl"}
{namespace name="frontend/address/index"}

{* Breadcrumb *}
{block name="frontend_index_start" append}
    {$sBreadcrumb[] = ["name"=>"{s name="AddressesDeleteTitle"}Delete address{/s}", "link"=>{url}]}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div class="account--address account--content address--delete">

        {* Addresses headline *}
        {block name="frontend_address_headline"}
            <div class="account--welcome">
                <h1 class="panel--title">{s name="AddressesDeleteTitle"}Delete address{/s}</h1>
            </div>
        {/block}

        {block name="frontend_address_content"}

            {block name="frontend_address_delete_notice"}
                <p>
                    {s name="AddressesDeleteNotice"}<b>Please note:</b> Deleting this address will not delete any pending orders being shipped to this address.{/s}
                    <br/>
                    {s name="AddressesDeleteConfirmText"}To permanently remove this address from your address book, click Confirm.{/s}
                </p>
            {/block}

            {block name="frontend_address_delete_content"}
                <div class="panel has--border is--rounded block address--box">

                    <div class="panel--body is--wide address--item-body">
                        {block name="frontend_address_delete_content_inner"}
                            {if $address.company}
                                <p><span class="address--company">{$address.company|escapeHtml}</span>{if $address.department} - <span class="address--department">{$address.department|escapeHtml}</span>{/if}</p>
                            {/if}
                            <span class="address--salutation">{$address.salutation|salutation}</span>
                            {if {config name="displayprofiletitle"}}
                                <span class="address--title">{$address.title|escapeHtml}</span><br/>
                            {/if}
                            <span class="address--firstname">{$address.firstname|escapeHtml}</span> <span class="address--lastname">{$address.lastname|escapeHtml}</span><br />
                            <span class="address--street">{$address.street|escapeHtml}</span><br />
                            {if $address.additionalAddressLine1}<span class="address--additional-one">{$address.additionalAddressLine1|escapeHtml}</span><br />{/if}
                            {if $address.additionalAddressLine2}<span class="address--additional-two">{$address.additionalAddressLine2|escapeHtml}</span><br />{/if}
                            {if {config name=showZipBeforeCity}}
                                <span class="address--zipcode">{$address.zipcode|escapeHtml}</span> <span class="address--city">{$address.city|escapeHtml}</span>
                            {else}
                                <span class="address--city">{$address.city|escapeHtml}</span> <span class="address--zipcode">{$address.zipcode|escapeHtml}</span>
                            {/if}<br />
                            {if $address.state.name}<span class="address--statename">{$address.state.name|escapeHtml}</span><br />{/if}
                            {$address.country.name|escapeHtml}
                        {/block}
                    </div>

                </div>
            {/block}

            {block name="frontend_address_delete_actions"}
                <div class="address--delete-actions">
                    <form action="{url controller=address action=delete id=$address.id}" method="post">

                        {block name="frontend_address_delete_actions_cancel"}
                            <a href="{url controller=address action=index}" title="{s name="AddressesDeleteCancelText"}Cancel{/s}" class="btn  is--secondary">
                                {s name="AddressesDeleteCancelText"}Cancel{/s}
                            </a>
                        {/block}

                        {block name="frontend_address_delete_actions_confirm"}
                            <button type="submit" title="{s name="AddressesDeleteButtonText"}Confirm{/s}" class="btn is--primary is--right">
                                {s name="AddressesDeleteButtonText"}Confirm{/s}
                            </button>
                        {/block}
                    </form>
                </div>
            {/block}
        {/block}
    </div>
{/block}
