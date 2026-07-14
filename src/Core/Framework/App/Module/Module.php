<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Module;

use Shopware\Core\Framework\App\Feature\TranslatedString;
use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 *
 * @internal
 */
#[Package('framework')]
final readonly class Module
{
    public function __construct(
        public string $name,
        public TranslatedString $label,
        public ?string $parent,
        public ?string $source,
        public int $position,
    ) {
    }
}
