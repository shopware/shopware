{block name='frontend_detail_configurator_error'}
    {if $sArticle.sError && $sArticle.sError.variantNotAvailable}
        {include file="frontend/_includes/messages.tpl" type="error" content="{s name='VariantAreNotAvailable'}{/s}"}
    {/if}
{/block}

<form method="post" action="{url sArticle=$sArticle.articleID sCategory=$sArticle.categoryID}" class="configurator--form selection--form">
    {foreach from=$sArticle.sConfigurator item=sConfigurator name=group key=groupID}

        {* Group name *}
        {block name='frontend_detail_group_name'}
            <p class="configurator--label">{$sConfigurator.groupname}:</p>
        {/block}

        {* Group description *}
        {block name='frontend_detail_group_description'}
            {if $sConfigurator.groupdescription}
                <p class="configurator--description">{$sConfigurator.groupdescription}</p>
            {/if}
        {/block}

        {$pregroupID=$groupID-1}
        {* Configurator drop down *}
        {block name='frontend_detail_group_selection'}
            <div class="field--select select-field{if $groupID gt 0 && empty($sArticle.sConfigurator[$pregroupID].user_selected)} is--disabled{/if}">
                <select{if $groupID gt 0 && empty($sArticle.sConfigurator[$pregroupID].user_selected)} disabled="disabled"{/if} name="group[{$sConfigurator.groupID}]"{if $theme.ajaxVariantSwitch} data-ajax-select-variants="true"{else} data-auto-submit="true"{/if}>

                    {* Please select... *}
                    {if empty($sConfigurator.user_selected)}
                        <option value="" selected="selected">{s name="DetailConfigValueSelect"}{/s}</option>
                    {/if}

                    {foreach from=$sConfigurator.values item=configValue name=option key=optionID}
                        <option {if !$configValue.selectable}disabled{/if} {if $configValue.selected && $sConfigurator.user_selected} selected="selected"{/if} value="{$configValue.optionID}">
                            {$configValue.optionname}{if $configValue.upprice && !$configValue.reset} {if $configValue.upprice > 0}{/if}{/if}
                            {if !$configValue.selectable}{s name="DetailConfigValueNotAvailable"}{/s}{/if}
                        </option>
                    {/foreach}
                </select>
            </div>
        {/block}
    {/foreach}

    {block name='frontend_detail_configurator_noscript_action'}
        <noscript>
            <input name="recalc" type="submit" value="{s name='DetailConfigActionSubmit'}{/s}" />
        </noscript>
    {/block}
</form>

{block name='frontend_detail_configurator_step_reset'}
    {include file="frontend/detail/config_reset.tpl"}
{/block}
