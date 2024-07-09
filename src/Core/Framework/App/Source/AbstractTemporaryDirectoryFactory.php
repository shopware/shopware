<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Source;

use Shopware\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('core')]
abstract class AbstractTemporaryDirectoryFactory
{
    abstract public function getDecorated(): AbstractTemporaryDirectoryFactory;

    abstract public function path(): string;
}
