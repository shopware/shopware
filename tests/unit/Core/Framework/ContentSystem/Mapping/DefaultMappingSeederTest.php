<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredElement;
use Shopware\Core\Framework\ContentSystem\Layout\StoredTree;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\AbstractContentSystemElementTypeRegistry;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\ContentSystemElementTypeSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\CopilotSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertySpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\ContentSystem\Mapping\DefaultMappingSeeder;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingSourceReference;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingTypeCompatibility;
use Shopware\Core\Framework\ContentSystem\Mapping\Provider\AbstractMappingCandidateProvider;
use Shopware\Core\Framework\ContentSystem\Mapping\Registry\ContentSystemMappingCandidateRegistry;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DefaultMappingSeeder::class)]
class DefaultMappingSeederTest extends TestCase
{
    public function testSeedsACompatibleDefaultOnlyWhenTheRootOffersIt(): void
    {
        $source = MappingSourceReference::root('product', 'cover');
        $property = new PropertySpecification(
            'media',
            new PropertyType(MediaEntity::class, false, null, null),
            false,
            'Media',
            'Product media',
            null,
            true,
            false,
            $source,
        );
        $specification = new ContentSystemElementTypeSpecification(
            'test:Media',
            'Media',
            'Test media element',
            null,
            null,
            new CopilotSpecification('', []),
            ['media' => $property],
            [],
        );
        $types = static::createStub(AbstractContentSystemElementTypeRegistry::class);
        $types->method('get')->willReturn($specification);
        $provider = new DefaultMappingTestProvider($source);
        $seeder = new DefaultMappingSeeder(
            $types,
            new ContentSystemMappingCandidateRegistry([$provider]),
            new MappingTypeCompatibility(),
        );
        $element = new StoredElement('element-1', 'test:Media');

        $mapped = $seeder->seed(new StoredTree([$element]), 'product', [$element->id])->roots[0];
        static::assertSame($source->jsonSerialize(), $mapped->contextDefinitions->getAllConsumers()['media']->source?->jsonSerialize());

        $otherLayout = $seeder->seed(new StoredTree([$element]), 'category', [$element->id])->roots[0];
        static::assertSame([], $otherLayout->contextDefinitions->getAllConsumers());
    }
}

/**
 * @internal
 */
class DefaultMappingTestProvider extends AbstractMappingCandidateProvider
{
    public function __construct(private readonly MappingSourceReference $source)
    {
    }

    public function supports(string $rootSource): bool
    {
        return $rootSource === 'product';
    }

    public function provide(string $rootSource): array
    {
        return [new MappingCandidate(
            $this->source->displayName(),
            'Media',
            'Product media',
            'media',
            MediaEntity::class,
            ContextType::Single,
            source: $this->source,
        )];
    }
}
