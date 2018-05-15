<?php declare(strict_types=1);

namespace Shopware\Application\Language\Struct;

use Shopware\Application\Language\Collection\LanguageBasicCollection;
use Shopware\System\Locale\Struct\LocaleBasicStruct;

class LanguageDetailStruct extends LanguageBasicStruct
{
    /**
     * @var LanguageBasicStruct|null
     */
    protected $parent;

    /**
     * @var LocaleBasicStruct
     */
    protected $locale;

    /**
     * @var LanguageBasicCollection
     */
    protected $children;

    public function __construct()
    {
        $this->children = new LanguageBasicCollection();
    }

    public function getParent(): ?LanguageBasicStruct
    {
        return $this->parent;
    }

    public function setParent(?LanguageBasicStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getChildren(): LanguageBasicCollection
    {
        return $this->children;
    }

    public function setChildren(LanguageBasicCollection $children): void
    {
        $this->children = $children;
    }
}
