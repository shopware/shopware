<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Event\ShopTemplate;

use Shopware\Api\Shop\Collection\ShopTemplateBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class ShopTemplateBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'shop_template.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var ShopTemplateBasicCollection
     */
    protected $shopTemplates;

    public function __construct(ShopTemplateBasicCollection $shopTemplates, ApplicationContext $context)
    {
        $this->context = $context;
        $this->shopTemplates = $shopTemplates;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getShopTemplates(): ShopTemplateBasicCollection
    {
        return $this->shopTemplates;
    }
}
