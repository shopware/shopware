<?php declare(strict_types=1);

namespace Shopware\Core\Test\Stub\ContentSystem;

use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\Inline\InlineMappingTokenParser;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingConsumers;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\AbstractContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\ContentSystem\Mapping\StoredMappingInspector;
use Shopware\Core\Framework\Log\Package;

/**
 * Shared mapping admission wiring; tests supply their own type and candidate registries.
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
final class MappingTestFactory
{
    public static function inspector(AbstractContentSystemElementTypeRegistry $types, AbstractContentSystemMappingCandidateRegistry $candidates, InlineMappingTokenParser $parser = new InlineMappingTokenParser()): StoredMappingInspector
    {
        return new StoredMappingInspector($types, $candidates, new MappingTypeCompatibility(), new MappingConsumers(), $parser);
    }
}
