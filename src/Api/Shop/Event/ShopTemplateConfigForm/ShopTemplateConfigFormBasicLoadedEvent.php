<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplateConfigForm;

use Shopware\Api\Shop\Collection\ShopTemplateConfigFormBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ShopTemplateConfigFormBasicLoadedEvent extends NestedEvent
{
    const NAME = 'shop_template_config_form.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ShopTemplateConfigFormBasicCollection
     */
    protected $shopTemplateConfigForms;

    public function __construct(ShopTemplateConfigFormBasicCollection $shopTemplateConfigForms, TranslationContext $context)
    {
        $this->context = $context;
        $this->shopTemplateConfigForms = $shopTemplateConfigForms;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getShopTemplateConfigForms(): ShopTemplateConfigFormBasicCollection
    {
        return $this->shopTemplateConfigForms;
    }
}
