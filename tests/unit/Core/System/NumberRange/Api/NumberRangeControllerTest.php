<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\NumberRange\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\NumberRange\Api\NumberRangeController;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Shopware\Core\System\NumberRange\NumberRangeEntity;
use Shopware\Core\System\NumberRange\NumberRangeException;
use Shopware\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NumberRangeController::class)]
class NumberRangeControllerTest extends TestCase
{
    public function testReserveUsesValueGenerator(): void
    {
        $context = Context::createDefaultContext();

        $valueGenerator = $this->createMock(AbstractNumberRangeValueGenerator::class);
        $valueGenerator->expects($this->once())
            ->method('getValue')
            ->with('order', $context, 'sales-channel-id', true)
            ->willReturn('1000');

        $response = (new NumberRangeController($valueGenerator, new StaticEntityRepository([])))->reserve(
            'order',
            'sales-channel-id',
            $context,
            new Request(['preview' => '1'])
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{"number":"1000"}', $response->getContent());
    }

    public function testPreviewPatternByNumberRangeIdUsesValueGenerator(): void
    {
        $numberRangeId = Uuid::randomHex();

        $valueGenerator = $this->createMock(AbstractNumberRangeValueGenerator::class);
        $valueGenerator->expects($this->once())
            ->method('previewPatternByNumberRangeId')
            ->with($numberRangeId, 'ORD-{n}', 10)
            ->willReturn('ORD-10');

        $response = (new NumberRangeController($valueGenerator, new StaticEntityRepository([])))->previewPatternByNumberRange(
            $numberRangeId,
            new Request(['pattern' => 'ORD-{n}', 'start' => '10'])
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{"number":"ORD-10"}', $response->getContent());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testDeprecatedPreviewPatternUsesLegacyValueGenerator(): void
    {
        $valueGenerator = $this->createMock(AbstractNumberRangeValueGenerator::class);
        $valueGenerator->expects($this->once())
            ->method('previewPattern')
            ->with('customer', 'C-{n}', 0)
            ->willReturn('C-1');

        $response = (new NumberRangeController($valueGenerator, new StaticEntityRepository([])))->previewPattern(
            'customer',
            new Request(['pattern' => 'C-{n}'])
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('{"number":"C-1"}', $response->getContent());
    }

    public function testDeprecatedPreviewPatternThrowsWhenMajorFeatureIsActive(): void
    {
        $valueGenerator = $this->createMock(AbstractNumberRangeValueGenerator::class);
        $valueGenerator->expects($this->never())
            ->method('previewPattern');

        $this->expectException(FeatureException::class);

        (new NumberRangeController($valueGenerator, new StaticEntityRepository([])))->previewPattern('customer', new Request());
    }

    public function testPatternCollisionsSearchesOtherDocumentNumberRangesOfTheSameType(): void
    {
        $typeId = Uuid::randomHex();
        $numberRangeId = Uuid::randomHex();

        $collidingNumberRange = new NumberRangeEntity();
        $collidingNumberRange->setId(Uuid::randomHex());
        $collidingNumberRange->setName('Invoices shop B');
        $collidingNumberRange->addTranslated('name', 'Invoices shop B');

        $capturedCriteria = null;
        $repository = new StaticEntityRepository([
            static function (Criteria $criteria) use (&$capturedCriteria, $collidingNumberRange): NumberRangeCollection {
                $capturedCriteria = $criteria;

                return new NumberRangeCollection([$collidingNumberRange]);
            },
        ]);

        $response = (new NumberRangeController(static::createStub(AbstractNumberRangeValueGenerator::class), $repository))->patternCollisions(
            new Request(['typeId' => $typeId, 'pattern' => 'INV-{n}', 'numberRangeId' => $numberRangeId]),
            Context::createDefaultContext()
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(
            ['collisions' => [['id' => $collidingNumberRange->getId(), 'name' => 'Invoices shop B']]],
            json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertEquals([
            new EqualsFilter('typeId', $typeId),
            new EqualsFilter('pattern', 'INV-{n}'),
            new PrefixFilter('type.technicalName', 'document_'),
            new NotFilter(NotFilter::CONNECTION_AND, [new EqualsFilter('id', $numberRangeId)]),
        ], $capturedCriteria->getFilters());
    }

    /**
     * @param array<string, string> $query
     */
    #[DataProvider('invalidPatternCollisionsQueryProvider')]
    public function testPatternCollisionsRejectsInvalidQuery(array $query, string $errorCode): void
    {
        $controller = new NumberRangeController(
            static::createStub(AbstractNumberRangeValueGenerator::class),
            new StaticEntityRepository([])
        );

        try {
            $controller->patternCollisions(new Request($query), Context::createDefaultContext());
            static::fail('Expected a NumberRangeException');
        } catch (NumberRangeException $e) {
            static::assertSame($errorCode, $e->getErrorCode());
        }
    }

    /**
     * @return \Generator<string, array{0: array<string, string>, 1: string}>
     */
    public static function invalidPatternCollisionsQueryProvider(): \Generator
    {
        yield 'missing type id' => [['pattern' => '{n}'], NumberRangeException::MISSING_REQUEST_PARAMETER];
        yield 'invalid type id' => [['typeId' => 'invoice', 'pattern' => '{n}'], NumberRangeException::INVALID_REQUEST_PARAMETER];
        yield 'missing pattern' => [['typeId' => Uuid::randomHex()], NumberRangeException::MISSING_REQUEST_PARAMETER];
        yield 'invalid number range id' => [['typeId' => Uuid::randomHex(), 'pattern' => '{n}', 'numberRangeId' => 'x'], NumberRangeException::INVALID_REQUEST_PARAMETER];
    }

    #[DataProvider('aclProtectedRouteProvider')]
    public function testRouteRequiresNumberRangeReadPrivilege(string $routeName): void
    {
        $route = (new AttributeRouteControllerLoader())->load(NumberRangeController::class)->get($routeName);

        static::assertNotNull(
            $route,
            \sprintf('Route "%s" is not defined on %s', $routeName, NumberRangeController::class)
        );
        static::assertSame(['number_range:read'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @return \Generator<string, array{0: string}>
     */
    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'reserving a number consumes the range state' => ['api.action.number-range.reserve'];
        yield 'previewing a pattern exposes the range configuration' => ['api.action.number-range.preview-pattern'];
        yield 'previewing a pattern by id exposes the range configuration' => ['api.action.number-range.preview-pattern-by-id'];
        yield 'listing pattern collisions exposes other ranges of the type' => ['api.action.number-range.pattern-collisions'];
    }
}
