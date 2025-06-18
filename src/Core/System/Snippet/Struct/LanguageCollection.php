<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Collection;

/**
 * @internal
 *
 * @extends Collection<Language>
 */
#[Package('discovery')]
class LanguageCollection extends Collection
{
    /**
     * @param list<Language> $elements
     */
    public function __construct(
        array $elements,
    ) {
        $indexed = [];
        foreach ($elements as $element) {
            $indexed[$element->locale] = $element;
        }

        parent::__construct($indexed);
    }

    protected function getExpectedClass(): string
    {
        return Language::class;
    }
}
