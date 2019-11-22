/* eslint-disable max-len */
Shopware.Service('exportTemplateService').registerProductExportTemplate({
    name: 'idealo',
    translationKey: 'sw-sales-channel.detail.productComparison.templates.template-label.idealo',
    headerTemplate: `
"categoryPath",{#- -#}
"brand",{#- -#}
"title",{#- -#}
"price",{#- -#}
"basePrice",{#- -#}
"hans",{#- -#}
"eans",{#- -#}
"deliveryCosts",{#- -#}
"url",{#- -#}
"delivery",{#- -#}
"sku",{#- -#}
"imageUrls",{#- -#}
"description",{#- -#}
"paymentCosts_CashInAdvance",{#- Change or add your payment methods -#}
"paymentCosts_CashOnDelivery",{#- Change or add your payment methods -#}
"paymentCosts_Invoice"{#- Change or add your payment methods -#}
        `.trim(),
    bodyTemplate: `
"{{ product.categories.first.getBreadCrumb|slice(1)|join(\' > \')|raw }}",{#- -#}
"{{ product.manufacturer.translated.name }}",{#- -#}
"{{ product.translated.name }}",{#- -#}
"{{ product.calculatedListingPrice.from.unitPrice|currency }}",{#- -#}
{% set price = product.calculatedListingPrice.from %}
"{% if price.referencePrice is not null %}
{{ price.referencePrice.price|currency }} / {{ price.referencePrice.referenceUnit }} {{ price.referencePrice.unitName }}{#- -#}
{% endif %}",{#- -#}
"{{ product.manufacturerNumber }}", {#- -#}
"{{ product.ean }}",{#- -#}
"{{ 4.95|currency }}",{#- Change to your delivery costs -#}
"{{ seoUrl(\'frontend.detail.page\', {\'productId\': product.id}) }}",{#- -#}
"{% if product.availableStock >= product.minPurchase and product.deliveryTime %}
{{ "detail.deliveryTimeAvailable"|trans({\'%name%\': product.deliveryTime.translation(\'name\')}) }}{#- -#}
{% elseif product.availableStock < product.minPurchase and product.deliveryTime and product.restockTime %}
{{ "detail.deliveryTimeRestock"|trans({\'%restockTime%\': product.restockTime,\'%name%\': product.deliveryTime.translation(\'name\')}) }}{#- -#}
{% else %}
{{ "detail.soldOut"|trans }}{#- -#}
{% endif %}",{#- -#}
"{{ product.productNumber }}",{#- -#}
"{{ product.cover.media.url }}",{#- -#}
"{{ product.translated.description|raw|length > 300 ? product.translated.description|raw|slice(0,300) ~ \'...\' : product.translated.description|raw }}",{#- -#}
"0.00",{#- Change or add your payment methods -#}
"0.00",{#- Change or add your payment methods -#}
"0.00"{#- Change or add your payment methods -#}
        `.trim(),
    footerTemplate: '',
    fileName: 'idealo.csv',
    encoding: 'UTF-8',
    fileFormat: 'csv',
    generateByCronjob: false,
    interval: 86400
});
