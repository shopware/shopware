<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Adapter\Twig;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Twig\Filter\ReplaceRecursiveFilter;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Twig\TwigFilter;

class ReplaceRecursiveFilterTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var ReplaceRecursiveFilter
     */
    private $replaceRecursiveFilter;

    protected function setUp(): void
    {
        $this->replaceRecursiveFilter = new ReplaceRecursiveFilter();
    }

    public function testGetFilterReturnsArrayWithTwigFilter(): void
    {
        $firstTwigFilter = $this->replaceRecursiveFilter->getFilters()[0];
        $this::assertInstanceOf(TwigFilter::class, $firstTwigFilter);
    }

    public function testIfFilterContainsReplaceRecursive(): void
    {
        $replaceRecursiveFilter = array_filter($this->replaceRecursiveFilter->getFilters(), static function ($filter) {
            return $filter->getName() === 'replace_recursive';
        });

        static::assertCount(1, $replaceRecursiveFilter);
    }

    public function testReplaceRecursiveTwoObjects(): void
    {
        $arrayOne = [
            'foo' => 'bar',
            'demo' => true,
            'lorem' => [
                'ipsum' => false,
                'non' => 'dolor',
            ],
        ];

        $arrayTwo = [
            'test' => 'case',
            'lorem' => [
                'non' => 'nononono',
                'dolor' => 'sit',
            ],
        ];

        $expect = [
            'foo' => 'bar',
            'demo' => true,
            'lorem' => [
                'ipsum' => false,
                'non' => 'nononono',
                'dolor' => 'sit',
            ],
            'test' => 'case',
        ];

        $result = $this->replaceRecursiveFilter->replaceRecursive($arrayOne, $arrayTwo);

        $this::assertEquals($expect, $result);
    }

    public function testReplaceRecursiveThreeObjects(): void
    {
        $arrayOne = [
            'foo' => 'bar',
            'demo' => true,
            'lorem' => [
                'ipsum' => false,
                'non' => 'dolor',
            ],
        ];

        $arrayTwo = [
            'test' => 'case',
            'lorem' => [
                'non' => 'nononono',
                'dolor' => 'sit',
                'nested' => [
                    'very' => 'nested',
                ],
            ],
        ];

        $arrayThree = [
            'foo' => 'test',
            'lorem' => [
                'nested' => [
                    'very' => 'very nested',
                    'test' => 'example',
                ],
            ],
        ];

        $expect = [
            'foo' => 'test',
            'demo' => true,
            'lorem' => [
                'ipsum' => false,
                'non' => 'nononono',
                'dolor' => 'sit',
                'nested' => [
                    'very' => 'very nested',
                    'test' => 'example',
                ],
            ],
            'test' => 'case',
        ];

        $result = $this->replaceRecursiveFilter->replaceRecursive($arrayOne, $arrayTwo, $arrayThree);

        $this::assertEquals($expect, $result);
    }
}
