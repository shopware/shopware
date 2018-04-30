{block name='frontend_widgets_captcha'}
    <div class="review--captcha">
        {block name='frontend_widgets_captcha_input_placeholder'}
            <div class="captcha--placeholder"><img src="data:image/png;base64,{$img}" alt="Captcha" /></div>
        {/block}

        {block name='frontend_widgets_captcha_input_label'}
            <strong class="captcha--notice">{s name="DetailCommentLabelCaptcha" namespace="frontend/detail/comment"}{/s}</strong>
        {/block}

        {block name='frontend_widgets_captcha_input_code'}
            <div class="captcha--code">
                <input type="text" name="sCaptcha" class="review--field{if $sErrorFlag.sCaptcha} has--error{/if}" required="required" aria-required="true"/>
                <input type="hidden" name="sRand" value="{$sRand}" />
            </div>
        {/block}
    </div>
{/block}