{block name="widget_emotion_component_html_panel"}
    <div class="emotion--html{if !$Data.needsNoStyling} panel has--border{/if}">

        {block name="widget_emotion_component_html_title"}
            {if $Data.cms_title}
                <div class="html--title{if !$Data.needsNoStyling} panel--title is--underline{/if}">
                    {$Data.cms_title}
                </div>
            {/if}
        {/block}

        {block name="widget_emotion_component_html_content"}
            <div class="html--content{if !$Data.needsNoStyling} panel--body is--wide{/if}">
                {$Data.text}
            </div>
        {/block}
    </div>
{/block}