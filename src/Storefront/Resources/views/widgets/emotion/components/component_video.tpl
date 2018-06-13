{block name="widget_emotion_component_video_container"}
    <div class="emotion--video"
         {if $Data.videoMode} data-mode="{$Data.videoMode}"{/if}
         {if $Data.originTop} data-scaleOriginX="{$Data.originTop}"{/if}
         {if $Data.originLeft} data-scaleOriginY="{$Data.originLeft}"{/if}
         {if $Data.scale} data-scale="{$Data.scale}"{/if}>

        {block name="widget_emotion_component_video_element"}
            {strip}
            <video class="video--element"
                   poster=""
                   {if $Data.autobuffer} preload{/if}
                   {if $Data.autoplay} autoplay{/if}
                   {if $Data.loop} loop{/if}
                   {if $Data.controls} controls{/if}
                   {if $Data.muted} muted{/if}>
                <source src="{link file=$Data.webm_video}" type="video/webm" />
                <source src="{link file=$Data.h264_video}" type="video/mp4" />
                <source src="{link file=$Data.ogg_video}" type="video/ogg" />
            </video>
            {/strip}
        {/block}

        {block name="widget_emotion_component_video_cover_image"}
            {if $Data.fallback_picture}
                <a href="#play-video"
                   class="video--cover"
                   style="background-image: url('{link file=$Data.fallback_picture}');">
                    <i class="video--play-icon icon--play"></i>
                </a>
            {/if}
        {/block}

        {block name="widget_emotion_component_video_play_button"}
            {if !$Data.controls}
                <a href="#play-video"
                   class="video--play-btn"
                   data-playIconCls="icon--play"
                   data-pauseIconCls="icon--pause">
                    <i class="video--play-icon icon--play"></i>
                </a>
            {/if}
        {/block}

        {block name="widget_emotion_component_video_text"}
            {if $Data.html_text}
                <div class="video--text{if $Data.controls} no--events{/if}"
                    {if $Data.overlay} style="background: {$Data.overlay}"{/if}>
                    {$Data.html_text}
                </div>
            {/if}
        {/block}
    </div>
{/block}
