<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Promotion\Util;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Promotion\Util\PromotionCodeService;
use Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits\PromotionTestFixtureBehaviour;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;

class PromotionCodeServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    /**
     * @var PromotionCodeService
     */
    private $codesService;

    protected function setUp(): void
    {
        $this->codesService = $this->getContainer()->get(PromotionCodeService::class);
    }

    public function testGetFixedCode(): void
    {
        $code = $this->codesService->getFixedCode();

        static::assertEquals(8, \strlen($code));
        static::assertMatchesRegularExpression('/([A-Z][0-9]){4}/', $code);
    }

    /**
     * @dataProvider codePreviewDataProvider
     */
    public function testGetCodePreview($codePattern, $expectedRegex): void
    {
        $actualCode = $this->codesService->getPreview($codePattern);

        static::assertMatchesRegularExpression($expectedRegex, $actualCode);
    }

    public function codePreviewDataProvider(): array
    {
        return [
            ['%s', '/([A-Z]){1}/'],
            ['%d', '/([0-9]){1}/'],
            ['%s%s%s', '/([A-Z]){3}/'],
            ['%d%d%d', '/([0-9]){3}/'],
            ['%s%d%s', '/([A-Z][0-9][A-Z])/'],
            ['%d%s%d', '/([0-9][A-Z][0-9])/'],
            ['PREFIX_%s%s%d%d', '/PREFIX_([A-Z]){2}([0-9]){2}/'],
            ['%d%d%s%s_SUFFIX', '/([0-9]){2}([A-Z]){2}_SUFFIX/'],
            ['PREFIX_%s%s_SUFFIX', '/PREFIX_([A-Z]){2}_SUFFIX/'],
            ['PREFIX_%d%d_SUFFIX', '/PREFIX_([0-9]){2}_SUFFIX/'],
            ['PREFIX_%s%d_SUFFIX', '/PREFIX_([A-Z][0-9])_SUFFIX/'],
            ['PREFIX_%d%s_SUFFIX', '/PREFIX_([0-9][A-Z])_SUFFIX/'],
            ['PREFIX_%d%s_SUFFIX', '/PREFIX_([0-9][A-Z])_SUFFIX/'],
            ['PREFIX_%d%s_NOW_WITH_UNRENDERED_VARS_%s%s%d%d_SUFFIX', '/PREFIX_([0-9][A-Z])_NOW_WITH_UNRENDERED_VARS_%s%s%d%d_SUFFIX/'],
            ['ILLEGAL_VAR_STOPS_THE_CHAIN_%d%s%q%d%s_SUFFIX', '/ILLEGAL_VAR_STOPS_THE_CHAIN_([0-9][A-Z])%q%d%s_SUFFIX/'],
        ];
    }

    public function testGenerateIndividualCodesWith0RequestedCodes(): void
    {
        $pattern = 'PREFIX_%s%d%s%d_SUFFIX';
        $codeList = $this->codesService->generateIndividualCodes($pattern, 0);

        static::assertCount(0, $codeList);
    }

    /**
     * @dataProvider generateIndividualCodesDataProvider
     */
    public function testGenerateIndividualCodesWithValidRequirements(int $requestedAmount): void
    {
        $pattern = 'PREFIX_%s%d%s%d_SUFFIX';
        $expectedCodeLength = \strlen(str_replace('%', '', $pattern));
        $codeList = $this->codesService->generateIndividualCodes($pattern, $requestedAmount);
        $codeLengthList = array_map(static function ($code) {
            return \strlen($code);
        }, $codeList);

        static::assertCount($requestedAmount, $codeList);
        static::assertCount($requestedAmount, array_unique($codeList));
        static::assertCount(1, array_unique($codeLengthList));
        static::assertEquals($expectedCodeLength, $codeLengthList[0]);
    }

    public function generateIndividualCodesDataProvider(): array
    {
        return [
            [1],
            [10],
            [500],
            [20000],
        ];
    }

    /**
     * @dataProvider generateIndividualCodesWithInsufficientPatternDataProvider
     */
    public function testGenerateIndividualCodesWithInsufficientPattern(int $requestedCodeAmount): void
    {
        // Only has 10 possibilities -> 6 or more requested codes would be invalid
        $pattern = 'PREFIX_%d_SUFFIX';

        $this->expectExceptionMessage('The amount of possible codes is too low for the current pattern. Make sure your pattern is sufficiently complex.');
        $this->codesService->generateIndividualCodes($pattern, $requestedCodeAmount);
    }

    public function generateIndividualCodesWithInsufficientPatternDataProvider(): array
    {
        return [
            [6],
            [10],
            [20],
        ];
    }

    public function testReplaceIndividualCodes(): void
    {
        $promotionRepository = $this->getContainer()->get('promotion.repository');
        $codeRepository = $this->getContainer()->get('promotion_individual_code.repository');
        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $context = $salesChannelContext->getContext();

        $id = Uuid::randomHex();
        $codes = ['myIndividualCode_00A', 'myIndividualCode_11B'];
        $this->createPromotion($id, null, $promotionRepository, $salesChannelContext);
        $this->createIndividualCode($id, $codes[0], $codeRepository, $context);
        $this->createIndividualCode($id, $codes[1], $codeRepository, $context);

        $criteria = (new Criteria([$id]))
            ->addAssociation('individualCodes');

        /** @var PromotionEntity $promotion */
        $promotion = $promotionRepository->search($criteria, $context)->first();
        static::assertCount(2, $promotion->getIndividualCodes()->getElements());

        $this->codesService->replaceIndividualCodes($id, 'newPattern_%d%d%s', 10, $context);

        $promotion = $promotionRepository->search($criteria, $context)->first();
        $individualCodes = $promotion->getIndividualCodes()->getElements();
        static::assertCount(10, $individualCodes);
        static::assertNotContains($codes[0], $individualCodes);
        static::assertNotContains($codes[1], $individualCodes);
    }

    public function testReplaceIndividualCodesWithDuplicatePattern(): void
    {
        $promotionRepository = $this->getContainer()->get('promotion.repository');
        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $id = Uuid::randomHex();
        $duplicatePattern = 'TEST_%d%s_END';

        // Create 2 Promotions. The first one has a pattern, which the second will try to use as well later on
        $this->createPromotionWithCustomData(['individualCodePattern' => $duplicatePattern], $promotionRepository, $salesChannelContext);
        $this->createPromotionWithCustomData(['id' => $id], $promotionRepository, $salesChannelContext);

        $this->expectExceptionMessage('Code pattern already exists in another promotion. Please provide a different pattern.');
        $this->codesService->replaceIndividualCodes($id, $duplicatePattern, 1, $salesChannelContext->getContext());
    }

    public function testAddIndividualCodes(): void
    {
        $id = Uuid::randomHex();
        $pattern = 'somePattern_%d%d%d';
        $data = [
            'id' => $id,
            'useCodes' => true,
            'useIndividualCodes' => true,
            'individualCodePattern' => $pattern,
        ];
        $promotionRepository = $this->getContainer()->get('promotion.repository');
        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $this->createPromotionWithCustomData($data, $promotionRepository, $salesChannelContext);

        // 1000 possible codes -> 500 valid codes
        $this->codesService->replaceIndividualCodes($id, $pattern, 100, $salesChannelContext->getContext());

        $this->addCodesAndAssertCount($id, 200, 300);
        $this->addCodesAndAssertCount($id, 200, 500);

        $this->expectExceptionMessage('The amount of possible codes is too low for the current pattern. Make sure your pattern is sufficiently complex.');
        $this->addCodesAndAssertCount($id, 1, 501);
    }

    private function addCodesAndAssertCount(string $id, int $newCodeAmount, int $expectedCodeAmount): void
    {
        $salesChannelContext = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
        $promotionRepository = $this->getContainer()->get('promotion.repository');
        $criteria = (new Criteria())
            ->addAssociation('individualCodes');

        $this->codesService->addIndividualCodes($id, $newCodeAmount, $salesChannelContext->getContext());

        /** @var PromotionEntity $promotion */
        $promotion = $promotionRepository->search($criteria, $salesChannelContext->getContext())->first();

        static::assertCount($expectedCodeAmount, $promotion->getIndividualCodes()->getIds());
    }
}
