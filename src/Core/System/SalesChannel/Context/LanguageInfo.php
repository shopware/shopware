<?php

declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Context;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
final readonly class LanguageInfo
{
    public function __construct(
        public string $name,
        public string $localeCode,
    ) {
    }
}
