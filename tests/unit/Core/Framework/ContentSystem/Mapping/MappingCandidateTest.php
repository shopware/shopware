<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Mapping;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataContext\ContextType;
use Shopware\Core\Framework\ContentSystem\Mapping\MappingCandidate;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MappingCandidate::class)]
class MappingCandidateTest extends TestCase
{
    public function testSchemaCarriesEveryFieldTheAdministrationNeedsToOfferTheCandidate(): void
    {
        $candidate = new MappingCandidate(
            path: 'category.media',
            label: 'sw-experience-studio.mapping.category.media.label',
            description: 'sw-experience-studio.mapping.category.media.description',
            group: 'media',
            valueType: MediaEntity::class,
            contextType: ContextType::Single,
            projection: 'media_from_category',
        );

        static::assertSame([
            'path' => 'category.media',
            'label' => 'sw-experience-studio.mapping.category.media.label',
            'description' => 'sw-experience-studio.mapping.category.media.description',
            'group' => 'media',
            'valueType' => MediaEntity::class,
            'contextType' => 'single',
            'projection' => 'media_from_category',
        ], $candidate->toSchema());
    }

    public function testAnUnprojectedCandidateReportsANullProjectionRatherThanOmittingTheKey(): void
    {
        $candidate = new MappingCandidate(
            path: 'category.name',
            label: 'a label',
            description: 'a description',
            group: 'basic',
            valueType: 'string',
        );

        $schema = $candidate->toSchema();

        static::assertArrayHasKey('projection', $schema);
        static::assertNull($schema['projection']);
    }

    /**
     * An undotted path names a root-ambient value outright, which is the shape
     * `Mutation/ContextConsumerMirror` writes for resolved reference wiring. `Validation/StoredMappingValidator`
     * tells the two apart by the dot, so offering one would leave every mapping onto it unvalidated.
     */
    public function testRefusesAnUndottedPathThatWouldEscapeTheWriteGate(): void
    {
        $this->expectExceptionObject(ContentSystemException::invalidMappingCandidatePath('productListing'));

        new MappingCandidate(
            path: 'productListing',
            label: 'a label',
            description: 'a description',
            group: 'basic',
            valueType: 'string',
        );
    }
}
