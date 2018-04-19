{extends file="frontend/index/index.tpl"}

{* hide shop navigation *}
{block name='frontend_index_navigation'}{/block}

{* hide breadcrumb bar *}
{block name='frontend_index_breadcrumb'}{/block}

{* hide left sidebar *}
{block name='frontend_index_content_left'}{/block}

{block name="frontend_index_body_classes" append}{strip} emotion--preview{/strip}{/block}

{block name="frontend_index_content"}

    {block name="widgets_emotion_preview_content"}
        <div class="content content--emotion-preview">
            <div class="content--emotions">

                {block name="widgets_emotion_preview_wrapper"}
                    <div class="emotion--wrapper"
                         data-controllerUrl="{url module=widgets controller=emotion action=index emotionId=$emotion.id secret=$previewSecret controllerName=$Controller}"
                         data-availableDevices="{$emotion.devices}"
                         data-showListing="{if $emotion.showListing == 1}true{else}false{/if}">
                    </div>
                {/block}
            </div>
        </div>
    {/block}
{/block}

{* hide right sidebar *}
{block name='frontend_index_content_right'}{/block}

{* hide last seen articles *}
{block name='frontend_index_left_last_articles'}{/block}

{* hide shop footer *}
{block name="frontend_index_footer"}{/block}