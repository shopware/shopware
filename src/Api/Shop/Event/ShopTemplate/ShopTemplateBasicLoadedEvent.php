<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplate;

use Shopware\Api\Shop\Collection\ShopTemplateBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class ShopTemplateBasicLoadedEvent extends NestedEvent
{
    const NAME = 'shop_template.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var ShopTemplateBasicCollection
     */
    protected $shopTemplates;

    public function __construct(ShopTemplateBasicCollection $shopTemplates, TranslationContext $context)
    {
        $this->context = $context;
        $this->shopTemplates = $shopTemplates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getShopTemplates(): ShopTemplateBasicCollection
    {
        return $this->shopTemplates;
    }
}
