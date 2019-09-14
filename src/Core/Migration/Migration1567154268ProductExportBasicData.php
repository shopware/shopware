<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Content\ProductExport\ProductExportEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1567154268ProductExportBasicData extends MigrationStep
{
    /** @var string */
    private $deDeLanguage;

    /** @var string */
    private $idealoSalesChannelId;

    /** @var string */
    private $billigerSalesChannelId;

    /** @var string */
    private $googleSalesChannelId;

    /** @var string */
    private $productStreamId;

    public function getCreationTimestamp(): int
    {
        return 1567154268;
    }

    public function update(Connection $connection): void
    {
        $this->createSalesChannelType($connection);
        $this->createSalesChannel($connection);
        $this->createProductStream($connection);
        $this->createProductExport($connection);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function createProductStream(Connection $connection): void
    {
        $this->productStreamId = Uuid::randomBytes();
        $apiFilterJson = '[{"type": "multi", "queries": [{"type": "multi", "queries": [{"type": "equals", "field": "product.active", "value": "1"}, {"type": "range", "field": "product.stock", "parameters": {"gt": 0}}], "operator": "AND"}], "operator": "OR"}]';

        $connection->insert(
            'product_stream',
            [
                'id' => $this->productStreamId,
                'api_filter' => $apiFilterJson,
                'invalid' => 0,
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'product_stream_translation',
            [
                'product_stream_id' => $this->productStreamId,
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Active and instock products',
                'description' => 'Contains all active and instock products',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $rootFilterId = Uuid::randomBytes();
        $multiFilterId = Uuid::randomBytes();

        $connection->insert(
            'product_stream_filter',
            [
                'id' => $rootFilterId,
                'product_stream_id' => $this->productStreamId,
                'type' => 'multi',
                'operator' => 'OR',
                'position' => 0,
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
        $connection->insert(
            'product_stream_filter',
            [
                'id' => $multiFilterId,
                'product_stream_id' => $this->productStreamId,
                'parent_id' => $rootFilterId,
                'type' => 'multi',
                'operator' => 'AND',
                'position' => 0,
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
        $connection->insert(
            'product_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'product_stream_id' => $this->productStreamId,
                'parent_id' => $multiFilterId,
                'type' => 'equals',
                'field' => 'active',
                'value' => '1',
                'position' => 1,
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
        $connection->insert(
            'product_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'product_stream_id' => $this->productStreamId,
                'parent_id' => $multiFilterId,
                'type' => 'range',
                'field' => 'stock',
                'parameters' => '{"gt":0}',
                'position' => 1,
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function createProductExport(Connection $connection): void
    {
        $connection->insert(
            'product_export',
            [
                'id' => Uuid::randomBytes(),
                'product_stream_id' => $this->productStreamId,
                'sales_channel_id' => $this->idealoSalesChannelId,
                'file_name' => 'idealo.csv',
                'access_key' => AccessKeyHelper::generateAccessKey('product-export'),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'file_format' => ProductExportEntity::FILE_FORMAT_CSV,
                'generate_by_cronjob' => 0,
                '`interval`' => 86400,
                'header_template' => '"Kategorie",{#- -#}
"Hersteller",{#- -#}
"Produktbezeichnung",{#- -#}
"Preis",{#- -#}
"Grundpreis",{#- -#}
"Hersteller-Artikelnummer",{#- -#}
"EAN",{#- -#}
"Versandkosten",{#- -#}
"Deeplink",{#- -#}
"Lieferzeit",{#- -#}
"Artikelnummer",{#- -#}
"Link Produktbild",{#- -#}
vProdukt Kurztext",{#- -#}
"Vorkasse Zuschlag",{#- Change or add your payment methods -#}
"Nachnahme Zuschlag",{#- Change or add your payment methods -#}
"Rechnung Zuschlag"{#- Change or add your payment methods -#}',

                'body_template' => '"{{ product.categories.first.getBreadCrumb|slice(1)|join(\' > \')|raw }}",{#- -#}
"{{ product.manufacturer.translated.name }}",{#- -#}
"{{ product.translated.name }}",{#- -#}
"{{ product.calculatedPrice.unitPrice|currency }}",{#- -#}
{% set price = product.calculatedPrice %}
{% if product.calculatedPrices|length == 1 %}
    {% set price = product.calculatedPrices.first %}
{% endif %}
"{% if price.referencePrice is not null %}
{{ price.referencePrice.price|currency }} / {{ price.referencePrice.referenceUnit }} {{ price.referencePrice.unitName }}{#- -#}
{% endif %}",{#- -#}
"{{ product.productNumber }}", {#- -#}
"{{ product.ean }}",{#- -#}
"{{ 4.90|currency }}",{#- Change to your delivery costs -#}
"{{ productUrl(product) }}",{#- -#}
"{% if product.availableStock >= product.minPurchase and product.deliveryTime %}
{{ "detail.deliveryTimeAvailable"|trans({\'%name%\': product.deliveryTime.translation(\'name\')}) }}{#- -#}
{% elseif product.availableStock < product.minPurchase and product.deliveryTime and product.restockTime %}
{{ "detail.deliveryTimeRestock"|trans({\'%restockTime%\': product.restockTime,\'%name%\': product.deliveryTime.translation(\'name\')}) }}{#- -#}
{% else %}
{{ "detail.soldOut"|trans }}{#- -#}
{% endif %}",{#- -#}
"{{ product.productNumber }}",{#- -#}
"{{ product.media|first.media.url }}",{#- -#}
"{{ product.translated.description|raw|length > 300 ? product.translated.description|raw|slice(0,300) ~ \'...\' : product.translated.description|raw }}",{#- -#}
"0.00",{#- Change or add your payment methods -#}
"0.00",{#- Change or add your payment methods -#}
"0.00"{#- Change or add your payment methods -#}',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'product_export',
            [
                'id' => Uuid::randomBytes(),
                'product_stream_id' => $this->productStreamId,
                'sales_channel_id' => $this->billigerSalesChannelId,
                'file_name' => 'billiger.csv',
                'access_key' => AccessKeyHelper::generateAccessKey('product-export'),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'file_format' => ProductExportEntity::FILE_FORMAT_CSV,
                'generate_by_cronjob' => 0,
                '`interval`' => 86400,
                'header_template' => 'aid,{#- -#}
brand,{#- -#}
mpnr,{#- -#}
ean,{#- -#}
name,{#- -#}
desc,{#- -#}
shop_cat,{#- -#}
price,{#- -#}
ppu,{#- -#}
link,{#- -#}
image,{#- -#}
dlv_time,{#- -#}
dlv_cost,{#- -#}
pzn,{#- -#}
unit_pricing_measure,{#- -#}
unit_pricing_base_measure,{#- -#}
target_url,{#- -#}
images{#- -#}',

                'body_template' => '"{{ product.productNumber }}",{#- -#}
"{{ product.manufacturer.translated.name }}",{#- -#}
"{{ product.manufacturer.id }}",{#- -#}
"{{ product.ean }}",{#- -#}
"{{ product.translated.name|length > 80 ? product.translated.name|slice(0, 80) ~ \'...\' : product.translated.name }}",{#- -#}
"{{ product.translated.description|raw|length > 900 ? product.translated.description|raw|slice(0,900) ~ \'...\' : product.translated.description|raw }}{#- -#}
",{#- -#}
"{{ product.categories.first.getBreadCrumb|slice(1)|join(\' > \')|raw }}",{#- -#}
"{{ product.calculatedPrice.unitPrice|currency }}",{#- -#}
{% set price = product.calculatedPrice %}
{% if product.calculatedPrices|length == 1 %}
    {% set price = product.calculatedPrices.first %}
{% endif %}
"{% if price.referencePrice is not null %}
{{ price.referencePrice.price|currency }} / {{ price.referencePrice.referenceUnit }} {{ price.referencePrice.unitName }}{#- -#}
{% endif %}",{#- -#}
"{{ productUrl(product) }}",{#- -#}
"{{ product.media|first.media.url }}",{#- -#}
"{% if product.availableStock >= product.minPurchase and product.deliveryTime %}
{{ "detail.deliveryTimeAvailable"|trans({\'%name%\': product.deliveryTime.translation(\'name\')}) }}{#- -#}
{% elseif product.availableStock < product.minPurchase and product.deliveryTime and product.restockTime %}
{{ "detail.deliveryTimeRestock"|trans({\'%restockTime%\': product.restockTime,\'%name%\': product.deliveryTime.translation(\'name\')}) }}{#- -#}
{% else %}
{{ "detail.soldOut"|trans }}{#- -#}
{% endif %}",{#- -#}
"{{ 4.95|currency }}",{# change your default delivery costs #}{#- -#}
,{#- -#}
"{% if product.purchaseUnit %}{{ product.purchaseUnit }}{{ product.unit.shortCode }}{% endif %}",{#- -#}
"{% if product.referenceUnit %}{{ product.referenceUnit }}{{ product.unit.shortCode }}{% endif %}",{#- -#}
"{{ productUrl(product) }}",{#- -#}
{% if product.media|length > 1 %}"{% for mediaAssociation in product.media|slice(0, 5) %}{{ mediaAssociation.media.url }}{% if not loop.last %};{% endif %}{% endfor %}"{% endif %}{#- -#}',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'product_export',
            [
                'id' => Uuid::randomBytes(),
                'product_stream_id' => $this->productStreamId,
                'sales_channel_id' => $this->googleSalesChannelId,
                'file_name' => 'google.xml',
                'access_key' => AccessKeyHelper::generateAccessKey('product-export'),
                'encoding' => ProductExportEntity::ENCODING_UTF8,
                'file_format' => ProductExportEntity::FILE_FORMAT_XML,
                'generate_by_cronjob' => 0,
                '`interval`' => 86400,
                'header_template' => '<?xml version="1.0" encoding="UTF-8" ?>
<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
	<atom:link href="{{ productExport.salesChannelDomain.url }}/export/{{ productExport.accessKey }}/{{ productExport.fileName }}" rel="self" type="application/rss+xml" />
	<title>{{ context.salesChannel.name }}</title>
	<description>Add your description</description>
	<link>{{ productExport.salesChannelDomain.url }}</link>
	<language>{{ productExport.salesChannelDomain.language.locale.code }}</language>
	<image>
		<url>Add your logo</url>
		<title>{{ context.salesChannel.name }}</title>
		<link>{{ productExport.salesChannelDomain.url }}</link>
	</image>',

                'body_template' => '<item> 
    <g:id>{{ product.id }}</g:id>
	<title>{{ product.translated.name|escape }}</title>
	<description>{{ product.translated.description|escape }}</description>
	<g:google_product_category>Select your google category</g:google_product_category>
	<g:product_type>{{ product.categories.first.getBreadCrumb|slice(1)|join(\' > \')|raw|escape }}</g:product_type>
	<link>{{ productUrl(product) }}</link>
	<g:image_link>{{ product.media|first.media.url }}</g:image_link>
	<g:condition>neu</g:condition>
	<g:availability>{% if product.availableStock >= product.minPurchase and product.deliveryTime %}bestellbar{% elseif product.availableStock < product.minPurchase and product.deliveryTime and product.restockTime %}vorbestellt{% else %}nicht auf lager{% endif %}</g:availability>
	<g:price>{{ product.calculatedPrice.unitPrice|currency }}</g:price>
	<g:brand>{{ product.manufacturer.translated.name|escape }}</g:brand>
	<g:gtin>{{ product.manufacturerNumber }}</g:gtin>
	<g:mpn>{{ product.manufacturerNumber }}</g:mpn>
	<g:shipping>
       <g:country>DE</g:country>
       <g:service>Standard</g:service>
       <g:price>Enter your shipping costs</g:price>
    </g:shipping>
  {% if product.updatedAt %}<pubDate>{{ product.updatedAt|date("%a, %d %b %Y %T %Z") }}</pubDate>{% endif %}		
</item>',
                'footer_template' => '</channel>
</rss>',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function createSalesChannelType(Connection $connection): void
    {
        $salesChannelTypeId = Uuid::fromHexToBytes(Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON);

        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDE = $this->getDeDeLanguageId($connection);

        $connection->insert(
            'sales_channel_type',
            [
                'id' => $salesChannelTypeId,
                'icon_name' => 'default-object-rocket',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
        $connection->insert(
            'sales_channel_type_translation',
            [
                'sales_channel_type_id' => $salesChannelTypeId,
                'language_id' => $languageEN,
                'name' => 'Product comparison',
                'manufacturer' => 'shopware AG',
                'description' => 'Sales channel for product comparison platforms',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
        $connection->insert(
            'sales_channel_type_translation',
            [
                'sales_channel_type_id' => $salesChannelTypeId,
                'language_id' => $languageDE,
                'name' => 'Produktvergleich',
                'manufacturer' => 'shopware AG',
                'description' => 'Sales channel für Produktvergleich Portale',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
    }

    private function createSalesChannel(Connection $connection): void
    {
        $defaultPaymentMethod = $connection->executeQuery(
            'SELECT id FROM payment_method WHERE active = 1 ORDER BY `position`'
        )->fetchColumn();
        $defaultShippingMethod = $connection->executeQuery(
            'SELECT id FROM shipping_method WHERE active = 1'
        )->fetchColumn();
        $countryStatement = $connection->executeQuery(
            'SELECT id FROM country WHERE active = 1 ORDER BY `position`'
        );
        $defaultCountry = $countryStatement->fetchColumn();
        $rootCategoryId = $connection->executeQuery('SELECT id FROM category')->fetchColumn();

        $this->idealoSalesChannelId = Uuid::randomBytes();
        $this->billigerSalesChannelId = Uuid::randomBytes();
        $this->googleSalesChannelId = Uuid::randomBytes();
        $languageEN = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $languageDE = $this->getDeDeLanguageId($connection);

        $salesChannelDefault = [
            'type_id' => Uuid::fromHexToBytes(
                Defaults::SALES_CHANNEL_TYPE_PRODUCT_COMPARISON
            ),
            'active' => 1,
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'currency_id' => Uuid::fromHexToBytes(Defaults::CURRENCY),
            'payment_method_id' => $defaultPaymentMethod,
            'shipping_method_id' => $defaultShippingMethod,
            'country_id' => $defaultCountry,
            'navigation_category_id' => $rootCategoryId,
            'navigation_category_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            'customer_group_id' => Uuid::fromHexToBytes(Defaults::FALLBACK_CUSTOMER_GROUP),
            'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
        ];

        $connection->insert(
            'sales_channel',
            array_merge(
                $salesChannelDefault,
                [
                    'id' => $this->idealoSalesChannelId,
                    'access_key' => AccessKeyHelper::generateAccessKey('sales-channel'),
                ]
            )
        );
        $connection->insert(
            'sales_channel',
            array_merge(
                $salesChannelDefault,
                [
                    'id' => $this->billigerSalesChannelId,
                    'access_key' => AccessKeyHelper::generateAccessKey('sales-channel'),
                ]
            )
        );
        $connection->insert(
            'sales_channel',
            array_merge(
                $salesChannelDefault,
                [
                    'id' => $this->googleSalesChannelId,
                    'access_key' => AccessKeyHelper::generateAccessKey('sales-channel'),
                ]
            )
        );

        $connection->insert(
            'sales_channel_translation',
            [
                'sales_channel_id' => $this->idealoSalesChannelId,
                'language_id' => $languageEN,
                'name' => 'idealo.de',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
        $connection->insert(
            'sales_channel_translation',
            [
                'sales_channel_id' => $this->idealoSalesChannelId,
                'language_id' => $languageDE,
                'name' => 'idealo.de',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'sales_channel_translation',
            [
                'sales_channel_id' => $this->billigerSalesChannelId,
                'language_id' => $languageEN,
                'name' => 'billiger.de',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
        $connection->insert(
            'sales_channel_translation',
            [
                'sales_channel_id' => $this->billigerSalesChannelId,
                'language_id' => $languageDE,
                'name' => 'billiger.de',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $connection->insert(
            'sales_channel_translation',
            [
                'sales_channel_id' => $this->googleSalesChannelId,
                'language_id' => $languageEN,
                'name' => 'Google product search',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );
        $connection->insert(
            'sales_channel_translation',
            [
                'sales_channel_id' => $this->googleSalesChannelId,
                'language_id' => $languageDE,
                'name' => 'Google Produktsuche',
                'created_at' => date(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->insertAdditionalSalesChannelData($connection, $this->idealoSalesChannelId);
        $this->insertAdditionalSalesChannelData($connection, $this->billigerSalesChannelId);
        $this->insertAdditionalSalesChannelData($connection, $this->googleSalesChannelId);
    }

    private function insertAdditionalSalesChannelData(Connection $connection, string $salesChannelId): void
    {
        $currencies = $connection->executeQuery('SELECT id FROM currency')->fetchAll(FetchMode::COLUMN);
        $languages = $connection->executeQuery('SELECT id FROM language')->fetchAll(FetchMode::COLUMN);
        $shippingMethods = $connection->executeQuery('SELECT id FROM shipping_method')->fetchAll(
            FetchMode::COLUMN
        );
        $paymentMethods = $connection->executeQuery('SELECT id FROM payment_method')->fetchAll(
            FetchMode::COLUMN
        );
        $countryStatement = $connection->executeQuery(
            'SELECT id FROM country WHERE active = 1 ORDER BY `position`'
        );
        $defaultCountry = $countryStatement->fetchColumn();

        // country
        $connection->insert(
            'sales_channel_country',
            ['sales_channel_id' => $salesChannelId, 'country_id' => $defaultCountry]
        );
        $connection->insert(
            'sales_channel_country',
            ['sales_channel_id' => $salesChannelId, 'country_id' => $countryStatement->fetchColumn()]
        );

        // currency
        foreach ($currencies as $currency) {
            $connection->insert(
                'sales_channel_currency',
                ['sales_channel_id' => $salesChannelId, 'currency_id' => $currency]
            );
        }

        // language
        foreach ($languages as $language) {
            $connection->insert(
                'sales_channel_language',
                ['sales_channel_id' => $salesChannelId, 'language_id' => $language]
            );
        }

        // shipping methods
        foreach ($shippingMethods as $shippingMethod) {
            $connection->insert(
                'sales_channel_shipping_method',
                ['sales_channel_id' => $salesChannelId, 'shipping_method_id' => $shippingMethod]
            );
        }

        // payment methods
        foreach ($paymentMethods as $paymentMethod) {
            $connection->insert(
                'sales_channel_payment_method',
                ['sales_channel_id' => $salesChannelId, 'payment_method_id' => $paymentMethod]
            );
        }
    }

    private function getDeDeLanguageId(Connection $connection): string
    {
        if ($this->deDeLanguage === null) {
            $this->deDeLanguage = $connection->fetchColumn(
                'SELECT id FROM language WHERE id != :default',
                ['default' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM)]
            );
        }

        return $this->deDeLanguage;
    }
}
