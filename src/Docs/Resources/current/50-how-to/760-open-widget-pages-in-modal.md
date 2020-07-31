[titleEn]: <>(Open widget pages in modal)
[metaDescriptionEn]: <>(This HowTo will show you how to open a widget page)
[hash]: <>(article:how_to_widget_pages_in_modal)

## Overview

This guide will show you how to open any page in a modal.

## UrlModalPlugin

This javascript plugin is automatically registered onto any element matching the selector `[data-toggle="modal"][data-url]`.
It extends the already existing [Bootstrap feature](https://getbootstrap.com/docs/4.3/components/modal/#live-demo) how to work with modals by loading their content asynchronously and embed them later into a modal element.
All you need to open up e.g. the info widget page about checkout information in a modal is:

```html
<a class="btn btn-info"
   data-toggle="modal"
   data-url="{{ path('frontend.cms.page', { id: shopware.config.core.basicInformation.shippingPaymentInfoPage }) }}"
>
    Open checkout notes
</a>
```

The content of the loaded html does not need to ship styling or additional javascript as the storefront PluginManager is invoked and the HTML in the response is directly added to the DOM of the current page.
This way you can take advantage of the already loaded stylesheets and javascript and furthermore reduce the response to the content to be displayed.

## Additional configuration

When you want to apply further styles or scripts to the modal you can let it automatically get a class added to its main element.
This can be changed with either a plugin option or a data attribute:

```html
<a class="btn btn-info"
   data-toggle="modal"
   data-url="{{ path('frontend.cms.page', { id: shopware.config.core.basicInformation.shippingPaymentInfoPage }) }}"
   data-modal-class="fancy-class" 
>
    Open checkout notes
</a>
```
or
```html
<a class="btn btn-info"
   data-toggle="modal"
   data-url="{{ path('frontend.cms.page', { id: shopware.config.core.basicInformation.shippingPaymentInfoPage }) }}"
   data-url-modal-plugin-options='{{ { modalClass: "fancy-class" }|json_encode }}'
>
    Open checkout notes
</a>
```
