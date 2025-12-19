<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\File;

use Shopware\Core\Content\Media\File\FileUrlValidatorInterface;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
class FileUrlValidatorStub implements FileUrlValidatorInterface
{
    public function isValid(string $source): bool
    {
        $host = parse_url($source, \PHP_URL_HOST);

        if ($host === false || $host === null) {
            return false;
        }

        return true;
    }
}
