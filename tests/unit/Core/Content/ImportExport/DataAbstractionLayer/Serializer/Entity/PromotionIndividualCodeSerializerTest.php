<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeDefinition;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Entity\PromotionIndividualCodeSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\AbstractFieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\SerializerRegistry;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(PromotionIndividualCodeSerializer::class)]
class PromotionIndividualCodeSerializerTest extends TestCase
{
    /**
     * @param array<string, mixed> $record
     */
    #[DataProvider('deserializeProvider')]
    public function testDeserializeResolvesIds(
        array $record,
        ?string $promotionIdByName,
        ?string $codeIdByCode,
        ?string $expectedPromotionId,
        ?string $expectedId
    ): void {
        $fieldSerializer = static::createStub(AbstractFieldSerializer::class);
        $fieldSerializer->method('deserialize')->willReturnCallback(
            static fn (Config $config, Field $field, mixed $value): mixed => $value
        );
        $registry = static::createStub(SerializerRegistry::class);
        $registry->method('getFieldSerializer')->willReturn($fieldSerializer);

        $codeRepository = static::createStub(EntityRepository::class);
        $codeRepository->method('searchIds')->willReturn(self::idSearchResult($codeIdByCode));
        $promotionRepository = static::createStub(EntityRepository::class);
        $promotionRepository->method('searchIds')->willReturn(self::idSearchResult($promotionIdByName));

        $serializer = new PromotionIndividualCodeSerializer($codeRepository, $promotionRepository);
        $serializer->setRegistry($registry);

        $definition = (new StaticDefinitionInstanceRegistry(
            [PromotionIndividualCodeDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        ))->getByEntityName(PromotionIndividualCodeDefinition::ENTITY_NAME);

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & ~(\E_ERROR | \E_PARSE | \E_CORE_ERROR | \E_COMPILE_ERROR | \E_USER_ERROR | \E_RECOVERABLE_ERROR)) === 0) {
                return false;
            }

            throw new \ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $result = iterator_to_array($serializer->deserialize(new Config([], [], []), $definition, $record));
        } finally {
            restore_error_handler();
        }

        static::assertSame($expectedPromotionId, $result['promotion']['id'] ?? null);
        static::assertSame($expectedId, $result['id'] ?? null);
    }

    /**
     * @return iterable<string, array{record: array<string, mixed>, promotionIdByName: string|null, codeIdByCode: string|null, expectedPromotionId: string|null, expectedId: string|null}>
     */
    public static function deserializeProvider(): iterable
    {
        $name = ['translations' => ['DEFAULT' => ['name' => 'Promo']]];

        yield 'promotion id absent, resolved by name' => [
            'record' => ['promotion' => $name],
            'promotionIdByName' => 'id-by-name',
            'codeIdByCode' => null,
            'expectedPromotionId' => 'id-by-name',
            'expectedId' => null,
        ];

        yield 'promotion id null, resolved by name' => [
            'record' => ['promotion' => ['id' => null] + $name],
            'promotionIdByName' => 'id-by-name',
            'codeIdByCode' => null,
            'expectedPromotionId' => 'id-by-name',
            'expectedId' => null,
        ];

        yield 'promotion id empty string, resolved by name' => [
            'record' => ['promotion' => ['id' => ''] + $name],
            'promotionIdByName' => 'id-by-name',
            'codeIdByCode' => null,
            'expectedPromotionId' => 'id-by-name',
            'expectedId' => null,
        ];

        yield 'promotion id given, name lookup not applied' => [
            'record' => ['promotion' => ['id' => 'given-id'] + $name],
            'promotionIdByName' => 'id-by-name',
            'codeIdByCode' => null,
            'expectedPromotionId' => 'given-id',
            'expectedId' => null,
        ];

        yield 'promotion id zero is kept, name lookup not applied' => [
            'record' => ['promotion' => ['id' => '0'] + $name],
            'promotionIdByName' => 'id-by-name',
            'codeIdByCode' => null,
            'expectedPromotionId' => '0',
            'expectedId' => null,
        ];

        yield 'promotion id zero is kept, promotionId not applied' => [
            'record' => ['promotionId' => 'fk-id', 'promotion' => ['id' => '0']],
            'promotionIdByName' => null,
            'codeIdByCode' => null,
            'expectedPromotionId' => '0',
            'expectedId' => null,
        ];

        yield 'code id zero is kept, code lookup not applied' => [
            'record' => ['code' => 'CODE', 'id' => '0', 'promotion' => ['id' => 'given-id']],
            'promotionIdByName' => null,
            'codeIdByCode' => 'id-by-code',
            'expectedPromotionId' => 'given-id',
            'expectedId' => '0',
        ];

        yield 'promotion id absent, name not found' => [
            'record' => ['promotion' => $name],
            'promotionIdByName' => null,
            'codeIdByCode' => null,
            'expectedPromotionId' => null,
            'expectedId' => null,
        ];

        yield 'promotion id absent, taken from promotionId' => [
            'record' => ['promotionId' => 'fk-id', 'promotion' => []],
            'promotionIdByName' => null,
            'codeIdByCode' => null,
            'expectedPromotionId' => 'fk-id',
            'expectedId' => null,
        ];

        yield 'promotion id null, taken from promotionId' => [
            'record' => ['promotionId' => 'fk-id', 'promotion' => ['id' => null]],
            'promotionIdByName' => null,
            'codeIdByCode' => null,
            'expectedPromotionId' => 'fk-id',
            'expectedId' => null,
        ];

        yield 'promotion id empty string, taken from promotionId' => [
            'record' => ['promotionId' => 'fk-id', 'promotion' => ['id' => '']],
            'promotionIdByName' => null,
            'codeIdByCode' => null,
            'expectedPromotionId' => 'fk-id',
            'expectedId' => null,
        ];

        yield 'promotion id given, promotionId not applied' => [
            'record' => ['promotionId' => 'fk-id', 'promotion' => ['id' => 'given-id']],
            'promotionIdByName' => null,
            'codeIdByCode' => null,
            'expectedPromotionId' => 'given-id',
            'expectedId' => null,
        ];

        yield 'no promotionId, promotion id stays absent' => [
            'record' => ['promotion' => []],
            'promotionIdByName' => null,
            'codeIdByCode' => null,
            'expectedPromotionId' => null,
            'expectedId' => null,
        ];

        yield 'code id absent, resolved by code' => [
            'record' => ['code' => 'CODE', 'promotion' => ['id' => 'given-id']],
            'promotionIdByName' => null,
            'codeIdByCode' => 'id-by-code',
            'expectedPromotionId' => 'given-id',
            'expectedId' => 'id-by-code',
        ];

        yield 'code id null, resolved by code' => [
            'record' => ['code' => 'CODE', 'id' => null, 'promotion' => ['id' => 'given-id']],
            'promotionIdByName' => null,
            'codeIdByCode' => 'id-by-code',
            'expectedPromotionId' => 'given-id',
            'expectedId' => 'id-by-code',
        ];

        yield 'code id empty string, resolved by code' => [
            'record' => ['code' => 'CODE', 'id' => '', 'promotion' => ['id' => 'given-id']],
            'promotionIdByName' => null,
            'codeIdByCode' => 'id-by-code',
            'expectedPromotionId' => 'given-id',
            'expectedId' => 'id-by-code',
        ];

        yield 'code id given, code lookup not applied' => [
            'record' => ['code' => 'CODE', 'id' => 'given-code-id', 'promotion' => ['id' => 'given-id']],
            'promotionIdByName' => null,
            'codeIdByCode' => 'id-by-code',
            'expectedPromotionId' => 'given-id',
            'expectedId' => 'given-code-id',
        ];

        yield 'code id absent, code not found' => [
            'record' => ['code' => 'CODE', 'promotion' => ['id' => 'given-id']],
            'promotionIdByName' => null,
            'codeIdByCode' => null,
            'expectedPromotionId' => 'given-id',
            'expectedId' => null,
        ];
    }

    private static function idSearchResult(?string $id): IdSearchResult
    {
        return new IdSearchResult(
            $id === null ? 0 : 1,
            $id === null ? [] : [$id => ['primaryKey' => $id, 'data' => []]],
            new Criteria(),
            Context::createDefaultContext()
        );
    }
}
