<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Framework\Api\Acl\AclCriteriaValidator;
use Shopware\Core\Framework\Api\ApiException;
use Shopware\Core\Framework\Api\Controller\ApiController;
use Shopware\Core\Framework\Api\Response\ResponseFactoryInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\Api\Controller\Fixtures\ApiController\ChildDefinition;
use Shopware\Tests\Unit\Core\Framework\Api\Controller\Fixtures\ApiController\ParentDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ApiController::class)]
class ApiControllerTest extends TestCase
{
    public function testListWithOneToOneAssociationInPathWhereParentEntityHasMultipleAssociationOfSameTypeToSameEntity(): void
    {
        $parentId = Uuid::randomHex();

        $this->createApiController('child_entity.secondChildOneToOneParent.id', $parentId)->list(
            new Request(),
            Context::createDefaultContext(),
            $this->createMock(ResponseFactoryInterface::class),
            'parent-entity',
            \sprintf('/%s/second-child-one-to-one', $parentId)
        );
    }

    public function testListWithManyToOneAssociationInPathWhereParentEntityHasMultipleAssociationOfSameTypeToSameEntity(): void
    {
        $parentId = Uuid::randomHex();

        $this->createApiController('child_entity.secondManyToOneParents.id', $parentId)->list(
            new Request(),
            Context::createDefaultContext(),
            $this->createMock(ResponseFactoryInterface::class),
            'parent-entity',
            \sprintf('/%s/second-child-many-to-one', $parentId)
        );
    }

    public function testListWithOneToManyAssociationInPathWhereParentEntityHasMultipleAssociationOfSameTypeToSameEntity(): void
    {
        $parentId = Uuid::randomHex();

        $this->createApiController('child_entity.secondParentOneToManyId', $parentId)->list(
            new Request(),
            Context::createDefaultContext(),
            $this->createMock(ResponseFactoryInterface::class),
            'parent-entity',
            \sprintf('/%s/second-one-to-many-children', $parentId)
        );
    }

    /**
     * @param array<string, mixed> $overwrites
     */
    #[DataProvider('invalidMediaStorageOverwriteProvider')]
    public function testCloneRejectsInvalidMediaStorageOverwrites(array $overwrites, string $propertyPath): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->never())->method('clone');

        $this->expectExceptionObject(ApiException::invalidCloneOverwrite($propertyPath));

        $this->cloneMedia($repository, $overwrites);
    }

    public static function invalidMediaStorageOverwriteProvider(): \Generator
    {
        yield 'relative path' => [['path' => 'document/invoice.pdf'], 'path'];
        yield 'external looking url that resolves to a local file' => [['path' => 'https://cdn.example.com/../../document/invoice.pdf'], 'path'];
        yield 'path reset to null' => [['path' => null], 'path'];
        yield 'file name with path segments' => [['fileName' => '../../theme/evil'], 'fileName'];
        yield 'path inside extensions, which the writer merges into the root payload' => [['extensions' => ['path' => 'document/invoice.pdf']], 'path'];
        yield 'nested thumbnail path' => [['thumbnails' => [['path' => 'document/invoice.pdf']]], 'thumbnails.0.path'];
    }

    /**
     * @param array<string, mixed> $overwrites
     */
    #[DataProvider('validMediaStorageOverwriteProvider')]
    public function testCloneAcceptsValidMediaStorageOverwrites(array $overwrites): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $repository->expects($this->once())->method('clone')->willThrowException(new \RuntimeException('cloned'));

        $this->expectExceptionObject(new \RuntimeException('cloned'));

        $this->cloneMedia($repository, $overwrites);
    }

    public static function validMediaStorageOverwriteProvider(): \Generator
    {
        yield 'external url' => [['path' => 'https://cdn.example.com/photo.jpg']];
        yield 'regular file name' => [['fileName' => 'copied-media']];
        yield 'nested data without storage fields' => [['translations' => [['title' => 'copy']]]];
    }

    /**
     * @param array<string, mixed> $overwrites
     */
    private function cloneMedia(EntityRepository $repository, array $overwrites): void
    {
        $container = new ContainerBuilder();
        $container->set('media.repository', $repository);

        $controller = new ApiController(
            new StaticDefinitionInstanceRegistry(
                [MediaDefinition::class, MediaThumbnailDefinition::class, MediaTranslationDefinition::class],
                $this->createMock(ValidatorInterface::class),
                $this->createMock(EntityWriteGatewayInterface::class),
                $container
            ),
            $this->createMock(DecoderInterface::class),
            $this->createMock(RequestCriteriaBuilder::class),
            $this->createMock(EntityProtectionValidator::class),
            $this->createMock(AclCriteriaValidator::class)
        );

        $controller->clone(Context::createDefaultContext(), 'media', Uuid::randomHex(), new Request([], ['overwrites' => $overwrites]));
    }

    private function createApiController(
        string $expectedFilterField,
        string $parentId,
    ): ApiController {
        $container = $this->createContainer($expectedFilterField, $parentId);

        $definitionInstanceRegistry = new StaticDefinitionInstanceRegistry(
            [ParentDefinition::class, ChildDefinition::class],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
            $container
        );

        $aggregationParser = new AggregationParser();

        $requestCriteriaBuilder = new RequestCriteriaBuilder(
            $aggregationParser,
            new ApiCriteriaValidator($definitionInstanceRegistry),
            new CriteriaArrayConverter($aggregationParser),
        );

        return new ApiController(
            $definitionInstanceRegistry,
            $this->createMock(DecoderInterface::class),
            $requestCriteriaBuilder,
            $this->createMock(EntityProtectionValidator::class),
            $this->createMock(AclCriteriaValidator::class)
        );
    }

    private function createContainer(string $expectedFilterField, string $parentId): ContainerBuilder
    {
        $container = new ContainerBuilder();

        $parentDefinition = new Definition(StaticEntityRepository::class);
        $parentDefinition->setArguments([[], new ParentDefinition()]);

        $childDefinition = new Definition(StaticEntityRepository::class);
        $childDefinition->setArguments([[], new ChildDefinition()]);

        $container->setDefinitions([
            'parent_entity.repository' => $parentDefinition,
            'child_entity.repository' => $childDefinition,
        ]);

        $container->set('parent_entity.repository', $this->createMock(EntityRepository::class));

        $childRepo = $this->createMock(EntityRepository::class);
        $childRepo->method('search')->willReturnCallback(function (Criteria $criteria, Context $context) use ($expectedFilterField, $parentId): EntitySearchResult {
            $filter = $criteria->getFilters()[0];
            static::assertInstanceOf(EqualsFilter::class, $filter);
            static::assertSame($expectedFilterField, $filter->getField());
            static::assertSame($parentId, $filter->getValue());

            return new EntitySearchResult(
                'child_entity',
                0,
                new EntityCollection(),
                null,
                $criteria,
                $context
            );
        });
        $container->set('child_entity.repository', $childRepo);

        return $container;
    }
}
