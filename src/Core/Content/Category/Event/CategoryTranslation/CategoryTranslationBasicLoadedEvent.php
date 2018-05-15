<?php declare(strict_types=1);

namespace Shopware\Content\Category\Event\CategoryTranslation;

use Shopware\Content\Category\Collection\CategoryTranslationBasicCollection;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Event\NestedEvent;

class CategoryTranslationBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'category_translation.basic.loaded';

    /**
     * @var ApplicationContext
     */
    protected $context;

    /**
     * @var CategoryTranslationBasicCollection
     */
    protected $categoryTranslations;

    public function __construct(CategoryTranslationBasicCollection $categoryTranslations, ApplicationContext $context)
    {
        $this->context = $context;
        $this->categoryTranslations = $categoryTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ApplicationContext
    {
        return $this->context;
    }

    public function getCategoryTranslations(): CategoryTranslationBasicCollection
    {
        return $this->categoryTranslations;
    }
}
