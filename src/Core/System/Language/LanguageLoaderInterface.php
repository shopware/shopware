<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

interface LanguageLoaderInterface
{
    public function loadLanguages(): array;
}
