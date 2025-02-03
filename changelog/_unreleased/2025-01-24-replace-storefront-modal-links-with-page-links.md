---
title: Replace Storefront modal links with page links
issue: NEXT-40307
flag: ACCESSIBILITY_TWEAKS
author: Benedikt Schulze Baek
author_email: b.schulze-baek@shopware.com
author_github: @bschulzebaek
---
# Storefront
* Changed links loading content into modals to be native links loading the content as a new page. This affects the following templates 
  * `src/Storefront/Resources/views/storefront/component/buy-widget/buy-widget.html.twig`
  * `src/Storefront/Resources/views/storefront/component/privacy-notice.html.twig`
  * `src/Storefront/Resources/views/storefront/component/product/card/price-unit.html.twig`
  * `src/Storefront/Resources/views/storefront/element/cms-element-form/form-components/cms-element-form-privacy.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/cookie/cookie-configuration.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/cookie/cookie-permission.html.twig`
  * `src/Storefront/Resources/views/storefront/layout/footer/footer.html.twig`
  * `src/Storefront/Resources/views/storefront/page/account/order-history/order-detail.html.twig`
  * `src/Storefront/Resources/views/storefront/page/checkout/confirm/index.html.twig`
___
# Upgrade Information
To make internal links more accessible, we are replacing links that load content into modals with links loading the content as a new page. See above for a list of affected templates.
This is achieved by providing a new controller action (`Shopware\Storefront\Controller\CmsController::pageFull`), which renders CMS layouts as full pages instead of partial widgets. This is using the CMS layouts already assigned to Storefronts in the "Settings > Basic Information" module.

Generally, this change is replacing calls to the controller action `frontend.cms.page` with `frontend.cms.page.full` and replacing elements using the modal data attributes (see below) with native links. See the following examples for reference.

Note that in some cases link elements are part of snippets. In these cases, the snippet should be updated to use the new controller action and a native link as well. To avoid breaks with existing snippets, we added new snippets with a "Page" suffix, for example `general.privacyNoticeTextPage` instead of `general.privacyNoticeText`.

## Updated behavior
```html
<a href="{{ path('frontend.cms.page.full', { id: config('core.basicInformation.shippingPaymentInfoPage') }) }}">
    Shipping information
</a>
```

## Previous behavior

To keep the old behavior for a specific link, you can use the following markup.
Please keep in mind that modals are impacting accessibility by disrupting user flows and should be used mindfully. 

```html
<button data-ajax-modal="true"
        data-url="{{ path('frontend.cms.page', { id: config('core.basicInformation.shippingPaymentInfoPage') }) }}">
    Shipping information
</button>
```
