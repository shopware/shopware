<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\NumberRange\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Api\NumberRangeController;
use Shopware\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
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

        $response = (new NumberRangeController($valueGenerator))->reserve(
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

        $response = (new NumberRangeController($valueGenerator))->previewPatternByNumberRange(
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

        $response = (new NumberRangeController($valueGenerator))->previewPattern(
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

        (new NumberRangeController($valueGenerator))->previewPattern('customer', new Request());
    }
}
