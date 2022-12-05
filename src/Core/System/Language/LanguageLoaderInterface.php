<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

/**
 * @package system-settings
 * @phpstan-type LanguageData array<string, array{id: string, code: string, parentId: string}>
 */
interface LanguageLoaderInterface
{
    /**
     * @return LanguageData
     */
    public function loadLanguages(): array;
}
