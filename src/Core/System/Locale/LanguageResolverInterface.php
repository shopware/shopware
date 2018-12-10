<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

interface LanguageResolverInterface
{
    public function getRootLanguageIds(): array;

    public function isRootLanguage(string $identifier): bool;

    public function getLanguageIdByIdentifier(string $identifier): string;
}
