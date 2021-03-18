<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SearchKeyword;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Doctrine\DBAL\Statement;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\AbstractTokenFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchPattern;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTerm;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\TokenizerInterface;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Util\ArrayNormalizer;
use Shopware\Core\Framework\Uuid\Uuid;

class ProductSearchTermInterpreter implements ProductSearchTermInterpreterInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var TokenizerInterface
     */
    private $tokenizer;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var AbstractTokenFilter|null
     */
    private $tokenFilter;

    public function __construct(
        Connection $connection,
        TokenizerInterface $tokenizer,
        LoggerInterface $logger,
        ?AbstractTokenFilter $tokenFilter = null
    ) {
        $this->connection = $connection;
        $this->tokenizer = $tokenizer;
        $this->logger = $logger;
        $this->tokenFilter = $tokenFilter;
    }

    public function interpret(string $word, Context $context): SearchPattern
    {
        $tokens = $this->tokenizer->tokenize($word);

        if (Feature::isActive('FEATURE_NEXT_10552') && $this->tokenFilter) {
            $tokens = $this->tokenFilter->filter($tokens, $context);
            if (empty($tokens)) {
                return new SearchPattern(new SearchTerm(''));
            }
            $tokenSlops = $this->slop($tokens);
            if (!$this->checkSlops(array_values($tokenSlops))) {
                return new SearchPattern(new SearchTerm($word));
            }
            $tokenKeywords = $this->fetchKeywords($context, $tokenSlops);
            $matches = \array_fill(0, \count($tokens), []);
            $matches = $this->groupTokenKeywords($matches, $tokenKeywords);
        } else {
            $slops = $this->slop($tokens);
            if (empty($slops['normal'])) {
                return new SearchPattern(new SearchTerm($word));
            }

            $matches = $this->fetchKeywords($context, $slops);
        }

        $combines = $this->permute($tokens);
        foreach ($combines as $token) {
            $tokens[] = $token;
        }
        $tokens = array_keys(array_flip($tokens));

        $pattern = new SearchPattern(new SearchTerm($word));

        if (Feature::isActive('FEATURE_NEXT_10552')) {
            $pattern->setBooleanClause($this->getConfigBooleanClause($context));
            $pattern->setTokenTerms($matches);

            $scoring = $this->score($tokens, ArrayNormalizer::flatten($matches));
            if ($pattern->getBooleanClause() === SearchPattern::BOOLEAN_CLAUSE_OR) {
                $scoring = \array_slice($scoring, 0, 8, true);
            }
        } else {
            $scoring = $this->score($tokens, $matches);
            $scoring = \array_slice($scoring, 0, 8, true);
        }

        foreach ($scoring as $keyword => $score) {
            $this->logger->info('Search match: ' . $keyword . ' with score ' . (float) $score);
        }

        foreach ($scoring as $keyword => $score) {
            $pattern->addTerm(new SearchTerm((string) $keyword, $score));
        }

        return $pattern;
    }

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

    private function slop(array $tokens): array
    {
        //@feature-deprecated (flag:FEATURE_NEXT_10552) tag:v6.4.0 - Please remove this line after removing Feature Flag
        //Dont need to declare $slops right here
        $slops = [];
        $tokenSlops = [];
        if (!Feature::isActive('FEATURE_NEXT_10552')) {
            $slops = [
                'normal' => [],
                'reversed' => [],
            ];
        }

        foreach ($tokens as $token) {
            if (Feature::isActive('FEATURE_NEXT_10552')) {
                $slops = [
                    'normal' => [],
                    'reversed' => [],
                ];
            }
            $token = (string) $token;
            $slopSize = mb_strlen($token) > 4 ? 2 : 1;
            $length = mb_strlen($token);

            if (mb_strlen($token) <= 2) {
                $slops['normal'][] = $token . '%';
                $slops['reversed'][] = $token . '%';
                if (Feature::isActive('FEATURE_NEXT_10552')) {
                    $tokenSlops[$token] = $slops;
                }

                continue;
            }

            $steps = 2;
            for ($i = 1; $i <= $length - 2; $i += $steps) {
                for ($i2 = 1; $i2 <= $slopSize; ++$i2) {
                    $placeholder = '';
                    for ($i3 = 1; $i3 <= $slopSize + 1; ++$i3) {
                        $slops['normal'][] = mb_substr($token, 0, $i) . $placeholder . mb_substr($token, $i + $i2) . '%';
                        $placeholder .= '_';
                    }
                }
            }
            //@feature-deprecated (flag:FEATURE_NEXT_10552) tag:v6.4.0 - Please remove this line after removing Feature Flag
            //Dont need to declare $tokenRev right here
            $tokenRev = '';
            if (Feature::isActive('FEATURE_NEXT_10552')) {
                $tokenRev = strrev($token);
            } else {
                $token = strrev($token);
            }
            for ($i = 1; $i <= $length - 2; $i += $steps) {
                for ($i2 = 1; $i2 <= $slopSize; ++$i2) {
                    $placeholder = '';
                    for ($i3 = 1; $i3 <= $slopSize + 1; ++$i3) {
                        if (Feature::isActive('FEATURE_NEXT_10552')) {
                            $slops['reversed'][] = mb_substr($tokenRev, 0, $i) . $placeholder . mb_substr($tokenRev, $i + $i2) . '%';
                        } else {
                            $slops['reversed'][] = mb_substr($token, 0, $i) . $placeholder . mb_substr($token, $i + $i2) . '%';
                        }
                        $placeholder .= '_';
                    }
                }
            }
            if (Feature::isActive('FEATURE_NEXT_10552')) {
                $tokenSlops[$token] = $slops;
            }
        }
        if (Feature::isActive('FEATURE_NEXT_10552')) {
            return $tokenSlops;
        }

        return $slops;
    }

    /**
     * @internal (flag:FEATURE_NEXT_10552)
     */
    private function groupTokenKeywords(array $matches, array $keywordRows): array
    {
        foreach ($keywordRows as $keywordRow) {
            $keyword = array_shift($keywordRow);
            foreach ($keywordRow as $indexColumn => $value) {
                if ((bool) $value) {
                    $matches[$indexColumn][] = $keyword;
                }
            }
        }

        return $matches;
    }

    private function fetchKeywords(Context $context, array $tokenSlops): array
    {
        $query = new QueryBuilder($this->connection);
        $query->select('keyword');
        $query->from('product_keyword_dictionary');

        $query->setTitle('search::detect-keywords');

        $counter = 0;
        $wheres = [];
        $index = 0;

        if (!Feature::isActive('FEATURE_NEXT_10552')) {
            $tokenSlops = [$tokenSlops];
        }

        foreach ($tokenSlops as $slops) {
            $slopsWheres = [];
            foreach ($slops['normal'] as $slop) {
                ++$counter;
                if (Feature::isActive('FEATURE_NEXT_10552')) {
                    $slopsWheres[] = 'keyword LIKE :reg' . $counter;
                } else {
                    $wheres[] = 'keyword LIKE :reg' . $counter;
                }
                $query->setParameter('reg' . $counter, $slop);
            }
            foreach ($slops['reversed'] as $slop) {
                ++$counter;
                if (Feature::isActive('FEATURE_NEXT_10552')) {
                    $slopsWheres[] = 'reversed LIKE :reg' . $counter;
                } else {
                    $wheres[] = 'reversed LIKE :reg' . $counter;
                }
                $query->setParameter('reg' . $counter, $slop);
            }
            if (Feature::isActive('FEATURE_NEXT_10552')) {
                $query->addSelect('IF (' . implode(' OR ', $slopsWheres) . ', 1, 0) as \'' . $index++ . '\'');
                $wheres = array_merge($wheres, $slopsWheres);
            }
        }

        $query->andWhere('language_id = :language');
        $query->andWhere('(' . implode(' OR ', $wheres) . ')');

        $query->setParameter('language', Uuid::fromHexToBytes($context->getLanguageId()));

        if (!Feature::isActive('FEATURE_NEXT_10552')) {
            return $query->execute()->fetchAll(FetchMode::COLUMN);
        }

        return $query->execute()->fetchAll(FetchMode::NUMERIC);
    }

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

        uasort($scoring, function ($a, $b) {
            return $b <=> $a;
        });

        return $scoring;
    }

    /**
     * @internal (flag:FEATURE_NEXT_10552)
     */
    private function checkSlops(array $tokenSlops): bool
    {
        foreach ($tokenSlops as $slops) {
            if ($slops['normal']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @internal (flag:FEATURE_NEXT_10552)
     */
    private function getConfigBooleanClause(Context $context): bool
    {
        $andLogic = false;
        $currentLanguageId = $context->getLanguageId();

        $query = new QueryBuilder($this->connection);
        $query->select('and_logic, language_id');
        $query->from('product_search_config');

        $query->andWhere('language_id IN (:language)');

        $query->setParameter('language', Uuid::fromHexToBytesList([
            $currentLanguageId, Defaults::LANGUAGE_SYSTEM,
        ]), Connection::PARAM_STR_ARRAY);

        /** @var Statement $stmt */
        $stmt = $query->execute();

        $configurations = $stmt->fetchAll();
        foreach ($configurations as $configuration) {
            $andLogic = (bool) $configuration['and_logic'];
            if (Uuid::fromBytesToHex($configuration['language_id']) === $currentLanguageId) {
                break;
            }
        }

        return $andLogic;
    }
}
