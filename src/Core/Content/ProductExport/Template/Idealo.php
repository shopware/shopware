<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Template;

use Shopware\Core\Content\ProductExport\ProductExportEntity;

class Idealo extends AbstractTemplate
{
    public function __construct()
    {
        $this->name = 'idealo';
        $this->translationKey = 'sw-sales-channel.detail.productComparison.templates.template-label.idealo';
        $this->headerTemplate = '"Kategorie",{#- -#}
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
"Produkt Kurztext",{#- -#}
"Vorkasse Zuschlag",{#- Change or add your payment methods -#}
"Nachnahme Zuschlag",{#- Change or add your payment methods -#}
"Rechnung Zuschlag"{#- Change or add your payment methods -#}';
        $this->bodyTemplate = '"{{ product.categories.first.getBreadCrumb|slice(1)|join(\' > \')|raw }}",{#- -#}
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
"{{ productUrl(product) }}",{#- -#}
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
"0.00"{#- Change or add your payment methods -#}';
        $this->footerTemplate = '';
        $this->fileName = 'idealo.csv';
        $this->encoding = ProductExportEntity::ENCODING_UTF8;
        $this->fileFormat = ProductExportEntity::FILE_FORMAT_CSV;
        $this->generateByCronjob = false;
        $this->interval = 86400;
    }
}
