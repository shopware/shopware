<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search\Term\Filter;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

class TokenFilterTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    /**
     * @dataProvider cases
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

    public function cases(): array
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

    private function updateProductSearchConfig(array $excludedTerms): void
    {
        $this->connection->executeUpdate(
            'UPDATE `product_search_config` SET `excluded_terms` = :excludedTerms WHERE `language_id` = UNHEX(:id)',
            [
                'excludedTerms' => json_encode($excludedTerms),
                'id' => $this->context->getLanguageId(),
            ]
        );
    }
}
