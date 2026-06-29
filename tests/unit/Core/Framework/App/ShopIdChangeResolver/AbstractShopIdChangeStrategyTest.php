<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\ShopIdChangeResolver;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppCollection;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Lifecycle\AppSecretRotationService;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\ShopIdChangeResolver\AbstractShopIdChangeStrategy;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\Filesystem;
use Shopware\Core\Test\Stub\App\StaticSourceResolver;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Tests\Unit\Core\Framework\App\AppFixture;

/**
 * @internal
 */
#[CoversClass(AbstractShopIdChangeStrategy::class)]
class AbstractShopIdChangeStrategyTest extends TestCase
{
    public function testForEachInstalledAppKeepsGoingWhenOneAppFailsAndReportsTheFailures(): void
    {
        $context = Context::createDefaultContext();
        $appOne = AppFixture::createAppEntity(name: 'app-one', id: 'app-one-id');
        $appTwo = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        // Both apps resolve to the same fixture manifest, which declares <setup> so the callback runs for each.
        $fixtureFs = new Filesystem(__DIR__ . '/../Manifest/_fixtures/test');
        $sourceResolver = new StaticSourceResolver(['app-one' => $fixtureFs, 'app-two' => $fixtureFs]);

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$appOne, $appTwo])]);

        // The batch must not abort on the first failure: rotateNow is attempted for BOTH apps (exactly(2))
        // even though the first one throws.
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->exactly(2))
            ->method('rotateNow')
            ->willReturnCallback(function (string $appId): void {
                if ($appId === 'app-one-id') {
                    throw AppException::registrationFailed('app-one', 'app server unreachable');
                }
            });

        $strategy = new class($sourceResolver, $appRepository, $rotationService) extends AbstractShopIdChangeStrategy {
            public function getName(): string
            {
                return 'test';
            }

            public function getDescription(): string
            {
                return 'test';
            }

            public function getDecorated(): AbstractShopIdChangeStrategy
            {
                throw new DecorationPatternException(self::class);
            }

            public function resolve(Context $context): void
            {
                $this->forEachInstalledApp($context, function (Manifest $manifest, AppEntity $app, Context $context): void {
                    $this->reRegisterApp($manifest, $app, $context);
                });
            }
        };

        // The aggregate names only the failed app; the healthy one was processed, so it is absent from the message.
        $this->expectExceptionObject(AppException::shopIdChangeAppReRegistrationFailed(['app-one']));

        $strategy->resolve($context);
    }

    public function testForEachInstalledAppIsolatesAManifestThatCannotBeLoaded(): void
    {
        $context = Context::createDefaultContext();
        $broken = AppFixture::createAppEntity(name: 'broken-manifest', id: 'broken-id');
        $healthy = AppFixture::createAppEntity(name: 'app-two', id: 'app-two-id');

        // 'broken-manifest' resolves to a directory with no manifest.xml, so Manifest::createFromXmlFile()
        // throws before the callback ever runs — this must be isolated like a callback failure, not abort it.
        $sourceResolver = new StaticSourceResolver([
            'broken-manifest' => new Filesystem(__DIR__),
            'app-two' => new Filesystem(__DIR__ . '/../Manifest/_fixtures/test'),
        ]);

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$broken, $healthy])]);

        // Only the healthy app reaches the callback; the broken one fails at manifest loading before it.
        // exactly-once on app-two proves the batch continued past the manifest failure.
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->once())
            ->method('rotateNow')
            ->with('app-two-id', static::isInstanceOf(Context::class), AppSecretRotationService::TRIGGER_SHOP_MOVE);

        $strategy = new class($sourceResolver, $appRepository, $rotationService) extends AbstractShopIdChangeStrategy {
            public function getName(): string
            {
                return 'test';
            }

            public function getDescription(): string
            {
                return 'test';
            }

            public function getDecorated(): AbstractShopIdChangeStrategy
            {
                throw new DecorationPatternException(self::class);
            }

            public function resolve(Context $context): void
            {
                $this->forEachInstalledApp($context, function (Manifest $manifest, AppEntity $app, Context $context): void {
                    $this->reRegisterApp($manifest, $app, $context);
                });
            }
        };

        $this->expectExceptionObject(AppException::shopIdChangeAppReRegistrationFailed(['broken-manifest']));

        $strategy->resolve($context);
    }
}
