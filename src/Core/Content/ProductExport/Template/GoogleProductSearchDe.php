<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Template;

use Shopware\Core\Content\ProductExport\ProductExportEntity;

class GoogleProductSearchDe extends AbstractTemplate
{
    public function __construct()
    {
        $this->name = 'google-product-search-de';
        $this->translationKey = 'sw-sales-channel.detail.productComparison.template-label.google-product-search-de';
        $this->headerTemplate = '<?xml version="1.0" encoding="UTF-8" ?>
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
	</image>';

        $this->bodyTemplate = '<item> 
	<g:id>{{ product.id }}</g:id>
	<title>{{ product.translated.name|escape }}</title>
	<description>{{ product.translated.description|escape }}</description>
	<g:google_product_category>950{# change your Google Shopping category #}</g:google_product_category>
	<g:product_type>{{ product.categories.first.getBreadCrumb|slice(1)|join(\' > \')|raw|escape }}</g:product_type>
	<link>{{ productUrl(product) }}</link>
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
</item>';
        $this->footerTemplate = '</channel>
</rss>';
        $this->fileName = 'google.xml';
        $this->encoding = ProductExportEntity::ENCODING_UTF8;
        $this->fileFormat = ProductExportEntity::FILE_FORMAT_XML;
        $this->generateByCronjob = false;
        $this->interval = 86400;
    }
}
