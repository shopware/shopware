<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Language;

interface LanguageLoaderInterface
{
    public function loadLanguages(): array;
}
