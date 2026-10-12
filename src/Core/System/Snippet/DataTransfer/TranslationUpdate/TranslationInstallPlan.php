<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\DataTransfer\TranslationUpdate;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
final readonly class TranslationInstallPlan
{
    /**
     * @param list<string> $localesToDownload locales the repository has something newer for, or whose files are missing locally
     * @param list<string> $localesToLink locales whose files are current, so only the language and snippet set are ensured
     * @param list<string> $unavailableLocales locales the repository does not offer and that have no files on the filesystem
     */
    public function __construct(
        public array $localesToDownload = [],
        public array $localesToLink = [],
        public array $unavailableLocales = [],
    ) {
    }

    public function nothingCanBeInstalled(): bool
    {
        return $this->unavailableLocales !== []
            && $this->localesToDownload === []
            && $this->localesToLink === [];
    }
}
