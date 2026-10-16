<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\SalesChannel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelFileEnableCommand;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelFileEnableCommand::class)]
class SalesChannelFileEnableCommandTest extends TestCase
{
    private const STOREFRONT_ID = '0191a1b2c3d4e5f60718293a4b5c6d7e';

    private const HEADLESS_ID = '0191a1b2c3d4e5f60718293a4b5c6d7f';

    public function testWithoutIdsOrAllNothingIsEnabled(): void
    {
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class);
        $fileRepository = StaticEntityRepository::of(SalesChannelFileCollection::class);

        $tester = new CommandTester(new SalesChannelFileEnableCommand($salesChannelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute([]));
        static::assertSame('No files were enabled. Provide id(s) or run with --all option.', $tester->getDisplay());
        static::assertSame([], $fileRepository->creates);
    }

    public function testItCreatesTheMissingFilesForTheSelectedSalesChannels(): void
    {
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class, [[self::STOREFRONT_ID, self::HEADLESS_ID]]);
        $fileRepository = new StaticEntityRepository([new SalesChannelFileCollection()]);

        $tester = new CommandTester(new SalesChannelFileEnableCommand($salesChannelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => [self::STOREFRONT_ID, self::HEADLESS_ID]]));
        static::assertSame('Enabled 4 file(s) for 2 sales channel(s)', $tester->getDisplay());
        static::assertSame([[
            ['salesChannelId' => self::STOREFRONT_ID, 'fileFamily' => 'agentic', 'fileName' => 'llms.txt', 'enabled' => true],
            ['salesChannelId' => self::STOREFRONT_ID, 'fileFamily' => 'agentic', 'fileName' => 'AGENTS.md', 'enabled' => true],
            ['salesChannelId' => self::HEADLESS_ID, 'fileFamily' => 'agentic', 'fileName' => 'llms.txt', 'enabled' => true],
            ['salesChannelId' => self::HEADLESS_ID, 'fileFamily' => 'agentic', 'fileName' => 'AGENTS.md', 'enabled' => true],
        ]], $fileRepository->creates);
    }

    public function testAFileTheMerchantSwitchedOffStaysOff(): void
    {
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class, [[self::STOREFRONT_ID]]);
        $fileRepository = new StaticEntityRepository([new SalesChannelFileCollection([
            $this->file(self::STOREFRONT_ID, 'llms.txt', enabled: false),
        ])]);

        $tester = new CommandTester(new SalesChannelFileEnableCommand($salesChannelRepository, $fileRepository));
        $tester->execute(['--all' => true]);

        static::assertSame('Enabled 1 file(s) for 1 sales channel(s)', $tester->getDisplay());
        static::assertSame([[
            ['salesChannelId' => self::STOREFRONT_ID, 'fileFamily' => 'agentic', 'fileName' => 'AGENTS.md', 'enabled' => true],
        ]], $fileRepository->creates);
        static::assertSame([], $fileRepository->updates);
    }

    public function testALowercaseAgentsMdRowCountsAsExisting(): void
    {
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class, [[self::STOREFRONT_ID]]);
        $fileRepository = new StaticEntityRepository([new SalesChannelFileCollection([
            $this->file(self::STOREFRONT_ID, 'llms.txt', enabled: true),
            $this->file(self::STOREFRONT_ID, 'agents.md', enabled: true),
        ])]);

        $tester = new CommandTester(new SalesChannelFileEnableCommand($salesChannelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        static::assertSame('No files were enabled. The files already exist for every selected sales channel.', $tester->getDisplay());
        static::assertSame([], $fileRepository->creates);
    }

    public function testOnlyStorefrontAndHeadlessSalesChannelsAreSelected(): void
    {
        $selectedTypes = null;
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class, [
            static function (Criteria $criteria) use (&$selectedTypes): array {
                $filter = $criteria->getFilters()[0] ?? null;
                static::assertInstanceOf(EqualsAnyFilter::class, $filter);
                $selectedTypes = $filter->getValue();

                return [];
            },
        ]);
        $fileRepository = StaticEntityRepository::of(SalesChannelFileCollection::class);

        $tester = new CommandTester(new SalesChannelFileEnableCommand($salesChannelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['--all' => true]));
        static::assertSame('No files were enabled. No Storefront or Headless sales channel matches.', $tester->getDisplay());
        static::assertSame([Defaults::SALES_CHANNEL_TYPE_STOREFRONT, Defaults::SALES_CHANNEL_TYPE_API], $selectedTypes);
        static::assertSame([], $fileRepository->creates);
    }

    public function testUppercaseIdsAreSearchedInLowercase(): void
    {
        $searchedIds = null;
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class, [
            static function (Criteria $criteria) use (&$searchedIds): array {
                $searchedIds = $criteria->getIds();

                return [self::STOREFRONT_ID];
            },
        ]);
        $fileRepository = new StaticEntityRepository([new SalesChannelFileCollection()]);

        $tester = new CommandTester(new SalesChannelFileEnableCommand($salesChannelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => [strtoupper(self::STOREFRONT_ID)]]));
        static::assertSame([self::STOREFRONT_ID], $searchedIds);
        static::assertSame('Enabled 2 file(s) for 1 sales channel(s)', $tester->getDisplay());
    }

    public function testInvalidIdsAreReportedAndIgnored(): void
    {
        $searchedIds = null;
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class, [
            static function (Criteria $criteria) use (&$searchedIds): array {
                $searchedIds = $criteria->getIds();

                return [self::STOREFRONT_ID];
            },
        ]);
        $fileRepository = new StaticEntityRepository([new SalesChannelFileCollection()]);

        $tester = new CommandTester(new SalesChannelFileEnableCommand($salesChannelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => ['foo', self::STOREFRONT_ID]]));
        static::assertSame([self::STOREFRONT_ID], $searchedIds);
        static::assertSame(
            'Ignored invalid id(s): foo' . \PHP_EOL . 'Enabled 2 file(s) for 1 sales channel(s)',
            $tester->getDisplay(),
        );
    }

    public function testIdsWithoutAStorefrontOrHeadlessSalesChannelAreReported(): void
    {
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class, [[self::STOREFRONT_ID]]);
        $fileRepository = new StaticEntityRepository([new SalesChannelFileCollection()]);

        $tester = new CommandTester(new SalesChannelFileEnableCommand($salesChannelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => [self::STOREFRONT_ID, self::HEADLESS_ID]]));
        static::assertSame(
            'Ignored id(s) that match no Storefront or Headless sales channel: ' . self::HEADLESS_ID . \PHP_EOL
            . 'Enabled 2 file(s) for 1 sales channel(s)',
            $tester->getDisplay(),
        );
    }

    public function testOnlyInvalidIdsDoNotSelectEverySalesChannel(): void
    {
        $salesChannelRepository = StaticEntityRepository::of(SalesChannelCollection::class);
        $fileRepository = StaticEntityRepository::of(SalesChannelFileCollection::class);

        $tester = new CommandTester(new SalesChannelFileEnableCommand($salesChannelRepository, $fileRepository));

        static::assertSame(Command::SUCCESS, $tester->execute(['ids' => ['foo']]));
        static::assertSame(
            'Ignored invalid id(s): foo' . \PHP_EOL . 'No files were enabled. No Storefront or Headless sales channel matches.',
            $tester->getDisplay(),
        );
        static::assertSame([], $fileRepository->creates);
    }

    private function file(string $salesChannelId, string $fileName, bool $enabled): SalesChannelFileEntity
    {
        $file = new SalesChannelFileEntity();
        $file->setId(Uuid::randomHex());
        $file->setSalesChannelId($salesChannelId);
        $file->setFileFamily('agentic');
        $file->setFileName($fileName);
        $file->setEnabled($enabled);

        return $file;
    }
}
