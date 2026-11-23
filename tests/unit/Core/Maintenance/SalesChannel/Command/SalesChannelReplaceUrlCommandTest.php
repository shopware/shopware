<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\SalesChannel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelReplaceUrlCommand;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
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

        $repository = StaticEntityRepository::of(SalesChannelDomainCollection::class, [[$domainId]]);

        $command = new SalesChannelReplaceUrlCommand($repository);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'previous-url' => $previousUrl,
            'new-url' => $newUrl,
        ]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertCount(1, $repository->searchedCriteria);
        static::assertEquals([new EqualsFilter('url', $previousUrl)], $repository->searchedCriteria[0]->getFilters());
        static::assertSame(1, $repository->searchedCriteria[0]->getLimit());
        static::assertSame([[['id' => $domainId, 'url' => $newUrl]]], $repository->updates);
    }

    public function testExecuteFailsWhenDomainNotFound(): void
    {
        $repository = StaticEntityRepository::of(SalesChannelDomainCollection::class, [[]]);

        $command = new SalesChannelReplaceUrlCommand($repository);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'previous-url' => 'https://non-existent-domain.com',
            'new-url' => 'https://new-domain.com',
        ]);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        static::assertCount(1, $repository->searchedCriteria);
        static::assertSame([], $repository->updates);
    }

    /**
     * @param array{previous-url: string, new-url: string} $arguments
     */
    #[DataProvider('invalidUrlDataProvider')]
    public function testExecuteFailsWithInvalidUrls(array $arguments): void
    {
        $repository = StaticEntityRepository::of(SalesChannelDomainCollection::class);

        $command = new SalesChannelReplaceUrlCommand($repository);

        $commandTester = new CommandTester($command);
        $commandTester->execute($arguments);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        static::assertSame([], $repository->searchedCriteria);
        static::assertSame([], $repository->updates);
    }

    public function testExecuteTrimsInputUrls(): void
    {
        $domainId = Uuid::randomHex();
        $previousUrl = 'https://old-domain.com';
        $newUrl = 'https://new-domain.com';

        $repository = StaticEntityRepository::of(SalesChannelDomainCollection::class, [[$domainId]]);

        $command = new SalesChannelReplaceUrlCommand($repository);

        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'previous-url' => '  ' . $previousUrl . '  ',
            'new-url' => '  ' . $newUrl . '  ',
        ]);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertEquals([new EqualsFilter('url', $previousUrl)], $repository->searchedCriteria[0]->getFilters());
        static::assertSame([[['id' => $domainId, 'url' => $newUrl]]], $repository->updates);
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
