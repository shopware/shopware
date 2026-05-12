<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout\Promotion\Util;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Checkout\Promotion\Util\PromotionCodeService;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\Test\Integration\Traits\Promotion\PromotionTestFixtureBehaviour;
use Shopware\Core\Test\TestDefaults;

/**
 * @internal
 */
#[Package('checkout')]
class PromotionCodeServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use PromotionTestFixtureBehaviour;

    private PromotionCodeService $codesService;

    protected function setUp(): void
    {
        $this->codesService = static::getContainer()->get(PromotionCodeService::class);
    }

    public function testGetFixedCode(): void
    {
        $code = $this->codesService->getFixedCode();

        static::assertSame(8, \strlen($code));
        static::assertMatchesRegularExpression('/([A-Z]\d){4}/', $code);
    }

    #[DataProvider('codePreviewDataProvider')]
    public function testGetCodePreview(string $codePattern, string $expectedRegex): void
    {
        $actualCode = $this->codesService->getPreview($codePattern);

        static::assertMatchesRegularExpression($expectedRegex, $actualCode);
    }

    /**
     * @return iterable<array<string>>
     */
    public static function codePreviewDataProvider(): iterable
    {
        yield 'code preview string placeholder a z 1' => ['%s', '/([A-Z]){1}/'];
        yield 'code preview digit placeholder d 1' => ['%d', '/(\d){1}/'];
        yield 'code preview string placeholderstring placeholderstring placeholder a z 3' => ['%s%s%s', '/([A-Z]){3}/'];
        yield 'code preview digit placeholderdigit placeholderdigit placeholder d 3' => ['%d%d%d', '/(\d){3}/'];
        yield 'code preview string placeholderdigit placeholderstring placeholder a z d a z' => ['%s%d%s', '/([A-Z]\d[A-Z])/'];
        yield 'code preview digit placeholderstring placeholderdigit placeholder d a z d' => ['%d%s%d', '/(\d[A-Z]\d)/'];
        yield 'code preview prefix string placeholderstring placeholderdigit placeholderdigit prefix a z 2 d' => ['PREFIX_%s%s%d%d', '/PREFIX_([A-Z]){2}(\d){2}/'];
        yield 'code preview digit placeholderdigit placeholderstring placeholderstring placeholder d 2 a z 2' => ['%d%d%s%s_SUFFIX', '/(\d){2}([A-Z]){2}_SUFFIX/'];
        yield 'code preview prefix string placeholderstring placeholder suffix prefix a z 2 suffix' => ['PREFIX_%s%s_SUFFIX', '/PREFIX_([A-Z]){2}_SUFFIX/'];
        yield 'code preview prefix digit placeholderdigit placeholder suffix prefix d 2 suffix' => ['PREFIX_%d%d_SUFFIX', '/PREFIX_(\d){2}_SUFFIX/'];
        yield 'code preview prefix string placeholderdigit placeholder suffix prefix a z d suffix' => ['PREFIX_%s%d_SUFFIX', '/PREFIX_([A-Z]\d)_SUFFIX/'];
        yield 'code preview prefix digit placeholderstring placeholder suffix prefix d a z suffix' => ['PREFIX_%d%s_SUFFIX', '/PREFIX_(\d[A-Z])_SUFFIX/'];
        yield 'code preview prefix digit placeholderstring placeholder suffix prefix d a z suffix variant 2' => ['PREFIX_%d%s_SUFFIX', '/PREFIX_(\d[A-Z])_SUFFIX/'];
        yield 'code preview prefix digit placeholderstring placeholder now prefix d a z now' => ['PREFIX_%d%s_NOW_WITH_UNRENDERED_VARS_%s%s%d%d_SUFFIX', '/PREFIX_(\d[A-Z])_NOW_WITH_UNRENDERED_VARS_%s%s%d%d_SUFFIX/'];
        yield 'code preview illegal var stops the chain illegal var stops the chain' => ['ILLEGAL_VAR_STOPS_THE_CHAIN_%d%s%q%d%s_SUFFIX', '/ILLEGAL_VAR_STOPS_THE_CHAIN_(\d[A-Z])%q%d%s_SUFFIX/'];
    }

    public function testGenerateIndividualCodesWith0RequestedCodes(): void
    {
        $pattern = 'PREFIX_%s%d%s%d_SUFFIX';
        $codeList = $this->codesService->generateIndividualCodes($pattern, 0);

        static::assertCount(0, $codeList);
    }

    #[DataProvider('generateIndividualCodesDataProvider')]
    public function testGenerateIndividualCodesWithValidRequirements(int $requestedAmount): void
    {
        $pattern = 'PREFIX_%s%d%s%d_SUFFIX';
        $expectedCodeLength = \strlen(str_replace('%', '', $pattern));
        $codeList = $this->codesService->generateIndividualCodes($pattern, $requestedAmount);
        $codeLengthList = array_map(static fn ($code) => \strlen($code), $codeList);

        static::assertCount($requestedAmount, $codeList);
        static::assertCount($requestedAmount, array_unique($codeList));
        static::assertCount(1, array_unique($codeLengthList));
        static::assertSame($expectedCodeLength, $codeLengthList[0]);
    }

    /**
     * @return iterable<array<int>>
     */
    public static function generateIndividualCodesDataProvider(): iterable
    {
        yield 'generate individual codes 1' => [1];
        yield 'generate individual codes 10' => [10];
        yield 'generate individual codes 500' => [500];
        yield 'generate individual codes 20000' => [20000];
    }

    #[DataProvider('generateIndividualCodesWithInsufficientPatternDataProvider')]
    public function testGenerateIndividualCodesWithInsufficientPattern(int $requestedCodeAmount): void
    {
        // Only has 10 possibilities -> 6 or more requested codes would be invalid
        $pattern = 'PREFIX_%d_SUFFIX';

        $this->expectExceptionMessage('The amount of possible codes is too low for the current pattern. Make sure your pattern is sufficiently complex.');
        $this->codesService->generateIndividualCodes($pattern, $requestedCodeAmount);
    }

    /**
     * @return iterable<array<int>>
     */
    public static function generateIndividualCodesWithInsufficientPatternDataProvider(): iterable
    {
        yield 'generate individual codes with insufficient pattern 6' => [6];
        yield 'generate individual codes with insufficient pattern 10' => [10];
        yield 'generate individual codes with insufficient pattern 20' => [20];
    }

    public function testReplaceIndividualCodes(): void
    {
        $promotionRepository = static::getContainer()->get('promotion.repository');
        $codeRepository = static::getContainer()->get('promotion_individual_code.repository');
        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $context = $salesChannelContext->getContext();

        $id = Uuid::randomHex();
        $codes = ['myIndividualCode_00A', 'myIndividualCode_11B'];
        $this->createPromotion($id, null, $promotionRepository, $salesChannelContext);
        $this->createIndividualCode($id, $codes[0], $codeRepository, $context);
        $this->createIndividualCode($id, $codes[1], $codeRepository, $context);

        $criteria = (new Criteria([$id]))
            ->addAssociation('individualCodes');

        /** @var PromotionEntity|null $promotion */
        $promotion = $promotionRepository->search($criteria, $context)->get($id);

        static::assertNotNull($promotion);
        static::assertNotNull($promotion->getIndividualCodes());
        static::assertCount(2, $promotion->getIndividualCodes()->getElements());

        $this->codesService->replaceIndividualCodes($id, 'newPattern_%d%d%s', 10, $context);

        /** @var PromotionEntity $promotion */
        $promotion = $promotionRepository->search($criteria, $context)->first();
        static::assertNotNull($promotion->getIndividualCodes());
        $individualCodes = $promotion->getIndividualCodes()->getElements();
        static::assertCount(10, $individualCodes);
        static::assertNotContains($codes[0], $individualCodes);
        static::assertNotContains($codes[1], $individualCodes);
    }

    public function testReplaceIndividualCodesWithDuplicatePattern(): void
    {
        $promotionRepository = static::getContainer()->get('promotion.repository');
        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

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
        $promotionRepository = static::getContainer()->get('promotion.repository');
        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $this->createPromotionWithCustomData($data, $promotionRepository, $salesChannelContext);

        // 1000 possible codes -> 500 valid codes
        $this->codesService->replaceIndividualCodes($id, $pattern, 100, $salesChannelContext->getContext());

        $this->addCodesAndAssertCount($id, 200, 300);
        $this->addCodesAndAssertCount($id, 200, 500);

        $this->expectExceptionMessage('The amount of possible codes is too low for the current pattern. Make sure your pattern is sufficiently complex.');
        $this->addCodesAndAssertCount($id, 1, 501);
    }

    public function testSplitPatternWithInvalidCodeThrowsInvalidCodePattern(): void
    {
        static::expectException(PromotionException::class);

        $this->codesService->splitPattern('PREFIX_%foo_SUFFIX');
    }

    private function addCodesAndAssertCount(string $id, int $newCodeAmount, int $expectedCodeAmount): void
    {
        $salesChannelContext = static::getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
        $promotionRepository = static::getContainer()->get('promotion.repository');
        $criteria = (new Criteria())
            ->addAssociation('individualCodes');

        $this->codesService->addIndividualCodes($id, $newCodeAmount, $salesChannelContext->getContext());

        /** @var PromotionEntity|null $promotion */
        $promotion = $promotionRepository->search($criteria, $salesChannelContext->getContext())->first();

        static::assertNotNull($promotion);
        static::assertNotNull($promotion->getIndividualCodes());
        static::assertCount($expectedCodeAmount, $promotion->getIndividualCodes()->getIds());
    }
}
