<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\SalesChannel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelReplaceUrlCommand;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelReplaceUrlCommand::class)]
class SalesChannelReplaceUrlCommandTest extends TestCase
{
    public function testExecuteSuccessfullyReplacesUrl(): void
    {
        $domainId = Uuid::randomHex();
        $previousUrl = 'https://old-domain.com';
        $newUrl = 'https://new-domain.com';

        $domainEntity = new SalesChannelDomainEntity();
        $domainEntity->setId($domainId);
        $domainEntity->setUrl($previousUrl);

        $searchResultMock = $this->createMock(EntitySearchResult::class);
        $searchResultMock->method('first')
            ->willReturn($domainEntity);

        $repositoryMock = $this->createMock(EntityRepository::class);
        $repositoryMock->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static function (Criteria $criteria) use ($previousUrl) {
                    $filters = $criteria->getFilters();
                    static::assertCount(1, $filters);
                    static::assertInstanceOf(EqualsFilter::class, $filters[0]);
                    static::assertSame('url', $filters[0]->getField());
                    static::assertSame($previousUrl, $filters[0]->getValue());
                    static::assertSame(1, $criteria->getLimit());

                    return true;
                }),
                static::isInstanceOf(Context::class)
            )
            ->willReturn($searchResultMock);

        $repositoryMock->expects($this->once())
            ->method('update')
            ->with(
                [[
                    'id' => $domainId,
                    'url' => $newUrl,
                ]],
                static::isInstanceOf(Context::class)
            );

        $command = new SalesChannelReplaceUrlCommand($repositoryMock);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'previous-url' => $previousUrl,
            'new-url' => $newUrl,
        ]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    public function testExecuteFailsWhenDomainNotFound(): void
    {
        $previousUrl = 'https://non-existent-domain.com';
        $newUrl = 'https://new-domain.com';

        $searchResultMock = $this->createMock(EntitySearchResult::class);
        $searchResultMock->method('first')
            ->willReturn(null);

        $repositoryMock = $this->createMock(EntityRepository::class);
        $repositoryMock->expects($this->once())
            ->method('search')
            ->willReturn($searchResultMock);

        $repositoryMock->expects($this->never())
            ->method('update');

        $command = new SalesChannelReplaceUrlCommand($repositoryMock);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'previous-url' => $previousUrl,
            'new-url' => $newUrl,
        ]);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }

    /**
     * @param array{previous-url: string, new-url: string} $arguments
     */
    #[DataProvider('invalidUrlDataProvider')]
    public function testExecuteFailsWithInvalidUrls(array $arguments): void
    {
        $repositoryMock = $this->createMock(EntityRepository::class);
        $repositoryMock->expects($this->never())
            ->method('search');
        $repositoryMock->expects($this->never())
            ->method('update');

        $command = new SalesChannelReplaceUrlCommand($repositoryMock);

        $commandTester = new CommandTester($command);
        $commandTester->execute($arguments);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
    }

    public function testExecuteTrimsInputUrls(): void
    {
        $domainId = Uuid::randomHex();
        $previousUrl = 'https://old-domain.com';
        $newUrl = 'https://new-domain.com';

        $domainEntity = new SalesChannelDomainEntity();
        $domainEntity->setId($domainId);
        $domainEntity->setUrl($previousUrl);

        $searchResultMock = $this->createMock(EntitySearchResult::class);
        $searchResultMock->method('first')
            ->willReturn($domainEntity);

        $repositoryMock = $this->createMock(EntityRepository::class);
        $repositoryMock->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static function (Criteria $criteria) use ($previousUrl) {
                    $filters = $criteria->getFilters();
                    static::assertCount(1, $filters);
                    $filter = $filters[0];
                    static::assertInstanceOf(EqualsFilter::class, $filter);
                    static::assertSame($previousUrl, $filter->getValue());

                    return true;
                }),
                static::isInstanceOf(Context::class)
            )
            ->willReturn($searchResultMock);

        $repositoryMock->expects($this->once())
            ->method('update')
            ->with(
                [[
                    'id' => $domainId,
                    'url' => $newUrl,
                ]],
                static::isInstanceOf(Context::class)
            );

        $command = new SalesChannelReplaceUrlCommand($repositoryMock);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'previous-url' => '  ' . $previousUrl . '  ',
            'new-url' => '  ' . $newUrl . '  ',
        ]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    /**
     * @return \Generator<string, array{arguments: array{previous-url: string, new-url: string}}>
     */
    public static function invalidUrlDataProvider(): \Generator
    {
        yield 'Empty previous URL' => [
            'arguments' => [
                'previous-url' => '',
                'new-url' => 'https://new-domain.com',
            ],
        ];

        yield 'Whitespace-only previous URL' => [
            'arguments' => [
                'previous-url' => '   ',
                'new-url' => 'https://new-domain.com',
            ],
        ];

        yield 'Invalid new URL' => [
            'arguments' => [
                'previous-url' => 'https://old-domain.com',
                'new-url' => 'not-a-valid-url',
            ],
        ];

        yield 'Identical URLs' => [
            'arguments' => [
                'previous-url' => 'https://same-domain.com',
                'new-url' => 'https://same-domain.com',
            ],
        ];

        yield 'Identical URLs with different spacing' => [
            'arguments' => [
                'previous-url' => '  https://same-domain.com  ',
                'new-url' => 'https://same-domain.com',
            ],
        ];
    }
}
