<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Term\Filter;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
class TokenFilterTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @dataProvider cases
     *
     * @param list<string> $tokens
     * @param list<string> $excludedTerms
     * @param list<string> $expected
     */
    public function testExcludedFilterFilter(array $tokens, array $excludedTerms, array $expected): void
    {
        $this->updateProductSearchConfig($excludedTerms);

        $service = new TokenFilter($this->connection);
        $keywords = $service->filter($tokens, $this->context);

        sort($expected);
        sort($keywords);
        static::assertEquals($expected, $keywords);
    }

    /**
     * @return array<array{list<string>, list<string>, list<string>}>
     */
    public static function cases(): array
    {
        return [
            [
                [],
                [],
                [],
            ],
            [
                ['between', 'against', 'surprise', 'on', 'in', 'at'],
                ['between', 'against', 'surprise', 'on', 'in', 'at'],
                [],
            ],
            [
                ['great', 'awesome', 'on', 'in'],
                ['between', 'against', 'surprise', 'on', 'in', 'at'],
                ['great', 'awesome'],
            ],
            [
                ['great', 'against', 'awesome', 'between'],
                ['between', 'against', 'surprise', 'on', 'in', 'at'],
                ['great', 'awesome'],
            ],
            [
                ['great', 'awesome', 'cotton'],
                ['between', 'against', 'surprise', 'on', 'in', 'at'],
                ['great', 'awesome', 'cotton'],
            ],
            [
                ['i', '', 'ky', 'u', 'ag', 'vn'],
                ['between', 'against', 'surprise', 'on', 'in', 'at'],
                ['ky', 'ag', 'vn'],
            ],
            [
                ['between', 'against', 'surprise', 'on', 'in', 'at'],
                ['Between', 'Against', 'Surprise', 'On', 'In', 'At'],
                [],
            ],
            [
                ['between', 'against', 'surprise', 'on', 'in', 'at'],
                ['Against', 'ON', 'in'],
                ['between', 'surprise', 'at'],
            ],
        ];
    }

    /**
     * @param list<string> $excludedTerms
     */
    private function updateProductSearchConfig(array $excludedTerms): void
    {
        $this->connection->executeStatement(
            'UPDATE `product_search_config` SET `excluded_terms` = :excludedTerms WHERE `language_id` = UNHEX(:id)',
            [
                'excludedTerms' => json_encode($excludedTerms, \JSON_THROW_ON_ERROR),
                'id' => $this->context->getLanguageId(),
            ]
        );
    }
}
