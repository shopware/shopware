/* eslint-disable max-len */
Shopware.Service('exportTemplateService').registerProductExportTemplate({
    name: 'google-product-search-de',
    translationKey: 'sw-sales-channel.detail.productComparison.templates.template-label.google-product-search-de',
    headerTemplate: `
<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
    <atom:link href="{{ productExport.salesChannelDomain.url }}/export/{{ productExport.accessKey }}/{{ productExport.fileName }}" rel="self" type="application/rss+xml" />
    <title>{{ context.salesChannel.name }}</title>
    <description>{# change your shop\'s description #}</description>
    <link>{{ productExport.salesChannelDomain.url }}</link>
    <language>{{ productExport.salesChannelDomain.language.locale.code }}</language>
    <image>
        <url>{# add your logo URL #}</url>
        <title>{{ context.salesChannel.name }}</title>
        <link>{{ productExport.salesChannelDomain.url }}</link>
    </image>
        `.trim(),
    bodyTemplate: `    <item> 
        <g:id>{{ product.id }}</g:id>
        <title>{{ product.translated.name|escape }}</title>
        <description>{{ product.translated.description|escape }}</description>
        <g:google_product_category>950{# change your Google Shopping category #}</g:google_product_category>
        <g:product_type>{{ product.categories.first.getBreadCrumb|slice(1)|join(\' > \')|raw|escape }}</g:product_type>
        <link>{{ seoUrl(\'frontend.detail.page\', {\'productId\': product.id}) }}</link>
        <g:image_link>{{ product.cover.media.url }}</g:image_link>
        <g:condition>neu</g:condition>
        <g:availability>{% if product.availableStock >= product.minPurchase and product.deliveryTime %}bestellbar{% elseif product.availableStock < product.minPurchase and product.deliveryTime and product.restockTime %}vorbestellt{% else %}nicht auf lager{% endif %}</g:availability>
        <g:price>{{ product.calculatedListingPrice.from.unitPrice|currency }}</g:price>
        <g:brand>{{ product.manufacturer.translated.name|escape }}</g:brand>
        <g:gtin>{{ product.manufacturerNumber }}</g:gtin>
        <g:mpn>{{ product.manufacturerNumber }}</g:mpn>
        <g:shipping>
            <g:country>DE</g:country>
            <g:service>Standard</g:service>
            <g:price>{{ 4.95|currency }}{# change your default delivery costs #}</g:price>
        </g:shipping>
        {% if product.updatedAt %}<pubDate>{{ product.updatedAt|date("%a, %d %b %Y %T %Z") }}</pubDate>{% endif %}
    </item>`,
    footerTemplate: `
</channel>
</rss>
    `.trim(),
    fileName: 'google.xml',
    encoding: 'UTF-8',
    fileFormat: 'xml',
    generateByCronjob: false,
    interval: 86400
});
