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

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    /**
     * @dataProvider cases
     */
    public function testExcludedFilterFilter(array $tokens, array $expected): void
    {
        $mockConnection = $this->getMockBuilder(Connection::class)->disableOriginalConstructor()->getMock();
        $mockConnection->expects(static::any())->method('fetchAssoc')->willReturn([
            'excluded_terms' => json_encode([
                'between',
                'against',
                'surprise',
                'on',
                'in',
                'at',
            ]),
            'min_search_length' => 2,
        ]);
        $service = new TokenFilter($mockConnection);
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
            ],
            [
                ['between', 'against', 'surprise', 'on', 'in', 'at'],
                [],
            ],
            [
                ['great', 'awesome', 'on', 'in'],
                ['great', 'awesome'],
            ],
            [
                ['great', 'against', 'awesome', 'between'],
                ['great', 'awesome'],
            ],
            [
                ['great', 'awesome', 'cotton'],
                ['great', 'awesome', 'cotton'],
            ],
            [
                ['i', '', 'ky', 'u', 'ag', 'vn'],
                ['ky', 'ag', 'vn'],
            ],
        ];
    }
}
