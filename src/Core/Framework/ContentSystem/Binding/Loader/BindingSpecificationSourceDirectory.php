<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Binding\Loader;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class BindingSpecificationSourceDirectory
{
    /**
     * A null prefix marks a standalone binding-specification directory, scanned whole-file for one specification
     * each. A non-null prefix marks an element-type directory, scanned for inline `bindings` sections whose
     * implicit type names are resolved with that prefix (mirroring {@see \Shopware\Core\Framework\ContentSystem\Layout\Type\Loader\ElementTypeSourceDirectory}).
     */
    public function __construct(
        public string $source,
        public string $path,
        public ?string $prefix = null,
    ) {
    }
}
