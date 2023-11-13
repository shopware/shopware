<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Administration\Administration;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Kernel;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

/**
 * @internal
 *
 * @group slow
 */
#[Package('core')]
class AdditionalPermissionValidationTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var array<int, string>
     */
    private const ROOT_CLASSES = [Administration::class, Kernel::class];

    /**
     * blacklist file path segments for ignored paths
     */
    private array $blacklist = [
        'Test/',
        'node_modules/',
        'Common/vendor/',
        'Recovery/vendor',
        'recovery/vendor',
        'storefront/vendor',
        'public/static/js',
    ];

    /**
     * @var array<int, string>
     */
    private array $rootDirs;

    protected function setUp(): void
    {
        $this->rootDirs = array_filter(array_map(static function (string $class): ?string {
            if (!\class_exists($class)) {
                return null;
            }

            return \dirname((string) (new \ReflectionClass($class))->getFileName());
        }, self::ROOT_CLASSES));
    }

    public function testSourceFilesForUnvalidatedPrivileges(): void
    {
        $additionalPermission = $this->getAdditionalPermissions();

        if (empty($additionalPermission)) {
            return;
        }

        $regexParts = [];

        foreach ($additionalPermission as $permission) {
            $regexParts[$permission] = '(\"|\')' . $permission . '(\"|\')';
        }

        $regex = sprintf('/%s/s', implode('|', $regexParts));

        $finder = new Finder();
        $finder->in($this->rootDirs)
            ->files()
            ->name('*.php')
            ->contains($regex);

        foreach ($this->blacklist as $path) {
            $finder->notPath($path);
        }

        foreach ($finder->getIterator() as $file) {
            $filePath = $file->getRealPath();
            $content = file_get_contents($filePath);

            foreach ($regexParts as $key => $regexPart) {
                if (preg_match('/' . $regexPart . '/s', $content)) {
                    unset($regexParts[$key]);
                }
            }
        }

        if (!empty($regexParts)) {
            static::fail(sprintf(
                'Found additional permission privileges not validated: %s',
                implode(', ', array_keys($regexParts))
            ));
        }
    }

    private function getAdditionalPermissions(): array
    {
        $entityPermissions = [];

        $registry = $this->getContainer()->get(DefinitionInstanceRegistry::class);
        $entities = $registry->getDefinitions();

        foreach ($entities as $entity) {
            foreach ([':read', ':update', ':create', ':delete'] as $action) {
                $entityPermissions[] = $entity->getEntityName() . $action;
            }
        }

        $regex = '/additional_permissions(.*?)(\'|\"|)privileges(\'|\"|):(.*?)\[(.*?)]/s';
        $finder = new Finder();
        $finder->in($this->rootDirs)
            ->files()
            ->path('/acl/')
            ->name('index.js')
            ->contains($regex);

        foreach ($this->blacklist as $path) {
            $finder->notPath($path);
        }

        $additionalPermission = [];

        foreach ($finder->getIterator() as $file) {
            $filePath = $file->getRealPath();
            $content = file_get_contents($filePath);

            preg_match_all($regex, $content, $matches);

            if (isset($matches[5][0])) {
                $jsArray = '[' . $matches[5][0] . ']';

                try {
                    // use Yaml to try parsing reconstructed JavaScript array
                    $results = Yaml::parse($jsArray);

                    $additionalPermission = array_merge($additionalPermission, $results);
                } catch (\Exception) {
                }
            }
        }

        foreach ($additionalPermission as $key => $permission) {
            if (array_search($permission, $entityPermissions, true) !== false) {
                unset($additionalPermission[$key]);
            }
        }

        return array_unique($additionalPermission);
    }
}
