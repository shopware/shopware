[titleEn]: <>(Creating a sales channel)
[titleDe]: <>(Creating a sales channel)
[wikiUrl]: <>(../plugin-system/saleschannel?category=shopware-platform-en/plugin-system)

Sales channels are grouped by sales channel types.
Let's assume, that you want to create a `language assistant` sales channel.
Your `language assistant` sales channel could be grouped by a `Voice Commerce` sales channel type.
This way, you could easily add several `language assistant` sales channels from different manufacturers.
First, add a new sales channel type and create a sales channel of that new type, while the installation of your plugin.

## Installation
```php
<?php declare(strict_types=1);

namespace SwagVoiceCommerce;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Util\AccessKeyHelper;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Context\InstallContext;

class SwagVoiceCommerce extends Plugin
{
    private const SALES_CHANNEL_TYPE_NAME = 'Voice Commerce';
    private const VOICE_COMMERCE_TYPE_ID = 'c8756f49a8a234ab2222f4a13f3e81ed';
    private const VOICE_COMMERCE_SALES_CHANNEL_ID = 'ff516f6ba5a234ab242f4a12f3b21eff';

    public function install(InstallContext $context): void
    {
        $this->addSalesChannelType($context->getContext());
        $this->addSalesChannel($context->getContext());
    }

    private function addSalesChannel( Context $context)
    {
        $salesChannelRepo = $this->container->get('sales_channel.repository');

        $salesChannel = [
            'id' => self::VOICE_COMMERCE_SALES_CHANNEL_ID,
            'typeId' => self::VOICE_COMMERCE_TYPE_ID,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'paymentMethodId' => Defaults::PAYMENT_METHOD_DEBIT,
            'shippingMethodId' => Defaults::SHIPPING_METHOD,
            'countryId' => Defaults::COUNTRY,
            'name' => 'Language Assistant Sales Channel',
            'active' => true,
            'taxCalculationType' => 'vertical',
            'accessKey' => AccessKeyHelper::generateAccessKey('sales-channel'),
        ];

        $salesChannelRepo->create([$salesChannel], $context);
    }

    private function addSalesChannelType(Context $context): void 
    {
        $salesChannelTypeRepository = $this->container->get('sales_channel_type.repository');

        $salesChannelType = [
            'id' => self::VOICE_COMMERCE_TYPE_ID,
            'iconName' => 'default-communication-speech-bubble',
            'name' => self::SALES_CHANNEL_TYPE_NAME,
            'description' => 'Voice commerce sales channel'
        ];

        $salesChannelTypeRepository->create([$salesChannelType], $context);
    }
    ...
}
```

## Sales channel type
Prepare an array with the necessary data for the new sales channel type.

| array key       | default value | used to |
|-----------------|---------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| id              | required      | Unique identifier for your new sales channel type. You should generate your own UUID and save it as constant in your plugin, so you could easily access your sales channel type |
| coverUrl        | `NULL`        | Display a cover on the detail page of your sales channel type |
| screenshotUrls  | `NULL`        | Display screenshots on the detail page of your sales channel type |
| iconName        | `NULL`        | Display an icon representing your sales channel type |
| name            | `NULL`        | Name of your sales channel type |
| description     | `NULL`        | Display a short description of your sales channel type |
| descriptionLong | `NULL`        | Display a long description of your sales channel type |

Use the sales channel type entity repository to create your new sales channel type.
If you use the `upsert()` instead of `create()` method the sales channel type will be updated if it already exists.
So you could use this method also on updates of your plugin.
But note, that this will only work if you set the ID by yourself.

## Sales channel
Prepare an array with the necessary data for the new sales channel.
Note that you need the id of your sales channel type to assign the sales channel to it.

| array key          | default value              | used to |
|--------------------|----------------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| id                 | required                   | Unique identifier for your new sales channel. You should generate your own UUID and save it as constant in your plugin, so you could easily access your sales channel |
| languageId         | required                   | Primary language for your sales channel |
| currencyId         | required                   | Primary currency for your sales channel |
| paymentMethodId    | required                   | Primary payment method for your sales channel |
| shippingMethodId   | required                   | Primary shipping method for your sales channel |
| countryId          | required                   | Primary country for your sales channel |
| accessKey          | required                   | Prevent unauthorized use of your sales channel |
| typeId             | required/or provide type   | Set's the type of your sales channel |
| type               | required/or provide typeId | Set's the type of your sales channel |
| name               | `NULL`                     | Name your sales channel|
| configuration      | `NULL`                     | Set the configuration of your sales channel|
| active             |`false`                     | Determines if your sales channel is active or not|
| taxCalculationType | `vertical`                 | Set `horizontal` or `vertical` tax calculation for your sales channel|

Use the sales channel entity repository to create your new sales channel.
If you use the `upsert()` method the sales channel will be updated if it already exists.
So you could use this method also on updates of your plugin.
But note, that this will only work if you set the ID by yourself.