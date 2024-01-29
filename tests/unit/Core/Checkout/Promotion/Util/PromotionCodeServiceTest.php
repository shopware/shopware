<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Util;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionIndividualCode\PromotionIndividualCodeEntity;
use Shopware\Core\Checkout\Promotion\PromotionCollection;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Checkout\Promotion\Util\PromotionCodeService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(PromotionCodeService::class)]
class PromotionCodeServiceTest extends TestCase
{
    public function testAddIndividualCodesPromotionNotFound(): void
    {
        $context = Context::createDefaultContext();

        $promotionRepository = new StaticEntityRepository([new PromotionCollection([])]);

        $codeService = new PromotionCodeService(
            $promotionRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(Connection::class)
        );

        static::expectException(PromotionException::class);
        static::expectExceptionMessage('These promotions "promotionId" are not found');
        $codeService->addIndividualCodes('promotionId', 10, $context);
    }

    public function testAddIndividualCodesPromotionEmptyPattern(): void
    {
        $context = Context::createDefaultContext();

        $promotion = new PromotionEntity();
        $promotion->setId('promotionId');
        $promotion->setIndividualCodePattern('');

        $promotionRepository = new StaticEntityRepository([new PromotionCollection([$promotion])]);

        $codeService = new PromotionCodeService(
            $promotionRepository,
            $this->createMock(EntityRepository::class),
            $this->createMock(Connection::class)
        );

        static::expectException(PromotionException::class);
        static::expectExceptionMessage('The amount of possible codes is too low for the current pattern. Make sure your pattern is sufficiently complex.');
        $codeService->addIndividualCodes('promotionId', 10, $context);
    }

    public function testReplaceIndividualCodes(): void
    {
        $context = Context::createDefaultContext();

        $promotion = new PromotionEntity();
        $promotion->setId('promotionId');
        $promotion->setIndividualCodePattern('%s');

        $promotionRepository = new StaticEntityRepository([
            new PromotionCollection([$promotion]),
            [],
        ]);

        $promotionId = Uuid::randomHex();
        $individualCodeRepository = new StaticEntityRepository([new PromotionIndividualCodeCollection([])]);
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())->method('executeStatement')->with(
            'DELETE FROM promotion_individual_code WHERE promotion_id = :id',
            ['id' => Uuid::fromHexToBytes($promotionId)],
        );

        $codeService = new PromotionCodeService(
            $promotionRepository,
            $individualCodeRepository,
            $connection
        );

        $codeService->addIndividualCodes($promotionId, 10, $context);

        static::assertNotEmpty($individualCodeRepository->upserts[0]);
        static::assertCount(10, $individualCodeRepository->upserts[0]);
    }

    public function testAddIndividualCodes(): void
    {
        $context = Context::createDefaultContext();

        $promotion = new PromotionEntity();
        $promotion->setId('promotionId');
        $promotion->setIndividualCodePattern('%s');

        $code = new PromotionIndividualCodeEntity();
        $code->setId(Uuid::randomHex());
        $code->setCode('code');
        $codes = new PromotionIndividualCodeCollection([]);

        $promotion->setIndividualCodes($codes);

        $promotionRepository = new StaticEntityRepository([
            new PromotionCollection([$promotion]),
            [],
        ]);
        $individualCodeRepository = new StaticEntityRepository([new PromotionIndividualCodeCollection([])]);
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())->method('executeStatement');

        $codeService = new PromotionCodeService(
            $promotionRepository,
            $individualCodeRepository,
            $connection
        );

        $promotionId = Uuid::randomHex();

        $codeService->addIndividualCodes($promotionId, 10, $context);

        static::assertNotEmpty($individualCodeRepository->upserts[0]);
        static::assertCount(10, $individualCodeRepository->upserts[0]);
    }
}
