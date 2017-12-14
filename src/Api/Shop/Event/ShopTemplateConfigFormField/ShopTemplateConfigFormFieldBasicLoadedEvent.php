<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigFormField;

use Shopware\Api\Shop\Collection\ShopTemplateConfigFormFieldBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ShopTemplateConfigFormFieldBasicLoadedEvent extends NestedEvent
{
    const NAME = 'shop_template_config_form_field.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ShopTemplateConfigFormFieldBasicCollection
     */
    protected $shopTemplateConfigFormFields;

    public function __construct(ShopTemplateConfigFormFieldBasicCollection $shopTemplateConfigFormFields, TranslationContext $context)
    {
        $this->context = $context;
        $this->shopTemplateConfigFormFields = $shopTemplateConfigFormFields;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getShopTemplateConfigFormFields(): ShopTemplateConfigFormFieldBasicCollection
    {
        return $this->shopTemplateConfigFormFields;
    }
}
