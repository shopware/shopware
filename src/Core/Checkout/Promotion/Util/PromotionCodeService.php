<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Util;

use Shopware\Core\Checkout\Promotion\Exception\PatternAlreadyInUseException;
use Shopware\Core\Checkout\Promotion\Exception\PatternNotComplexEnoughException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;

class PromotionCodeService
{
    public const PROMOTION_PATTERN_REGEX = '/(?<prefix>[^%]*)(?<replacement>(%[sd])+)(?<suffix>.*)/';
    public const CODE_COMPLEXITY_FACTOR = 0.5;

    /**
     * @var EntityRepositoryInterface
     */
    private $individualCodesRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $promotionRepository;

    public function __construct(EntityRepositoryInterface $promotionRepository, EntityRepositoryInterface $individualCodesRepository)
    {
        $this->promotionRepository = $promotionRepository;
        $this->individualCodesRepository = $individualCodesRepository;
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

    public function replaceIndividualCodes(string $promotionId, Context $context, string $pattern, int $amount = 1): void
    {
        if ($this->isCodePatternAlreadyInUse($pattern, $promotionId, $context)) {
            throw new PatternAlreadyInUseException();
        }

        $codes = $this->generateIndividualCodes($pattern, $amount);
        $codeEntries = \array_values(\array_map(static function ($code) use ($promotionId) {
            return [
                'promotionId' => $promotionId,
                'code' => $code,
            ];
        }, $codes));

        $this->resetPromotionCodes($promotionId, $context);
        $this->individualCodesRepository->upsert($codeEntries, $context);
    }

    /**
     * @throws PatternNotComplexEnoughException
     *
     * @return array<string>
     */
    public function generateIndividualCodes(string $pattern, int $amount): array
    {
        if ($amount < 1) {
            return [];
        }

        $codePattern = $this->splitPattern($pattern);

        /*
         * This condition ensures a fundamental randomness to the generated codes in ratio to all possibilities, which
         * also minimizes the number of retries. Therefore, the CODE_COMPLEXITY_FACTOR is the worst-case-scenario
         * probability to find a new unique promotion code.
         */
        if ($this->calculatePossibilites($codePattern['replacementString']) * self::CODE_COMPLEXITY_FACTOR < $amount) {
            throw new PatternNotComplexEnoughException();
        }

        $codes = [];
        do {
            $codes[] = $this->generateCode($codePattern);

            if (\count($codes) >= $amount) {
                $codes = \array_unique($codes);
            }
        } while (\count($codes) < $amount);

        return $codes;
    }

    public function resetPromotionCodes(string $promotionId, Context $context): void
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('promotionId', $promotionId));
        $deleteCodes = \array_values($this->individualCodesRepository->searchIds($criteria, $context)->getData());

        $this->individualCodesRepository->delete($deleteCodes, $context);
    }

    public function splitPattern(string $pattern): array
    {
        \preg_match(self::PROMOTION_PATTERN_REGEX, $pattern, $codePattern);
        $codePattern['replacementString'] = \str_replace('%', '', $codePattern['replacement']);
        $codePattern['replacementArray'] = \str_split($codePattern['replacementString']);

        return $codePattern;
    }

    public function isCodePatternAlreadyInUse(string $pattern, string $promotionId, Context $context): bool
    {
        $criteria = (new Criteria())
            ->addFilter(new NotFilter('AND', [new EqualsFilter('id', $promotionId)]))
            ->addFilter(new EqualsFilter('individualCodePattern', $pattern));

        return $this->promotionRepository->search($criteria, $context)->getTotal() > 0;
    }

    private function generateCode(array $codePattern): string
    {
        $code = '';
        foreach ($codePattern['replacementArray'] as $letter) {
            $code .= $this->getRandomChar($letter);
        }

        return $codePattern['prefix'] . $code . $codePattern['suffix'];
    }

    private function calculatePossibilites(string $pattern): int
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
        $counts = \count_chars($pattern, 1);

        $result = 1;
        foreach ($counts as $key => $count) {
            $result *= $possibilityCounts[\chr($key)] ** $count;
        }

        return $result;
    }

    private function getRandomChar(string $type): string
    {
        if ($type === 'd') {
            return (string) random_int(0, 9);
        }

        return \chr(random_int(65, 90));
    }
}
