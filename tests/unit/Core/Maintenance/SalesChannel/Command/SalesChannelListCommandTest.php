<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\SalesChannel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelListCommand;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelListCommand::class)]
class SalesChannelListCommandTest extends TestCase
{
    public function testNoValidationErrors(): void
    {
        $id = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setUniqueIdentifier($id);
        $salesChannel->setId($id);
        $salesChannel->setActive(true);
        $salesChannel->setMaintenance(false);

        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection([$salesChannel])], new SalesChannelDefinition());

        $command = new SalesChannelListCommand($salesChannelRepository);

        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        static::assertSame(
            0,
            $commandTester->getStatusCode(),
            "\"bin/console sales-channel:list\" returned errors:\n" . $commandTester->getDisplay()
        );
        $output = ' ---------------------------------- ------ -------- ------------- ------------------ ----------- ------------------ ------------ ---------
  id                                 Name   Active   Maintenance   Default Language   Languages   Default Currency   Currencies   Domains
 ---------------------------------- ------ -------- ------------- ------------------ ----------- ------------------ ------------ ---------
  %s   n/a    active   off           n/a                            n/a
 ---------------------------------- ------ -------- ------------- ------------------ ----------- ------------------ ------------ ---------

';
        static::assertSame(\sprintf($output, $id), preg_replace('/ +$/m', '', $commandTester->getDisplay()));
    }

    /**
     * @param list<string> $expectedLines
     */
    #[DataProvider('domainSeparatorProvider')]
    public function testDomainsAreListedSeparately(string $format, array $expectedLines): void
    {
        $id = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setUniqueIdentifier($id);
        $salesChannel->setId($id);
        $salesChannel->setActive(true);
        $salesChannel->setMaintenance(false);
        $salesChannel->setDomains(new SalesChannelDomainCollection(array_map(static function (string $url): SalesChannelDomainEntity {
            $domain = new SalesChannelDomainEntity();
            $domain->setId(Uuid::randomHex());
            $domain->setUrl($url);

            return $domain;
        }, ['http://a.test', 'http://b.test'])));

        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection([$salesChannel])], new SalesChannelDefinition());

        $commandTester = new CommandTester(new SalesChannelListCommand($salesChannelRepository));
        $commandTester->execute(['--format' => $format]);

        $commandTester->assertCommandIsSuccessful();
        foreach ($expectedLines as $expectedLine) {
            static::assertMatchesRegularExpression($expectedLine, $commandTester->getDisplay());
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function domainSeparatorProvider(): iterable
    {
        yield 'table' => ['table', ['/^  [0-9a-f]{32} .* http:\/\/a\.test +$/m', '/^ +http:\/\/b\.test +$/m']];
        yield 'markdown' => ['markdown', ['/^\| [0-9a-f]{32} \|.*\| http:\/\/a\.test<br>http:\/\/b\.test \|$/m']];
    }

    public function testFormatJsonOutput(): void
    {
        $id = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setUniqueIdentifier($id);
        $salesChannel->setId($id);
        $salesChannel->setActive(true);
        $salesChannel->setMaintenance(false);

        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection([$salesChannel])], new SalesChannelDefinition());

        $command = new SalesChannelListCommand($salesChannelRepository);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--format' => 'json']);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertJson($commandTester->getDisplay());
        static::assertStringContainsString($id, $commandTester->getDisplay());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove together with `--output` option
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testOutputJsonDeprecated(): void
    {
        $id = Uuid::randomHex();

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setUniqueIdentifier($id);
        $salesChannel->setId($id);
        $salesChannel->setActive(true);
        $salesChannel->setMaintenance(false);

        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection([$salesChannel])], new SalesChannelDefinition());

        $command = new SalesChannelListCommand($salesChannelRepository);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--output' => 'json']);

        static::assertSame(0, $commandTester->getStatusCode());
        static::assertJson($commandTester->getDisplay());
        static::assertStringContainsString($id, $commandTester->getDisplay());
    }

    public function testInvalidFormatReturnsError(): void
    {
        $salesChannelRepository = new StaticEntityRepository([new SalesChannelCollection([])], new SalesChannelDefinition());

        $command = new SalesChannelListCommand($salesChannelRepository);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['--format' => 'xml']);

        static::assertSame(2, $commandTester->getStatusCode());
        static::assertStringContainsString('Invalid format "xml"', $commandTester->getDisplay());
    }
}
