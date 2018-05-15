<?php declare(strict_types=1);

namespace Shopware\StorefrontApi\Search;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Framework\ORM\Search\Term\SearchPattern;
use Shopware\Framework\ORM\Search\Term\SearchTerm;
use Shopware\Framework\ORM\Search\Term\TokenizerInterface;
use Shopware\Context\Struct\ApplicationContext;
use Shopware\Framework\Struct\Uuid;

class KeywordSearchTermInterpreter
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

    public function interpret(string $word, ApplicationContext $context): SearchPattern
    {
        $tokens = $this->tokenizer->tokenize($word);

        $slops = $this->slop($tokens);

        $matches = $this->fetchKeywords($context, $slops);

        $scoring = $this->score($tokens, $matches, $context);

        $scoring = \array_slice($scoring, 0, 10);

        foreach ($scoring as $match) {
            $this->logger->info('Search match: ' . $match['keyword'], $match);
        }
        $pattern = new SearchPattern(new SearchTerm($word));

        foreach ($scoring as $match) {
            $pattern->addTerm(new SearchTerm($match['keyword'], $match['score']));
        }

        return $pattern;
    }

    private function slop(array $tokens): array
    {
        $slops = [
            'normal' => [],
            'reversed' => [],
        ];
        foreach ($tokens as $index => $token) {
            $slopSize = strlen($token) > 4 ? 2 : 1;
            $length = strlen($token);

            for ($i = 1; $i <= $length - 1; ++$i) {
                for ($i2 = 1; $i2 <= $slopSize; ++$i2) {
                    $placeholder = '';
                    for ($i3 = 1; $i3 <= $slopSize + 1; ++$i3) {
                        $slops['normal'][] =
                            substr($token, 0, $i) .
                            $placeholder .
                            substr($token, $i + $i2)
                            . '%'
                        ;
                        $placeholder .= '_';
                    }
                }
            }

            $token = strrev($token);
            for ($i = 1; $i <= $length - 1; ++$i) {
                for ($i2 = 1; $i2 <= $slopSize; ++$i2) {
                    $placeholder = '';
                    for ($i3 = 1; $i3 <= $slopSize + 1; ++$i3) {
                        $slops['reversed'][] =
                            substr($token, 0, $i) .
                            $placeholder .
                            substr($token, $i + $i2)
                            . '%'
                        ;
                        $placeholder .= '_';
                    }
                }
            }
        }

        return $slops;
    }

    private function fetchKeywords(ApplicationContext $context, array $slops): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('keyword');
        $query->from('search_keyword');

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

        $query->andWhere('(' . implode(' OR ', $wheres) . ')');
        $query->andWhere('language_id = :language');
        $query->setParameter('language', Uuid::fromStringToBytes($context->getLanguageId()));

        return $query->execute()->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function score(array $tokens, array $matches, ApplicationContext $context): array
    {
        $scoring = [];

        foreach ($matches as $keyword) {
            $distance = null;
            $bestHit = '';
            foreach ($tokens as $token) {
                $levenshtein = levenshtein($keyword, $token);

                if ($distance === null) {
                    $distance = $levenshtein;
                    $bestHit = $token;
                }
                if ($levenshtein < $distance) {
                    $distance = $levenshtein;
                    $bestHit = $token;
                }
            }

            ++$distance;

            $longTerm = max($bestHit, $keyword);
            $shortTerm = min($bestHit, $keyword);

            $longLeft = substr($longTerm, 1);
            $shortLeft = substr($shortTerm, 1);

            if ($keyword === $bestHit) {
                //exact hit
                $score = 0.8;
            } elseif (strpos($longTerm, $shortTerm) === 0) {
                //starts with
                $score = 0.5;
            } elseif (strpos($longLeft, $shortLeft) === 0) {
                //first character not match
                $score = 0.1;
            } else {
                $score = 1 / $distance / 10;
            }

            $scoreMatch = [
                'keyword' => $keyword,
                'similar' => similar_text($bestHit, $keyword),
                'distance' => $distance,
                'token' => $bestHit,
                'score' => $score,
                'longer' => $longTerm,
                'shorter' => $shortTerm,
            ];

            $scoring[] = $scoreMatch;
        }

        usort($scoring, function (array $a, array $b) {
            return $b['score'] <=> $a['score'];
        });

        return $scoring;
    }
}
