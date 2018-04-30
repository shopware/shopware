{block name="frontend_widgets_captcha_custom_captcha"}
        <div class="{if $captchaName !== 'honeypot' && $captchaName !== 'nocaptcha' }panel--body is--wide{/if}">

            {block name="frontend_widgets_captcha_custom_captcha_placeholder"}
                <div class="captcha--placeholder"
                    data-captcha="true"
                    data-src="{url module=widgets controller=Captcha action=getCaptchaByName captchaName=$captchaName}"
                    data-errorMessage="{s name="invalidCaptchaMessage" namespace="widgets/captcha/custom_captcha"}{/s}"
                    {if isset($captchaHasError) && count($captchaHasError) > 0}
                        data-hasError="true"
                    {/if}>
                </div>
            {/block}

            {block name="frontend_widgets_captcha_custom_captcha_hidden_input"}
                <input type="hidden" name="captchaName" value="{$captchaName}" />
            {/block}

        </div>
{/block}