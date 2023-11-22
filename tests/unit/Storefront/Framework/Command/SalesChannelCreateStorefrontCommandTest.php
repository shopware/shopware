<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\SalesChannel\Service\SalesChannelCreator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Framework\Command\SalesChannelCreateStorefrontCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(SalesChannelCreateStorefrontCommand::class)]
class SalesChannelCreateStorefrontCommandTest extends TestCase
{
    /**
     * @param array<IdSearchResult> $idsSearchResult
     */
    #[DataProvider('dataProviderTestExecuteCommandSuccessful')]
    public function testExecuteCommandSuccessful(?string $snippetSetId = null, ?string $isoCode = null, array $idsSearchResult = []): void
    {
        $snippetSetRepository = new StaticEntityRepository($idsSearchResult);

        $foundSnippetSetId = $snippetSetId;
        if (!$foundSnippetSetId) {
            /** @var IdSearchResult $idSearchResult */
            foreach ($idsSearchResult as $idSearchResult) {
                $foundSnippetSetId = $idSearchResult->firstId() ?: $foundSnippetSetId;
            }
        }

        $mockSalesChannelCreator = $this->createMock(SalesChannelCreator::class);

        $mockSalesChannelCreator->expects(static::once())
            ->method('createSalesChannel')
            ->with(
                'id',
                'name',
                Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
                'languageId',
                'currencyId',
                'paymentMethodId',
                'shippingMethodId',
                'countryId',
                'customerGroupId',
                'navigationCategoryId',
                null,
                null,
                null,
                null,
                null,
                [
                    'domains' => [
                        [
                            'url' => 'url',
                            'languageId' => 'languageId',
                            'snippetSetId' => $foundSnippetSetId,
                            'currencyId' => 'currencyId',
                        ],
                    ],
                    'navigationCategoryDepth' => 3,
                    'name' => 'name',
                ]
            );

        $cmd = new SalesChannelCreateStorefrontCommand(
            $snippetSetRepository,
            $mockSalesChannelCreator
        );

        $inputs = array_merge(
            [
                'id',
                null, // typeId
                'name',
                'languageId',
                'currencyId',
                'paymentMethodId',
                'shippingMethodId',
                'countryId',
                'customerGroupId',
                'navigationCategoryId',
                $snippetSetId,
            ],
            $snippetSetId ? [] : [$isoCode],
            [
                'url',
                'languageId',
                'currencyId',
                'name',
            ]
        );

        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')
            ->willReturn(...$inputs);

        $output = $this->createStub(OutputInterface::class);

        $status = $cmd->run($input, $output);

        static::assertEquals(SalesChannelCreateStorefrontCommand::SUCCESS, $status);
    }

    /**
     * @param array<IdSearchResult> $idsSearchResult
     */
    #[DataProvider('dataProviderTestExecuteCommandWithAnException')]
    public function testExecuteCommandWithAnException(?string $snippetSetId = null, ?string $isoCode = null, array $idsSearchResult = []): void
    {
        $snippetSetRepository = new StaticEntityRepository($idsSearchResult);

        $mockSalesChannelCreator = $this->createStub(SalesChannelCreator::class);

        $cmd = new SalesChannelCreateStorefrontCommand(
            $snippetSetRepository,
            $mockSalesChannelCreator
        );

        $inputs = [
            'id',
            null, // typeId
            'name',
            'languageId',
            'currencyId',
            'paymentMethodId',
            'shippingMethodId',
            'countryId',
            'customerGroupId',
            'navigationCategoryId',
            $snippetSetId,
            $isoCode,
            'url',
            'languageId',
            'currencyId',
            'name',
        ];

        $input = $this->createMock(InputInterface::class);
        $input->method('getOption')
            ->willReturn(...$inputs);

        $output = $this->createStub(OutputInterface::class);

        $this->expectExceptionMessage(sprintf('Snippet set with isoCode %s cannot be found.', $isoCode));

        $cmd->run($input, $output);
    }

    public static function dataProviderTestExecuteCommandSuccessful(): \Generator
    {
        yield 'with snippetSetId input' => [
            'snippetSetId' => 'snippetSetId',
            'isoCode' => null,
            'idSearchResult' => [],
            'exception' => null,
        ];

        yield 'with valid isoCode' => [
            'snippetSetId' => null,
            'isoCode' => 'de-DE',
            'idSearchResult' => [
                new IdSearchResult(1, [['primaryKey' => 'snippetSetId', 'data' => []]], new Criteria(), Context::createDefaultContext()),
            ],
            'exception' => null,
        ];

        yield 'with not found isoCode, use en-GB as fallback' => [
            'snippetSetId' => null,
            'isoCode' => 'nl-NL',
            'idSearchResult' => [
                new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()),
                new IdSearchResult(1, [['primaryKey' => 'snippetSetId', 'data' => []]], new Criteria(), Context::createDefaultContext()),
            ],
            'exception' => null,
        ];
    }

    public static function dataProviderTestExecuteCommandWithAnException(): \Generator
    {
        yield 'with not found fallback isoCode, throw exception' => [
            'snippetSetId' => null,
            'isoCode' => 'nl-NL',
            'idSearchResult' => [
                new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()),
                new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext()),
            ],
            'exception' => 'Snippet set with isoCode nl-NL cannot be found.',
        ];
    }
}
