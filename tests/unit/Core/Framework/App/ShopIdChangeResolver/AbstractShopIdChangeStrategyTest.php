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
use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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
    private const SETUP_MANIFEST_DIR = __DIR__ . '/../Manifest/_fixtures/test';

    public function testForEachInstalledAppKeepsGoingWhenOneAppFailsAndReportsTheFailures(): void
    {
        $context = Context::createDefaultContext();
        $failingAppId = 'failing-app-id';
        $failingApp = AppFixture::createAppEntity(name: 'failing-app', id: $failingAppId);
        $healthyApp = AppFixture::createAppEntity(name: 'healthy-app', id: 'healthy-app-id');

        // Both apps resolve to the same fixture manifest, which declares <setup> so the callback runs for each.
        $setupManifestFs = new Filesystem(self::SETUP_MANIFEST_DIR);
        $sourceResolver = new StaticSourceResolver([
            'failing-app' => $setupManifestFs,
            'healthy-app' => $setupManifestFs,
        ]);

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$failingApp, $healthyApp])]);

        // The batch must not abort on the first failure: rotateNow is attempted for BOTH apps (exactly(2))
        // even though the first one throws.
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->exactly(2))
            ->method('rotateNow')
            ->willReturnCallback(function (string $appId) use ($failingAppId): void {
                if ($appId === $failingAppId) {
                    throw AppException::registrationFailed('failing-app', 'app server unreachable');
                }
            });

        $strategy = $this->createReRegisterEachAppStrategy($sourceResolver, $appRepository, $rotationService);

        // The aggregate names only the failed app; the healthy one was processed, so it is absent from the message.
        $this->expectExceptionObject(AppException::shopIdChangeAppReRegistrationFailed(['failing-app']));

        $strategy->resolve($context);
    }

    public function testForEachInstalledAppIsolatesAManifestThatCannotBeLoaded(): void
    {
        $context = Context::createDefaultContext();
        $brokenApp = AppFixture::createAppEntity(name: 'broken-manifest', id: 'broken-id');
        $healthyApp = AppFixture::createAppEntity(name: 'healthy-app', id: 'healthy-app-id');

        // 'broken-manifest' resolves to a directory with no manifest.xml, so Manifest::createFromXmlFile()
        // throws before the callback ever runs — this must be isolated like a callback failure, not abort the batch.
        $sourceResolver = new StaticSourceResolver([
            'broken-manifest' => new Filesystem(__DIR__),
            'healthy-app' => new Filesystem(self::SETUP_MANIFEST_DIR),
        ]);

        /** @var StaticEntityRepository<AppCollection> $appRepository */
        $appRepository = new StaticEntityRepository([new AppCollection([$brokenApp, $healthyApp])]);

        // Only the healthy app reaches the callback; the broken one fails at manifest loading before it.
        // exactly-once on the healthy app proves the batch continued past the manifest failure.
        $rotationService = $this->createMock(AppSecretRotationService::class);
        $rotationService->expects($this->once())
            ->method('rotateNow')
            ->with('healthy-app-id', static::isInstanceOf(Context::class), AppSecretRotationService::TRIGGER_SHOP_MOVE);

        $strategy = $this->createReRegisterEachAppStrategy($sourceResolver, $appRepository, $rotationService);

        $this->expectExceptionObject(AppException::shopIdChangeAppReRegistrationFailed(['broken-manifest']));

        $strategy->resolve($context);
    }

    /**
     * A minimal concrete strategy whose resolve() re-registers every installed app — the path that exercises
     * forEachInstalledApp's failure isolation. The getName/getDescription/getDecorated bodies only satisfy the
     * abstract contract and carry no test meaning.
     *
     * @param EntityRepository<AppCollection> $appRepository
     */
    private function createReRegisterEachAppStrategy(
        SourceResolver $sourceResolver,
        EntityRepository $appRepository,
        AppSecretRotationService $rotationService
    ): AbstractShopIdChangeStrategy {
        return new class($sourceResolver, $appRepository, $rotationService) extends AbstractShopIdChangeStrategy {
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
    }
}
