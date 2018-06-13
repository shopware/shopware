{block name="widget_emotion_component_iframe"}
    <div class="emotion--iframe">
        {if $Data && $Data.iframe_url}
            <iframe class="external--content content--iframe"
                    width="100%"
                    height="100%"
                    src="{$Data.iframe_url}" frameborder="0">
            </iframe>
        {/if}
    </div>
{/block}