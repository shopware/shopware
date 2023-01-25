<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Framework\Log\Package;

#[Package('content')]
interface FileUrlValidatorInterface
{
    public function isValid(string $source): bool;
}
