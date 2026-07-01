<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Loader;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class BindingSpecificationSourceDirectory
{
    public function __construct(
        public string $source,
        public string $path,
    ) {
    }
}
