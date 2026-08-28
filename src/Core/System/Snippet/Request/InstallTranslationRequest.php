<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Request;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
final class InstallTranslationRequest
{
    /**
     * @param list<string> $locales
     */
    public function __construct(
        public array $locales = [],
        public bool $all = false,
        public bool $activate = true,
    ) {
    }
}
