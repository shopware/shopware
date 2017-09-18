<?php declare(strict_types=1);

namespace Shopware\ShopTemplate\Event;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\ShopTemplate\Struct\ShopTemplateBasicCollection;

class ShopTemplateBasicLoadedEvent extends NestedEvent
{
    const NAME = 'shopTemplate.basic.loaded';

    /**
     * @var ShopTemplateBasicCollection
     */
    protected $shopTemplates;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(ShopTemplateBasicCollection $shopTemplates, TranslationContext $context)
    {
        $this->shopTemplates = $shopTemplates;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getShopTemplates(): ShopTemplateBasicCollection
    {
        return $this->shopTemplates;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
        ]);
    }
}
