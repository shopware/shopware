<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Struct;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\Snippet\Command\ValidateTranslationFilesCommand;
use Symfony\Component\Console\Input\InputInterface;

/**
 * @internal
 */
#[Package('discovery')]
class ValidatedTranslationFileOptions extends Struct
{
    /**
     * @param list<string> $extensionPaths
     * @param list<string> $ignoredPaths
     */
    public function __construct(
        protected bool $fix = false,
        protected bool $all = false,
        protected array $extensionPaths = [],
        protected array $ignoredPaths = [],
        protected ?string $dir = null,
    ) {
    }

    public function isFix(): bool
    {
        return $this->fix;
    }

    public function isAll(): bool
    {
        return $this->all;
    }

    /**
     * @return list<string>
     */
    public function getExtensionPaths(): array
    {
        return $this->extensionPaths;
    }

    /**
     * @return list<string>
     */
    public function getIgnoredPaths(): array
    {
        return $this->ignoredPaths;
    }

    public function getDir(): ?string
    {
        return $this->dir;
    }

    /**
     * @param InputInterface $input Expected to have options as defined in {@see ValidateTranslationFilesCommand}
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
