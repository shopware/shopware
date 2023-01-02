<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Framework\Log\Package;

/**
 * @phpstan-type LanguageData array<string, array{id: string, code: string, parentId: string}>
 */
#[Package('core')]
interface LanguageLoaderInterface
{
    /**
     * @return LanguageData
     */
    public function loadLanguages(): array;
}
