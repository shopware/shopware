<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Search\Util;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\Search\Term\SearchPattern;
use Shopware\Core\Framework\ORM\Search\Term\SearchTerm;
use Shopware\Core\Framework\ORM\Search\Term\TokenizerInterface;
use Shopware\Core\Framework\Struct\Uuid;

class KeywordSearchTermInterpreter implements KeywordSearchTermInterpreterInterface
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

    public function __construct(Connection $connection, TokenizerInterface $tokenizer, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->tokenizer = $tokenizer;
        $this->logger = $logger;
    }

    public function interpret(string $word, string $scope, Context $context): SearchPattern
    {
        $tokens = $this->tokenizer->tokenize($word);

        $slops = $this->slop($tokens);

        $matches = $this->fetchKeywords($context, $scope, $slops);

        $combines = Permute::permute($tokens);
        foreach ($combines as $token) {
            $tokens[] = $token;
        }
        $tokens = array_keys(array_flip($tokens));

        $scoring = $this->score($tokens, $matches);

        $scoring = \array_slice($scoring, 0, 8);

        foreach ($scoring as $keyword => $score) {
            $this->logger->debug('Search match: ' . $keyword . ' with score ' . (float) $score);
        }

        $pattern = new SearchPattern(new SearchTerm($word), $scope);

        foreach ($scoring as $keyword => $score) {
            $pattern->addTerm(new SearchTerm((string) $keyword, $score));
        }

        return $pattern;
    }

    private function permute($arg): array
    {
        $array = \is_string($arg) ? str_split($arg) : $arg;

        if (\count($array) === 1) {
            return $array;
        }

        $result = [];
        foreach ($array as $key => $item) {
            $nested = $this->permute(array_diff_key($array, [$key => $item]));

            foreach ($nested as $p) {
                $result[] = $item . ' ' . $p;
            }
        }

        return $result;
    }

    private function slop(array $tokens): array
    {
        $slops = [
            'normal' => [],
            'reversed' => [],
        ];

        foreach ($tokens as $index => $token) {
            $slopSize = \strlen($token) > 4 ? 2 : 1;
            $length = \strlen($token);

            if (\strlen($token) <= 2) {
                $slops['normal'][] = $token . '%';
                $slops['reversed'][] = $token . '%';
                continue;
            }

            $steps = 2;
            for ($i = 1; $i <= $length - 2; $i += $steps) {
                for ($i2 = 1; $i2 <= $slopSize; ++$i2) {
                    $placeholder = '';
                    for ($i3 = 1; $i3 <= $slopSize + 1; ++$i3) {
                        $slops['normal'][] =
                            \substr($token, 0, $i) .
                            $placeholder .
                            \substr($token, $i + $i2)
                            . '%'
                        ;
                        $placeholder .= '_';
                    }
                }
            }

            $token = strrev($token);
            for ($i = 1; $i <= $length - 2; $i += $steps) {
                for ($i2 = 1; $i2 <= $slopSize; ++$i2) {
                    $placeholder = '';
                    for ($i3 = 1; $i3 <= $slopSize + 1; ++$i3) {
                        $slops['reversed'][] =
                            \substr($token, 0, $i) .
                            $placeholder .
                            \substr($token, $i + $i2)
                            . '%'
                        ;
                        $placeholder .= '_';
                    }
                }
            }
        }

        return $slops;
    }

    private function fetchKeywords(Context $context, string $scope, array $slops): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('keyword');
        $query->from('search_dictionary');

        $counter = 0;
        $wheres = [];
        foreach ($slops['normal'] as $slop) {
            ++$counter;
            $wheres[] = 'keyword LIKE :reg' . $counter;
            $query->setParameter('reg' . $counter, $slop);
        }
        foreach ($slops['reversed'] as $slop) {
            ++$counter;
            $wheres[] = 'reversed LIKE :reg' . $counter;
            $query->setParameter('reg' . $counter, $slop);
        }

        $query->andWhere('scope = :scope');
        $query->andWhere('language_id = :language');
        $query->andWhere('tenant_id = :tenant');
        $query->andWhere('(' . implode(' OR ', $wheres) . ')');

        $query->setParameter('language', Uuid::fromStringToBytes($context->getLanguageId()));
        $query->setParameter('tenant', Uuid::fromStringToBytes($context->getTenantId()));
        $query->setParameter('scope', $scope);

        return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
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
                $levenshtein = levenshtein($match, $token);
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
                    continue;
                }
            }

            $scoring[$match] = $score / 10;
        }

        uasort($scoring, function ($a, $b) {
            return $b <=> $a;
        });

        return $scoring;
    }
}
