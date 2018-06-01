<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryTranslation\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\Collection\CategoryTranslationBasicCollection;
use Shopware\Core\Framework\Event\NestedEvent;

class CategoryTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'category_translation.basic.loaded';

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var CategoryTranslationBasicCollection
     */
    protected $categoryTranslations;

    public function __construct(CategoryTranslationBasicCollection $categoryTranslations, Context $context)
    {
        $this->context = $context;
        $this->categoryTranslations = $categoryTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    public function getCategoryTranslations(): CategoryTranslationBasicCollection
    {
        return $this->categoryTranslations;
    }
}
