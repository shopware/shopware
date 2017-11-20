<?php declare(strict_types=1);

namespace Shopware\Category\Event\CategoryTranslation;

use Shopware\Category\Collection\CategoryTranslationBasicCollection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Event\NestedEvent;

class CategoryTranslationBasicLoadedEvent extends NestedEvent
{
    const NAME = 'category_translation.basic.loaded';

    /**
     * @var TranslationContext
     */
    protected $context;

    /**
     * @var CategoryTranslationBasicCollection
     */
    protected $categoryTranslations;

    public function __construct(CategoryTranslationBasicCollection $categoryTranslations, TranslationContext $context)
    {
        $this->context = $context;
        $this->categoryTranslations = $categoryTranslations;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): TranslationContext
    {
        return $this->context;
    }

    public function getCategoryTranslations(): CategoryTranslationBasicCollection
    {
        return $this->categoryTranslations;
    }
}
