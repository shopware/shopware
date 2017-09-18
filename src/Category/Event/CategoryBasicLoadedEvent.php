<?php declare(strict_types=1);

namespace Shopware\Category\Event;

use Shopware\Category\Struct\CategoryBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;
use Shopware\Framework\Event\NestedEventCollection;
use Shopware\SeoUrl\Event\SeoUrlBasicLoadedEvent;

class CategoryBasicLoadedEvent extends NestedEvent
{
    const NAME = 'category.basic.loaded';

    /**
     * @var CategoryBasicCollection
     */
    protected $categories;

    /**
     * @var TranslationContext
     */
    protected $context;

    public function __construct(CategoryBasicCollection $categories, TranslationContext $context)
    {
        $this->categories = $categories;
        $this->context = $context;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getCategories(): CategoryBasicCollection
    {
        return $this->categories;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getEvents(): ?NestedEventCollection
    {
        return new NestedEventCollection([
            new SeoUrlBasicLoadedEvent($this->categories->getCanonicalUrls(), $this->context),
        ]);
    }
}
