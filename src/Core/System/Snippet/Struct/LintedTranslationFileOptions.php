<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\Command\LintTranslationFilesCommand;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
#[Package('discovery')]
readonly class LintedTranslationFileOptions
{
    /**
     * @param list<string> $extensionPaths
     * @param list<string> $ignoredPaths
     */
    private function __construct(
        public bool $isFix = false,
        public bool $isAll = false,
        public array $extensionPaths = [],
        public array $ignoredPaths = [],
        public ?string $dir = null,
    ) {
    }

    /**
     * @param InputInterface $input Expected to have options as defined in {@see LintTranslationFilesCommand}
     */
    public static function fromInputInterface(InputInterface $input): self
    {
        $extensions = (string) $input->getOption('extensions');
        $ignoredPaths = (string) $input->getOption('ignore');

        return new self(
            (bool) $input->getOption('fix'),
            (bool) $input->getOption('all'),
            !empty($extensions) ? explode(',', $extensions) : [],
            !empty($ignoredPaths) ? explode(',', $ignoredPaths) : [],
            (string) $input->getOption('dir') ?: null,
        );
    }
}
