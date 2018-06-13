{extends file="frontend/account/index.tpl"}

{* Breadcrumb *}
{block name="frontend_index_start" append}
    {$sBreadcrumb[] = ["name"=>"{s name="AddressesTitle"}My addresses{/s}", "link"=>{url action="index"}]}
{/block}

{* Main content *}
{block name="frontend_index_content"}
    <div class="account--address account--content">

        {* Addresses headline *}
        {block name="frontend_address_headline"}
            <div class="account--welcome">
                <h1 class="panel--title">{s name="AddressesTitle"}My addresses{/s}</h1>
            </div>
        {/block}

        {* Success messages *}
        {block name="frontend_address_success_messages"}
            {if $success}
                {include file="frontend/address/success_messages.tpl" type=$success}
            {/if}
        {/block}

        {* Error messages *}
        {block name="frontend_address_error_messages"}
            {if $error}
                {include file="frontend/address/error_messages.tpl" type=$error}
            {/if}
        {/block}

        {block name="frontend_address_content"}
            <div class="address--content block-group" data-panel-auto-resizer="true">

                {foreach $addresses as $address}
                    {block name="frontend_address_item_content"}
                        <div class="address--item-content address--box">
                            <div class="panel has--border is--rounded block">
                                <div class="panel--body is--wide">
                                    {block name="frontend_address_item_content_body"}
                                        <div class="address--item-body">
                                            {block name="frontend_address_item_content_title"}
                                                {if $sUserData.additional.user.default_shipping_address_id == $address.id || $sUserData.additional.user.default_billing_address_id == $address.id}
                                                    <div class="panel--title is--underline">
                                                        {if $sUserData.additional.user.default_shipping_address_id == $address.id}
                                                            <div>{s name="AddressesTitleDefaultShippingAddress"}Default shipping address{/s}</div>
                                                        {/if}
                                                        {if $sUserData.additional.user.default_billing_address_id == $address.id}
                                                            <div>{s name="AddressesTitleDefaultBillingAddress"}Default billing address{/s}</div>
                                                        {/if}
                                                    </div>
                                                {/if}
                                            {/block}
                                            {block name="frontend_address_item_content_inner"}
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
                                                <span class="address--countryname">{$address.country.name|escapeHtml}</span>
                                            {/block}
                                        </div>
                                    {/block}
                                </div>

                                {block name="frontend_address_item_content_actions"}
                                    <div class="address--item-actions panel--actions is--wide">
                                        {block name="frontend_address_item_content_set_default"}
                                            <div class="address--actions-set-defaults">

                                                {block name="frontend_address_item_content_set_default_shipping"}
                                                    {if $sUserData.additional.user.default_shipping_address_id != $address.id}
                                                        <form action="{url controller="address" action="setDefaultShippingAddress"}" method="post">
                                                            <input type="hidden" name="addressId" value="{$address.id}" />
                                                            <button type="submit" class="btn is--link is--small">{s name="AddressesSetAsDefaultShippingAction"}{/s}</button>
                                                        </form>
                                                    {/if}
                                                {/block}

                                                {block name="frontend_address_item_content_set_default_billing"}
                                                    {if $sUserData.additional.user.default_billing_address_id != $address.id}
                                                        <form action="{url controller="address" action="setDefaultBillingAddress"}" method="post">
                                                            <input type="hidden" name="addressId" value="{$address.id}" />
                                                            <button type="submit" class="btn is--link is--small">{s name="AddressesSetAsDefaultBillingAction"}{/s}</button>
                                                        </form>
                                                    {/if}
                                                {/block}

                                            </div>
                                        {/block}

                                        {block name="frontend_address_item_content_actions_change"}
                                            <a href="{url controller=address action=edit id=$address.id}" title="{s name="AddressesContentItemActionEdit"}Change{/s}" class="btn is--small">
                                                {s name="AddressesContentItemActionEdit"}Change{/s}
                                            </a>
                                        {/block}

                                        {block name="frontend_address_item_content_actions_delete"}
                                            {if $sUserData.additional.user.default_shipping_address_id != $address.id && $sUserData.additional.user.default_billing_address_id != $address.id}
                                                <a href="{url controller=address action=delete id=$address.id}" title="{s name="AddressesContentItemActionDelete"}Delete{/s}" class="btn is--small">
                                                    {s name="AddressesContentItemActionDelete"}Delete{/s}
                                                </a>
                                            {/if}
                                        {/block}
                                    </div>
                                {/block}

                            </div>
                        </div>
                    {/block}
                {/foreach}

                {block name="frontend_address_item_content_create"}
                    <div class="address--item-content address--item-create block">
                        <a href="{url controller=address action=create}" title="{s name="AddressesContentItemActionCreate"}Create new address +{/s}" class="btn is--block is--primary">
                            {s name="AddressesContentItemActionCreate"}Create new address +{/s}
                        </a>
                    </div>
                {/block}
            </div>
        {/block}

    </div>

{/block}
