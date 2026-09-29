<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\DataTransfer\TranslationUpdate;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class TranslationUpdateResult
{
    /**
     * @param list<string> $updated locales whose translations were downloaded and persisted
     * @param list<string> $skipped locales that were already up to date
     */
    public function __construct(
        public array $updated = [],
        public array $skipped = [],
    ) {
    }
}
