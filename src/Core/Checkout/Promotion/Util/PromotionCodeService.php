<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Util;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Promotion\Exception\PatternNotComplexEnoughException;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Random;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @phpstan-type CodePattern array{prefix?: string, replacement: string, suffix?: string, replacementString: string, replacementArray: array<string>}
 */
#[Package('buyers-experience')]
class PromotionCodeService
{
    final public const PROMOTION_PATTERN_REGEX = '/(?<prefix>[^%]*)(?<replacement>(%[sd])+)(?<suffix>.*)/';
    final public const CODE_COMPLEXITY_FACTOR = 0.5;

    /**
     * @internal
     */
    public function __construct(
        private readonly EntityRepository $promotionRepository,
        private readonly EntityRepository $individualCodesRepository,
        private readonly Connection $connection
    ) {
    }

    public function getFixedCode(): string
    {
        $pattern = implode('', array_fill(0, 4, '%s%d'));

        return $this->generateIndividualCodes($pattern, 1)[0];
    }

    public function getPreview(string $pattern): string
    {
        return $this->generateIndividualCodes($pattern, 1)[0];
    }

    /**
     * @param array<string> $codeBlacklist
     *
     * @throws PatternNotComplexEnoughException
     *
     * @return array<string>
     */
    public function generateIndividualCodes(string $pattern, int $amount, array $codeBlacklist = []): array
    {
        if ($amount < 1) {
            return [];
        }

        $codePattern = $this->splitPattern($pattern);
        $blacklistCount = \count($codeBlacklist);

        /*
         * This condition ensures a fundamental randomness to the generated codes in ratio to all possibilities, which
         * also minimizes the number of retries. Therefore, the CODE_COMPLEXITY_FACTOR is the worst-case-scenario
         * probability to find a new unique promotion code.
         */

        $complexity = $this->isComplexEnough($codePattern['replacementString'], $amount, $blacklistCount);

        if (!$complexity) {
            throw PromotionException::patternNotComplexEnough();
        }

        $codes = $codeBlacklist;
        do {
            $codes[] = $this->generateCode($codePattern);

            if (\count($codes) >= $amount + $blacklistCount) {
                $codes = array_unique($codes);
            }
        } while (\count($codes) < $amount + $blacklistCount);

        return array_diff($codes, $codeBlacklist);
    }

    public function addIndividualCodes(string $promotionId, int $amount, Context $context): void
    {
        $criteria = (new Criteria([$promotionId]))
            ->addAssociation('individualCodes');

        $promotion = $this->promotionRepository->search($criteria, $context)->first();

        if (!$promotion instanceof PromotionEntity) {
            throw PromotionException::promotionsNotFound([$promotionId]);
        }

        $pattern = $promotion->getIndividualCodePattern();

        if (empty($pattern)) {
            throw PromotionException::patternNotComplexEnough();
        }

        if ($promotion->getIndividualCodes() === null) {
            $this->replaceIndividualCodes($promotionId, $pattern, $amount, $context);

            return;
        }

        $newCodes = $this->generateIndividualCodes(
            $pattern,
            $amount,
            $promotion->getIndividualCodes()->getCodeArray()
        );

        $codeEntries = $this->prepareCodeEntities($promotionId, $newCodes);
        $this->individualCodesRepository->upsert($codeEntries, $context);
    }

    /**
     * @throws PromotionException
     */
    public function replaceIndividualCodes(string $promotionId, string $pattern, int $amount, Context $context): void
    {
        if ($this->isCodePatternAlreadyInUse($pattern, $promotionId, $context)) {
            throw PromotionException::patternAlreadyInUse();
        }

        $codes = $this->generateIndividualCodes($pattern, $amount);

        $codeEntries = $this->prepareCodeEntities($promotionId, $codes);

        $this->resetPromotionCodes($promotionId, $context);

        $this->individualCodesRepository->upsert($codeEntries, $context);
    }

    public function resetPromotionCodes(string $promotionId, Context $context): void
    {
        $this->connection->executeStatement('DELETE FROM promotion_individual_code WHERE promotion_id = :id', ['id' => Uuid::fromHexToBytes($promotionId)]);
    }

    /**
     * @return CodePattern
     */
    public function splitPattern(string $pattern): array
    {
        preg_match(self::PROMOTION_PATTERN_REGEX, $pattern, $codePattern);
        if (!isset($codePattern['replacement'])) {
            throw PromotionException::invalidCodePattern($pattern);
        }

        $codePattern['replacementString'] = str_replace('%', '', $codePattern['replacement']);
        $codePattern['replacementArray'] = str_split($codePattern['replacementString']);

        return $codePattern;
    }

    public function isCodePatternAlreadyInUse(string $pattern, string $promotionId, Context $context): bool
    {
        $criteria = (new Criteria())
            ->addFilter(new NotFilter('AND', [new EqualsFilter('id', $promotionId)]))
            ->addFilter(new EqualsFilter('individualCodePattern', $pattern));

        return $this->promotionRepository->searchIds($criteria, $context)->getTotal() > 0;
    }

    /**
     * @param CodePattern $codePattern
     */
    private function generateCode(array $codePattern): string
    {
        $code = '';
        foreach ($codePattern['replacementArray'] as $letter) {
            $code .= $this->getRandomChar($letter);
        }

        return ($codePattern['prefix'] ?? '') . $code . ($codePattern['suffix'] ?? '');
    }

    private function getRandomChar(string $type): string
    {
        if ($type === 'd') {
            return (string) Random::getInteger(0, 9);
        }

        return \chr(Random::getInteger(65, 90));
    }

    /**
     * @param array<string> $codes
     *
     * @return array<array<string, string>>
     */
    private function prepareCodeEntities(string $promotionId, array $codes): array
    {
        return array_values(array_map(static fn ($code) => [
            'promotionId' => $promotionId,
            'code' => $code,
        ], $codes));
    }

    private function isComplexEnough(string $pattern, int $amount, int $blacklistCount): bool
    {
        /*
         * These counts describe the amount of possibilities in a single digit, depending on variable type:
         * - d: digits (0-9)
         * - s: letters (A-Z)
         */
        $possibilityCounts = [
            'd' => 10,
            's' => 26,
        ];
        /** @var array<int, int> $counts */
        $counts = count_chars($pattern, 1);

        $result = 1;
        foreach ($counts as $key => $count) {
            $result *= $possibilityCounts[\chr($key)] ** $count;

            if ($result * self::CODE_COMPLEXITY_FACTOR >= ($amount + $blacklistCount)) {
                return true;
            }
        }

        return false;
    }
}
