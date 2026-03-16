<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * Describes the data type a content data loader directly provides.
 *
 * @param class-string<Struct> $className
 * @param list<class-string<Struct>> $genericParameters
 */
#[Package('framework')]
final readonly class ContentSystemDataLoaderTypeDescriptor
{
    /**
     * @param class-string<Struct> $className
     * @param list<class-string<Struct>> $genericParameters
     */
    public function __construct(
        public string $className,
        public array $genericParameters = [],
    ) {
    }
}
