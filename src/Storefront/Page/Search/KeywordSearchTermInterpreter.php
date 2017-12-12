<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Shopware\Api\Search\Term\SearchPattern;
use Shopware\Api\Search\Term\SearchTerm;
use Shopware\Context\Struct\TranslationContext;

class KeywordSearchTermInterpreter
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var \Shopware\Api\Search\Term\TokenizerInterface
     */
    private $tokenizer;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(Connection $connection, \Shopware\Api\Search\Term\TokenizerInterface $tokenizer, LoggerInterface $logger)
    {
        $this->connection = $connection;
        $this->tokenizer = $tokenizer;
        $this->logger = $logger;
    }

    public function interpret(string $word, TranslationContext $context): SearchPattern
    {
        $tokens = $this->tokenizer->tokenize($word);

        $slops = $this->slop($tokens);

        $matches = $this->fetchKeywords($context, $slops);
        $scoring = $this->score($tokens, $matches, $context);

        $pattern = new \Shopware\Api\Search\Term\SearchPattern(new \Shopware\Api\Search\Term\SearchTerm($word));

        foreach ($scoring as $match) {
            $pattern->addTerm(
                new SearchTerm($match['keyword'], $match['score'])
            );
        }

        return $pattern;
    }

    private function slop(array $tokens): array
    {
        $slops = [];
        foreach ($tokens as $index => $token) {
            $slopSize = strlen($token) > 4 ? 2 : 1;
            $length = strlen($token);

            for ($i = 0; $i <= $length - 1; ++$i) {
                for ($i2 = 1; $i2 <= $slopSize; ++$i2) {
                    $placeholder = '';
                    for ($i3 = 1; $i3 <= $slopSize + 1; ++$i3) {
                        $slops[] =
                            '%' .
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

    private function fetchKeywords(TranslationContext $context, array $slops): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select('*');
        $query->from('search_keyword');

        $counter = 0;
        $wheres = [];
        foreach ($slops as $slop) {
            ++$counter;
            $wheres[] = 'keyword LIKE :reg' . $counter;
            $query->setParameter('reg' . $counter, $slop);
        }

        $query->andWhere('(' . implode(' OR ', $wheres) . ')');
        $query->andWhere('shop_uuid = :shop');
        $query->setParameter('shop', $context->getShopUuid());

        return $query->execute()->fetchAll();
    }

    private function score(array $tokens, array $matches, TranslationContext $context): array
    {
        foreach ($matches as &$match) {
            $distance = null;
            foreach ($tokens as $token) {
                $keyword = $match['keyword'];
                $bestToken = '';
                $levenshtein = levenshtein($keyword, $token);

                if ($distance === null) {
                    $distance = $levenshtein;
                    $bestToken = $token;
                }
                if ($levenshtein < $distance) {
                    $distance = $levenshtein;
                    $bestToken = $token;
                }
            }

            ++$distance;

            $match['score'] = 1 / $distance / ($match['document_count'] / 25);

            $this->logger->info(
                'Search match: ' . $keyword,
                [
                    'match' => $keyword,
                    'token' => $bestToken,
                    'distance' => $distance - 1,
                    'document count' => $match['document_count'],
                    'score' => $match['score'],
                ]
            );
        }

        return $matches;
    }
}
