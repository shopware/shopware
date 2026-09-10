<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Framework\Log\Package;

#[Package('discovery')]
class FileUrlValidator implements FileUrlValidatorInterface
{
    private readonly TrustedUrlResolver $resolver;

    /**
     * @internal
     */
    public function __construct(?TrustedUrlResolver $resolver = null)
    {
        $this->resolver = $resolver ?? new TrustedUrlResolver();
    }

    public function isValid(string $source): bool
    {
        return $this->resolver->isValid($source);
    }
}
