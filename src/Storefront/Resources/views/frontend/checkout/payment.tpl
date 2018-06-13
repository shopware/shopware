{extends file="frontend/checkout/confirm.tpl"}

{block name='frontend_index_content_left'}
    {if !$theme.checkoutHeader}
        {$smarty.block.parent}
    {/if}
{/block}

{* Javascript *}
{block name="frontend_index_header_javascript" append}
<script type="text/javascript">
//<![CDATA[
    jQuery(document).ready(function($) {
        
        //$('#payment').removeClass('grid_18').removeClass('push_1');
        //$('#payment').addClass('grid_14').addClass('push_3');
        $('#payment_frame').css('display', 'none');
        $('#payment_loader').css('display', 'block');
        
        $('#payment_frame').load(function(){
            $('#payment_loader').css('display', 'none');
            $('#payment_frame').css('display', 'block');
            
            var window = $('#payment_frame')[0].contentWindow;
            if(window) {
                var height = window.document.body.offsetHeight;
                if(height>400) {
                    $('#payment_frame').css('height', height+'px');
                }
            }
        });
    });
//]]>
</script>
{/block}

{* Main content *}
{block name="frontend_index_content"}
<div id="payment" class="grid_20" style="margin:10px 0 10px 20px;width:959px;">

    <h2 class="headingbox_dark largesize">{s name="PaymentHeader"}{/s}</h2>
    <iframe id="payment_frame" width="100%" frameborder="0" border="0" src="{$sEmbedded}"></iframe>
    <div id="payment_loader" class="ajaxSlider" style="height:100px;border:0 none;display:none">
        <div class="loader" style="width:80px;margin-left:-50px;">{s name="PaymentInfoWait"}{/s}</div>
    </div>
    
</div>
<div class="doublespace">&nbsp;</div>
{/block}