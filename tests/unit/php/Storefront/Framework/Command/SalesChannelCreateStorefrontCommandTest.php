<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Shopware\Storefront\Framework\Command\SalesChannelCreateStorefrontCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @package system-settings
 *
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Command\SalesChannelCreateStorefrontCommand
 */
class SalesChannelCreateStorefrontCommandTest extends TestCase
{
    /**
     * @dataProvider dataProviderTestGetSnippetSetId
     */
    public function testGetSnippetSetId(string $expected, string $snippetSetId): void
    {
        $mockSalesChannelCreator = $this->createStub(SalesChannelCreator::class);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')
            ->willReturn($snippetSetId);

        $snippetSetRepository = $this->createMock(EntityRepository::class);
        $snippetSetRepository->method('searchIds')
            ->willReturn($idSearchResult);

        $cmd = new SalesChannelCreateStorefrontCommand(
            $snippetSetRepository,
            $mockSalesChannelCreator
        );

        $reflectionMethod = ReflectionHelper::getMethod(SalesChannelCreateStorefrontCommand::class, 'getSnippetSetId');

        $result = $reflectionMethod->invoke($cmd, 'de_DE');

        static::assertEquals($expected, $result);
    }

    public function testGetSnippetSetIdWithException(): void
    {
        $mockEntityRepository = $this->createStub(EntityRepository::class);
        $mockSalesChannelCreator = $this->createStub(SalesChannelCreator::class);

        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')
            ->willReturn(null);

        $snippetSetRepository = $this->createMock(EntityRepository::class);
        $snippetSetRepository->method('searchIds')
            ->willReturn($idSearchResult);

        $cmd = new SalesChannelCreateStorefrontCommand(
            $snippetSetRepository,
            $mockSalesChannelCreator
        );

        $reflectionMethod = ReflectionHelper::getMethod(SalesChannelCreateStorefrontCommand::class, 'getSnippetSetId');

        $this->expectExceptionMessage('Unable to get default SnippetSet. Please provide a valid SnippetSetId.');
        $reflectionMethod->invoke($cmd, 'yx-XY');
    }

    public function testGetTypeId(): void
    {
        $mockEntityRepository = $this->createStub(EntityRepository::class);
        $mockSalesChannelCreator = $this->createStub(SalesChannelCreator::class);

        $cmd = new SalesChannelCreateStorefrontCommand(
            $mockEntityRepository,
            $mockSalesChannelCreator
        );

        $reflectionMethod = ReflectionHelper::getMethod(SalesChannelCreateStorefrontCommand::class, 'getTypeId');

        $result = $reflectionMethod->invoke($cmd);

        static::assertEquals(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, $result);
    }

    /**
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $mockInputValues
     *
     * @dataProvider dataProviderTestGetSalesChannelConfiguration
     */
    public function testGetSalesChannelConfiguration(array $expected, array $mockInputValues, string $snippetSetId): void
    {
        $idSearchResult = $this->createMock(IdSearchResult::class);
        $idSearchResult->method('firstId')
            ->willReturn($snippetSetId);

        $snippetSetRepository = $this->createMock(EntityRepository::class);
        $snippetSetRepository->method('searchIds')
            ->willReturn($idSearchResult);

        $mockSalesChannelCreator = $this->createStub(SalesChannelCreator::class);

        $cmd = new SalesChannelCreateStorefrontCommand(
            $snippetSetRepository,
            $mockSalesChannelCreator
        );

        $reflectionMethod = ReflectionHelper::getMethod(SalesChannelCreateStorefrontCommand::class, 'getSalesChannelConfiguration');

        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')
            ->willReturn(...array_values($mockInputValues));

        $output = $this->createStub(OutputInterface::class);

        $result = $reflectionMethod->invoke($cmd, $input, $output);

        static::assertEquals($expected, $result);
    }

    public static function dataProviderTestGetSalesChannelConfiguration(): \Generator
    {
        $url = 'http://localhost';
        $languageId = Uuid::randomHex();
        $snippetSetId = Uuid::randomHex();
        $currencyId = Uuid::randomHex();
        $name = 'Storefront';

        yield 'Data provider for testing get sale channel configuration' => [
            'Expected result' => [
                'domains' => [[
                    'url' => $url,
                    'languageId' => $languageId,
                    'snippetSetId' => $snippetSetId,
                    'currencyId' => $currencyId,
                ]],
                'navigationCategoryDepth' => 3,
                'name' => $name,
            ],
            'Mock method getOption from input' => [
                'snippetSetId' => null,
                'isoCode' => 'de-DE',
                'url' => $url,
                'languageId' => $languageId,
                'currencyId' => $currencyId,
                'name' => $name,
            ],
            'expected snippet set ID' => $snippetSetId,
        ];
    }

    public static function dataProviderTestGetSnippetSetId(): \Generator
    {
        $snippetSetId = Uuid::randomHex();

        yield 'Data provider for testing get snippet set ID' => [
            'expected' => $snippetSetId,
            'snippetSetId' => $snippetSetId,
        ];
    }
}
