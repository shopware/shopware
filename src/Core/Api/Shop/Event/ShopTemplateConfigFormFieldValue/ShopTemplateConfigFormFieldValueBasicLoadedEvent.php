<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigFormFieldValue;

use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldValueBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ShopTemplateConfigFormFieldValueBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'shop_template_config_form_field_value.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ShopTemplateConfigFormFieldValueBasicCollection
     */
    protected $shopTemplateConfigFormFieldValues;

    public function __construct(ShopTemplateConfigFormFieldValueBasicCollection $shopTemplateConfigFormFieldValues, ApplicationContext $context)
    {
        $this->context = $context;
        $this->shopTemplateConfigFormFieldValues = $shopTemplateConfigFormFieldValues;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getShopTemplateConfigFormFieldValues(): ShopTemplateConfigFormFieldValueBasicCollection
    {
        return $this->shopTemplateConfigFormFieldValues;
    }
}
