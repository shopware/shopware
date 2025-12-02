<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\ThemeCollection;
use Shopware\Storefront\Theme\ThemeRuntimeConfig;
use Shopware\Storefront\Theme\ThemeRuntimeConfigStorage;

/**
 * @internal
 *
 * @phpstan-import-type ThemeRuntimeConfigArrayOverrides from ThemeRuntimeConfig
 */
#[CoversClass(ThemeRuntimeConfigStorage::class)]
class ThemeRuntimeConfigStorageTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    private ThemeRuntimeConfigStorage $storage;

    /**
     * @var EntityRepository<ThemeCollection>
     */
    private EntityRepository $themeRepository;

    protected function setUp(): void
    {
        $this->storage = new ThemeRuntimeConfigStorage($this->getContainer()->get(Connection::class));
        $this->themeRepository = $this->getContainer()->get('theme.repository');
    }

    public function testSaveAndGetByName(): void
    {
        $config = $this->createThemeRuntimeConfig();
        static::assertNotNull($config->technicalName, 'Technical name should not be null for this test');
        $this->storage->save($config);

        $retrieved = $this->storage->getByName($config->technicalName);

        static::assertNotNull($retrieved);
        $this->assertThemeRuntimeConfigEquals($config, $retrieved);
    }

    public function testGetById(): void
    {
        $config = $this->createThemeRuntimeConfig();
        $this->storage->save($config);

        $retrieved = $this->storage->getById($config->themeId);

        static::assertNotNull($retrieved);
        $this->assertThemeRuntimeConfigEquals($config, $retrieved);
    }

    public function testGetActiveThemeNames(): void
    {
        $namesBefore = $this->storage->getActiveThemeNames();
        $themeName1 = 'theme1_' . Uuid::randomHex();
        $themeName2 = 'theme2_' . Uuid::randomHex();

        $config1 = $this->createThemeRuntimeConfig(['technicalName' => $themeName1]);
        $config2 = $this->createThemeRuntimeConfig(['technicalName' => $themeName2]);

        $this->storage->save($config1);
        $this->storage->save($config2);

        $activeThemes = $this->storage->getActiveThemeNames();
        $addedNames = array_diff($activeThemes, $namesBefore);

        static::assertCount(2, $addedNames);
        static::assertContains($themeName1, $addedNames);
        static::assertContains($themeName2, $addedNames);
    }

    public function testSaveUpdatesExistingTheme(): void
    {
        $themeId = Uuid::randomHex();
        $initialConfig = $this->createThemeRuntimeConfig(['themeId' => $themeId]);
        $updatedConfig = $this->createThemeRuntimeConfig([
            'themeId' => $themeId,
            'resolvedConfig' => ['key' => 'updated'],
            'viewInheritance' => ['view' => 'updated'],
            'scriptFiles' => ['updated.js'],
            'iconSets' => [
                'updated-set' => [
                    'path' => 'path/to/updated/icons',
                    'namespace' => 'updated-theme',
                ],
            ],
        ]);

        $this->storage->save($initialConfig);
        $this->storage->save($updatedConfig);

        $retrieved = $this->storage->getById($themeId);

        static::assertNotNull($retrieved);
        $this->assertThemeRuntimeConfigEquals($updatedConfig, $retrieved);
    }

    public function testGetCopiesIds(): void
    {
        $parentThemeId = $this->createTheme(null, 'parent-theme');

        // Create a copy (no technical name)
        $copyId = $this->createTheme($parentThemeId, null);

        // Create a regular child theme (with technical name)
        $childId = $this->createTheme($parentThemeId, 'child-theme');

        $copyIds = $this->storage->getCopiesIds($parentThemeId);

        static::assertCount(1, $copyIds);
        static::assertContains($copyId, $copyIds);
        static::assertNotContains($childId, $copyIds);
    }

    public function testGetChildThemeIds(): void
    {
        $parentThemeId = $this->createTheme();

        // Create first level children
        $child1Id = $this->createTheme($parentThemeId);
        $child2Id = $this->createTheme($parentThemeId);

        // Create second level children
        $grandChild1Id = $this->createTheme($child1Id);
        $grandChild2Id = $this->createTheme($child2Id);

        // Create third level children
        $greatGrandChild1Id = $this->createTheme($grandChild1Id);
        $greatGrandChild2Id = $this->createTheme($grandChild2Id);

        $childIds = $this->storage->getChildThemeIds($parentThemeId);

        static::assertCount(6, $childIds);
        static::assertContains($child1Id, $childIds);
        static::assertContains($child2Id, $childIds);
        static::assertContains($grandChild1Id, $childIds);
        static::assertContains($grandChild2Id, $childIds);
        static::assertContains($greatGrandChild1Id, $childIds);
        static::assertContains($greatGrandChild2Id, $childIds);
    }

    public function testGetThemeTechnicalName(): void
    {
        $technicalName = 'test-theme-' . Uuid::randomHex();
        $themeId = $this->createTheme(null, $technicalName);

        $retrievedTechnicalName = $this->storage->getThemeTechnicalName($themeId);

        static::assertSame($technicalName, $retrievedTechnicalName);
    }

    public function testGetThemeTechnicalNameReturnsNullForNonExistentTheme(): void
    {
        $nonExistentThemeId = Uuid::randomHex();

        $retrievedTechnicalName = $this->storage->getThemeTechnicalName($nonExistentThemeId);

        static::assertNull($retrievedTechnicalName);
    }

    public function testGetThemeIdByTechnicalName(): void
    {
        $technicalName = 'test-theme-' . Uuid::randomHex();
        $themeId = $this->createTheme(null, $technicalName);

        $retrievedThemeId = $this->storage->getThemeIdByTechnicalName($technicalName);

        static::assertSame($themeId, $retrievedThemeId);
    }

    public function testGetThemeIdByTechnicalNameReturnsNullForNonExistentTheme(): void
    {
        $nonExistentTechnicalName = 'non-existent-theme-' . Uuid::randomHex();

        $retrievedThemeId = $this->storage->getThemeIdByTechnicalName($nonExistentTechnicalName);

        static::assertNull($retrievedThemeId);
    }

    /**
     * @param ThemeRuntimeConfigArrayOverrides $overrides
     */
    private function createThemeRuntimeConfig(array $overrides = []): ThemeRuntimeConfig
    {
        $defaults = [
            'themeId' => Uuid::randomHex(),
            'technicalName' => 'default-theme',
            'resolvedConfig' => ['key' => 'default-value'],
            'viewInheritance' => ['view' => 'default-inheritance'],
            'scriptFiles' => ['default.js'],
            'iconSets' => [
                'default-set' => [
                    'path' => 'path/to/icons',
                    'namespace' => 'default-theme',
                ],
            ],
            'updatedAt' => new \DateTimeImmutable(),
        ];

        return ThemeRuntimeConfig::fromArray($defaults)->with($overrides);
    }

    private function assertThemeRuntimeConfigEquals(ThemeRuntimeConfig $expected, ThemeRuntimeConfig $actual): void
    {
        static::assertNotSame($expected, $actual); // we want to make sure we do not compare the same object
        static::assertSame($expected->themeId, $actual->themeId);
        static::assertSame($expected->technicalName, $actual->technicalName);
        static::assertSame($expected->resolvedConfig, $actual->resolvedConfig);
        static::assertSame($expected->viewInheritance, $actual->viewInheritance);
        static::assertSame($expected->scriptFiles, $actual->scriptFiles);
        static::assertSame($expected->iconSets, $actual->iconSets);
        static::assertSame(
            $expected->updatedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $actual->updatedAt->format(Defaults::STORAGE_DATE_TIME_FORMAT)
        );
    }

    private function createTheme(?string $parentThemeId = null, ?string $technicalName = null): string
    {
        $id = Uuid::randomHex();
        $this->themeRepository->create([
            [
                'id' => $id,
                'parentThemeId' => $parentThemeId,
                'name' => 'Test Theme',
                'technicalName' => $technicalName,
                'author' => 'test',
                'active' => true,
            ],
        ], Context::createDefaultContext());

        return $id;
    }
}
