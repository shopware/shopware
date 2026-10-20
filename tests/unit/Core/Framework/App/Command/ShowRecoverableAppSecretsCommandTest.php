<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Command\ShowRecoverableAppSecretsCommand;
use Shopware\Core\Framework\App\RetainedSecret\AppRetainedSecretGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ShowRecoverableAppSecretsCommand::class)]
class ShowRecoverableAppSecretsCommandTest extends TestCase
{
    public function testPrintsLiveThenRetainedSecretsNewestFirstWithAWarning(): void
    {
        $tester = new CommandTester($this->createCommand($this->installedApp(), $this->retainedRows()));

        $tester->execute(['name' => 'TestApp']);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        $display = $tester->getDisplay();
        static::assertStringContainsString('contains live app secrets', $display);

        $positions = array_map(
            static fn (string $secret): int|false => strpos($display, $secret),
            ['pending-newest', 'pending-oldest', 'committed-secret', 'retained-newer', 'retained-older']
        );
        static::assertNotContains(false, $positions);
        $sorted = $positions;
        sort($sorted);
        static::assertSame($sorted, $positions, 'secrets must be listed live first, then retained, newest first');

        static::assertStringContainsString('current', $display);
        static::assertStringContainsString('old-shop-1', $display);
        static::assertStringContainsString('2026-01-04T12:00:00+00:00', $display);
    }

    public function testRawPrintsOnlyTheSecretsOnePerLine(): void
    {
        $tester = new CommandTester($this->createCommand($this->installedApp(), $this->retainedRows()));

        $tester->execute(['name' => 'TestApp', '--raw' => true]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertSame(
            "pending-newest\npending-oldest\ncommitted-secret\nretained-newer\nretained-older\n",
            $tester->getDisplay()
        );
    }

    public function testListsRetainedSecretsOfAnAppThatIsNotInstalled(): void
    {
        $tester = new CommandTester($this->createCommand(null, $this->retainedRows()));

        $tester->execute(['name' => 'TestApp', '--raw' => true]);

        static::assertSame(Command::SUCCESS, $tester->getStatusCode());
        static::assertSame("retained-newer\nretained-older\n", $tester->getDisplay());
    }

    public function testFailsWhenTheAppIsNotInstalledAndNothingIsRetained(): void
    {
        $tester = new CommandTester($this->createCommand(null, []));

        $tester->execute(['name' => 'TestApp']);

        static::assertSame(Command::FAILURE, $tester->getStatusCode());
        static::assertStringContainsString('No secrets found for app "TestApp"', $tester->getDisplay());
    }

    /**
     * @param list<array{shopId: string, secret: string, expiresAt: \DateTimeImmutable}> $retained
     */
    private function createCommand(?AppEntity $app, array $retained): ShowRecoverableAppSecretsCommand
    {
        $gateway = static::createStub(AppRetainedSecretGateway::class);
        $gateway->method('findValid')->willReturn($retained);

        return new ShowRecoverableAppSecretsCommand(
            new StaticEntityRepository([new AppCollection($app === null ? [] : [$app])]),
            $gateway,
        );
    }

    private function installedApp(): AppEntity
    {
        $app = new AppEntity();
        $app->setId(Uuid::randomHex());
        $app->setName('TestApp');
        $app->setAppSecret('committed-secret');
        $app->setUnconfirmedAppSecrets(['pending-newest', 'pending-oldest']);

        return $app;
    }

    /**
     * @return list<array{shopId: string, secret: string, expiresAt: \DateTimeImmutable}>
     */
    private function retainedRows(): array
    {
        return [
            ['shopId' => 'old-shop-1', 'secret' => 'retained-newer', 'expiresAt' => new \DateTimeImmutable('2026-01-04 12:00:00', new \DateTimeZone('UTC'))],
            ['shopId' => 'old-shop-2', 'secret' => 'retained-older', 'expiresAt' => new \DateTimeImmutable('2026-01-03 12:00:00', new \DateTimeZone('UTC'))],
        ];
    }
}
