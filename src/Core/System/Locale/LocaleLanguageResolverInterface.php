<?php declare(strict_types=1);

namespace Shopware\Core\System\Locale;

use Shopware\Core\Framework\Context;
use Shopware\Core\System\Exception\InvalidLocaleCodeException;

interface LocaleLanguageResolverInterface
{
    /**
     * @throws InvalidLocaleCodeException
     */
    public function getLanguageByLocale(string $localeCode, Context $context): ?string;

    public function invalidate(): void;
}
