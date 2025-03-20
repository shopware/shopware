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
    /**
     * @param non-empty-list<string> $chain
     */
    public function __construct(
        public string $id,
        public string $name,
        public array $chain,
        public string $localeId,
        public string $localeCode,
    ) {
    }
}
