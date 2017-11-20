<?php declare(strict_types=1);

namespace Shopware\Shop\Event\ShopTemplateConfigFormFieldValue;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Shop\Collection\ShopTemplateConfigFormFieldValueBasicCollection;

class ShopTemplateConfigFormFieldValueBasicLoadedEvent extends NestedEvent
{
    const NAME = 'shop_template_config_form_field_value.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ShopTemplateConfigFormFieldValueBasicCollection
     */
    protected $shopTemplateConfigFormFieldValues;

    public function __construct(ShopTemplateConfigFormFieldValueBasicCollection $shopTemplateConfigFormFieldValues, TranslationContext $context)
    {
        $this->context = $context;
        $this->shopTemplateConfigFormFieldValues = $shopTemplateConfigFormFieldValues;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getShopTemplateConfigFormFieldValues(): ShopTemplateConfigFormFieldValueBasicCollection
    {
        return $this->shopTemplateConfigFormFieldValues;
    }
}
