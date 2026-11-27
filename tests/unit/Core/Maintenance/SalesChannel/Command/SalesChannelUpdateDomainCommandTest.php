<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Maintenance\SalesChannel\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Maintenance\SalesChannel\Command\SalesChannelUpdateDomainCommand;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SalesChannelUpdateDomainCommand::class)]
class SalesChannelUpdateDomainCommandTest extends TestCase
{
    public function testReplacesTheHostOfAllDomains(): void
    {
        $german = $this->createDomain('https://brand-a.example/de');
        $english = $this->createDomain('https://brand-a.example/en?lang=en');

        $repository = new StaticEntityRepository([new SalesChannelDomainCollection([$german, $english])]);

        $commandTester = new CommandTester(new SalesChannelUpdateDomainCommand($repository));
        $commandTester->execute(['domain' => 'staging.example']);

        static::assertSame(Command::SUCCESS, $commandTester->getStatusCode());
        static::assertSame([[
            ['id' => $german->getId(), 'url' => 'https://staging.example/de'],
            ['id' => $english->getId(), 'url' => 'https://staging.example/en?lang=en'],
        ]], $repository->updates);
    }

    public function testFailsWithoutUpdatingWhenSeveralDomainsWouldGetTheSameUrl(): void
    {
        $brandA = $this->createDomain('https://brand-a.example/de');
        $brandB = $this->createDomain('https://brand-b.example/DE');
        $brandAEnglish = $this->createDomain('https://brand-a.example/en');

        $repository = new StaticEntityRepository([new SalesChannelDomainCollection([$brandA, $brandB, $brandAEnglish])]);

        $commandTester = new CommandTester(new SalesChannelUpdateDomainCommand($repository));
        $commandTester->execute(['domain' => 'staging.example']);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        static::assertSame([], $repository->updates);

        $display = $commandTester->getDisplay();
        static::assertStringContainsString('https://staging.example/de', $display);
        static::assertStringContainsString('https://brand-a.example/de', $display);
        static::assertStringContainsString('https://brand-b.example/DE', $display);
        static::assertStringNotContainsString('https://brand-a.example/en', $display);
    }

    public function testFailsWithoutUpdatingWhenTheNewUrlBelongsToADomainThatIsNotUpdated(): void
    {
        $alreadyMoved = $this->createDomain('https://staging.example/de');
        $brandB = $this->createDomain('https://brand-b.example/de');

        $repository = new StaticEntityRepository([new SalesChannelDomainCollection([$alreadyMoved, $brandB])]);

        $commandTester = new CommandTester(new SalesChannelUpdateDomainCommand($repository));
        $commandTester->execute(['domain' => 'staging.example', '--previous-domain' => 'brand-b.example']);

        static::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        static::assertSame([], $repository->updates);
        static::assertStringContainsString('https://brand-b.example/de', $commandTester->getDisplay());
    }

    private function createDomain(string $url): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl($url);

        return $domain;
    }
}
