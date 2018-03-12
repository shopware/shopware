<?php declare(strict_types=1);

namespace Shopware\Api\Language\Event\Language;

use Shopware\Api\Language\Collection\LanguageBasicCollection;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Event\NestedEvent;

class LanguageBasicLoadedEvent extends NestedEvent
{
    public const NAME = 'language.basic.loaded';

    /**
     * @var ShopContext
     */
    protected $context;

    /**
     * @var LanguageBasicCollection
     */
    protected $languages;

    public function __construct(LanguageBasicCollection $languages, ShopContext $context)
    {
        $this->context = $context;
        $this->languages = $languages;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): ShopContext
    {
        return $this->context;
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return $this->languages;
    }
}
