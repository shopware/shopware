<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\ArrayNormalizer;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('buyers-experience')]
class ProductSearchTermInterpreter implements ProductSearchTermInterpreterInterface
{
    private const RELEVANT_KEYWORD_COUNT = 8;

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly TokenizerInterface $tokenizer,
        private readonly LoggerInterface $logger,
        private readonly AbstractTokenFilter $tokenFilter,
        private readonly KeywordLoader $keywordLoader
    ) {
    }

    public function interpret(string $word, Context $context): SearchPattern
    {
        $tokens = $this->tokenizer->tokenize($word);
        $tokens = $this->tokenFilter->filter($tokens, $context);

        if (empty($tokens)) {
            return new SearchPattern(new SearchTerm(''));
        }

        $tokenSlops = $this->slop($tokens);

        $tokenKeywords = $this->keywordLoader->fetch($tokenSlops, $context);
        $matches = array_fill(0, \count($tokens), []);
        $matches = $this->groupTokenKeywords($matches, $tokenKeywords);

        foreach ($this->permute($tokens) as $token) {
            $tokens[] = $token;
        }

        $tokens = array_keys(array_flip($tokens));

        $pattern = new SearchPattern(new SearchTerm($word));

        $pattern->setBooleanClause($this->getConfigBooleanClause($context));
        $pattern->setTokenTerms($matches);

        $scoring = $this->score($tokens, ArrayNormalizer::flatten($matches));
        // only use the 8 best matches, otherwise the query might explode
        $scoring = \array_slice($scoring, 0, self::RELEVANT_KEYWORD_COUNT, true);

        foreach ($scoring as $keyword => $score) {
            $this->logger->info('Search match: ' . $keyword . ' with score ' . (float) $score);
            $pattern->addTerm(new SearchTerm((string) $keyword, $score));
        }

        return $pattern;
    }

    /**
     * @param array<string> $tokens
     *
     * @return list<string>
     */
    private function permute(array $tokens): array
    {
        $combinations = [];
        foreach ($tokens as $token) {
            foreach ($tokens as $combine) {
                if ($combine === $token) {
                    continue;
                }
                $combinations[] = $token . ' ' . $combine;
            }
        }

        return $combinations;
    }

    /**
     * @param array<string> $tokens
     *
     * @return array<string, array{normal: list<string>, reversed: list<string>}>
     */
    private function slop(array $tokens): array
    {
        $tokenSlops = [];

        foreach ($tokens as $token) {
            $slops = [
                'normal' => [],
                'reversed' => [],
            ];
            $token = (string) $token;
            $slopSize = mb_strlen($token) > 4 ? 2 : 1;
            $length = mb_strlen($token);

            // is too short
            if (mb_strlen($token) <= 2) {
                $slops['normal'][] = $token . '%';
                $slops['reversed'][] = $token . '%';
                $tokenSlops[$token] = $slops;

                continue;
            }

            // looks like a number
            if (\preg_match('/\d/', $token) === 1) {
                $slops['normal'][] = $token . '%';
                $tokenSlops[$token] = $slops;

                continue;
            }

            $tokenRev = $this->reverseToken($token);

            $steps = 2;
            for ($i = 1; $i <= $length - 2; $i += $steps) {
                for ($i2 = 1; $i2 <= $slopSize; ++$i2) {
                    $placeholder = '';

                    for ($i3 = 1; $i3 <= $slopSize + 1; ++$i3) {
                        $slops['normal'][] = mb_substr($token, 0, $i) . $placeholder . mb_substr($token, $i + $i2) . '%';
                        $slops['reversed'][] = mb_substr($tokenRev, 0, $i) . $placeholder . mb_substr($tokenRev, $i + $i2) . '%';

                        $placeholder .= '_';
                    }
                }
            }

            $tokenSlops[$token] = $slops;
        }

        return $tokenSlops;
    }

    /**
     * @param array<int, list<string>> $matches
     * @param list<list<string>> $keywordRows
     *
     * @return array<int, list<string>>
     */
    private function groupTokenKeywords(array $matches, array $keywordRows): array
    {
        foreach ($keywordRows as $keywordRow) {
            $keyword = array_shift($keywordRow);
            if (!$keyword) {
                continue;
            }
            foreach ($keywordRow as $indexColumn => $value) {
                if ((bool) $value) {
                    $matches[$indexColumn][] = $keyword;
                }
            }
        }

        return $matches;
    }

    /**
     * @param array<string> $tokens
     * @param array<string> $matches
     *
     * @return array<string, float>
     */
    private function score(array $tokens, array $matches): array
    {
        $scoring = [];
        foreach ($matches as $match) {
            $score = 1;

            $matchSegments = $this->tokenizer->tokenize($match);

            if (\count($matchSegments) > 1) {
                $score += \count($matchSegments) * 4;
            }

            foreach ($tokens as $token) {
                $levenshtein = levenshtein($match, (string) $token);

                if ($levenshtein === 0) {
                    $score += 6;

                    continue;
                }

                if ($levenshtein <= 2) {
                    $score += 3;

                    continue;
                }

                if ($levenshtein <= 3) {
                    $score += 2;
                }
            }

            $scoring[$match] = $score / 10;
        }

        uasort($scoring, fn ($a, $b) => $b <=> $a);

        return $scoring;
    }

    private function getConfigBooleanClause(Context $context): bool
    {
        $andLogic = false;
        $currentLanguageId = $context->getLanguageId();

        $configurations = $this->connection->fetchAllAssociative(
            'SELECT `and_logic`, `language_id` FROM `product_search_config` WHERE `language_id` IN (:language)',
            ['language' => Uuid::fromHexToBytesList([$currentLanguageId, Defaults::LANGUAGE_SYSTEM])],
            ['language' => ArrayParameterType::BINARY]
        );
        foreach ($configurations as $configuration) {
            $andLogic = (bool) $configuration['and_logic'];
            if (Uuid::fromBytesToHex($configuration['language_id']) === $currentLanguageId) {
                break;
            }
        }

        return $andLogic;
    }

    private function reverseToken(string $token): string
    {
        $chars = mb_str_split($token, 1);

        return implode('', array_reverse($chars));
    }
}
