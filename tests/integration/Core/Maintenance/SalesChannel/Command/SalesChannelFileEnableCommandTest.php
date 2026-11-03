<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Maintenance\SalesChannel\Command;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelFileEnableCommand;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
class SalesChannelFileEnableCommandTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private CommandTester $commandTester;

    /**
     * @var EntityRepository<SalesChannelFileCollection>
     */
    private EntityRepository $fileRepository;

    private string $storefrontId;

    protected function setUp(): void
    {
        $this->commandTester = new CommandTester(static::getContainer()->get(SalesChannelFileEnableCommand::class));
        $this->fileRepository = static::getContainer()->get('sales_channel_file.repository');
        $this->storefrontId = $this->salesChannelIdOfType(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);
    }

    public function testItEnablesLlmsTxtAndAgentsMd(): void
    {
        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['ids' => [$this->storefrontId]]));

        static::assertSame(['AGENTS.md' => true, 'llms.txt' => true], $this->files($this->storefrontId));
    }

    public function testAFileTheMerchantSwitchedOffStaysOff(): void
    {
        $this->fileRepository->create([[
            'salesChannelId' => $this->storefrontId,
            'fileFamily' => 'agentic',
            'fileName' => 'agents.md',
            'enabled' => false,
        ]], Context::createDefaultContext());

        $this->commandTester->execute(['ids' => [$this->storefrontId]]);

        static::assertSame(['agents.md' => false, 'llms.txt' => true], $this->files($this->storefrontId));
    }

    public function testASecondRunChangesNothing(): void
    {
        $this->commandTester->execute(['--all' => true]);
        $filesAfterFirstRun = $this->files($this->storefrontId);

        static::assertSame(Command::SUCCESS, $this->commandTester->execute(['--all' => true]));
        static::assertSame(
            'No files were enabled. The files already exist for every selected sales channel.',
            $this->commandTester->getDisplay(),
        );
        static::assertSame($filesAfterFirstRun, $this->files($this->storefrontId));
    }

    /**
     * @return array<string, bool>
     */
    private function files(string $salesChannelId): array
    {
        $criteria = (new Criteria())->addFilter(
            new EqualsFilter('salesChannelId', $salesChannelId),
            new EqualsFilter('fileFamily', 'agentic'),
        );

        $files = [];
        foreach ($this->fileRepository->search($criteria, Context::createDefaultContext())->getEntities() as $file) {
            $files[$file->getFileName()] = $file->isEnabled();
        }
        ksort($files);

        return $files;
    }

    private function salesChannelIdOfType(string $typeId): string
    {
        $salesChannelRepository = static::getContainer()->get('sales_channel.repository');
        static::assertInstanceOf(EntityRepository::class, $salesChannelRepository);
        $criteria = (new Criteria())->addFilter(new EqualsFilter('typeId', $typeId))->setLimit(1);

        $salesChannelId = $salesChannelRepository->searchIds($criteria, Context::createDefaultContext())->firstId();
        static::assertIsString($salesChannelId);
        static::assertTrue(Uuid::isValid($salesChannelId));

        return $salesChannelId;
    }
}
