{block name="widget_emotion_component_html_code"}

    {* Smarty parsing *}
    {block name="widget_emotion_component_html_code_smarty"}
        {if $Data.smarty}
            {include file="string:{$Data.smarty}"}
        {/if}
    {/block}

    {* Javascript code snippet *}
    {block name="widget_emotion_component_html_code_javascript"}
        {if $Data.javascript}
            <script type="text/javascript">
                //<![CDATA[
                {strip}{$Data.javascript}{/strip}
                //]]>
            </script>
        {/if}
    {/block}
{/block}