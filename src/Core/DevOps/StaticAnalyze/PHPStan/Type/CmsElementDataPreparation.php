<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Type;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\Element\CmsElementResolverInterface;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @phpstan-type Resolver array{class: class-string, type: string, enrich: string, parameter: string, methods: array<string, string>}
 * @phpstan-type Method array{file: string, parameters: list<string>, slots: list<string>}
 * @phpstan-type Manifest array{resolvers: list<Resolver>, methods: array<string, Method>}
 *
 * @internal
 */
#[Package('framework')]
class CmsElementDataPreparation
{
    /**
     * @return Manifest
     */
    public static function discover(string $containerXml): array
    {
        $xml = simplexml_load_string((new Filesystem())->readFile($containerXml), options: \LIBXML_NONET);
        if ($xml === false) {
            throw new \RuntimeException('Cannot read CMS resolver registrations.');
        }
        $services = [];
        foreach ($xml->services->service as $service) {
            $services[(string) $service['id']] = $service;
        }
        $registered = [];
        foreach ($services as $id => $service) {
            foreach ($service->tag as $tag) {
                if ((string) $tag['name'] === 'shopware.cms.data_resolver') {
                    $registered[] = ['id' => $id, 'priority' => (int) $tag['priority'], 'order' => \count($registered)];
                }
            }
        }
        usort($registered, static fn (array $a, array $b): int => [-$a['priority'], $a['order']] <=> [-$b['priority'], $b['order']]);
        $manifest = ['resolvers' => [], 'methods' => []];
        foreach ($registered as $registration) {
            $service = $services[$registration['id']];
            $visited = [];
            while (isset($service['alias'])) {
                $alias = (string) $service['alias'];
                if (isset($visited[$alias]) || !isset($services[$alias])) {
                    throw new \RuntimeException('Cannot resolve CMS resolver alias ' . $alias);
                }
                $visited[$alias] = true;
                $service = $services[$alias];
            }
            $class = (string) $service['class'];
            if (!is_a($class, CmsElementResolverInterface::class, true)) {
                throw new \RuntimeException('Cannot analyze CMS resolver ' . $class);
            }
            $reflection = new \ReflectionClass($class);
            $methods = [];
            do {
                foreach ($reflection->getMethods() as $method) {
                    $callback = $method->getDeclaringClass()->getName() . '::' . $method->getName();
                    $methods[$method->getName()] ??= $callback;
                    $slots = [];
                    $parameters = [];
                    foreach ($method->getParameters() as $parameter) {
                        $parameters[] = $parameter->getName();
                        $type = $parameter->getType();
                        if ($type instanceof \ReflectionNamedType && is_a($type->getName(), CmsSlotEntity::class, true)) {
                            $slots[] = $parameter->getName();
                        }
                    }
                    $file = $method->getFileName();
                    if ($file !== false && !$method->isAbstract() && ($slots !== [] || $method->getName() === 'getType')) {
                        $manifest['methods'][$callback] = ['file' => $file, 'parameters' => $parameters, 'slots' => $slots];
                    }
                }
                $reflection = $reflection->getParentClass();
            } while ($reflection !== false);
            $enrich = new \ReflectionMethod($class, 'enrich');
            $manifest['resolvers'][] = ['class' => $class, 'type' => $methods['getType'], 'enrich' => $methods['enrich'], 'parameter' => $enrich->getParameters()[0]->getName(), 'methods' => $methods];
        }

        return $manifest;
    }

    public static function prepare(string $root, ?string $phpstanBin = null, ?string $bootstrap = null): void
    {
        $directory = $root . '/var/cache/phpstan-cms';
        $manifest = self::discover($root . '/var/cache/static_phpstan_dev/Shopware_Core_DevOps_StaticAnalyze_StaticAnalyzeKernelPhpstan_devDebugContainer.xml');
        $filesystem = new Filesystem();
        $filesystem->mkdir($directory);
        $filesystem->dumpFile($directory . '/resolvers.json', json_encode($manifest, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT));
        $filesystem->remove($directory . '/context.json');
        if ($manifest['resolvers'] === []) {
            $filesystem->dumpFile($directory . '/context.json', '{"types":{},"unresolved":[]}');

            return;
        }
        $configuration = [
            'parameters' => [
                'level' => null,
                'customRulesetUsed' => true,
                'reportUnmatchedIgnoredErrors' => false,
                'paths' => array_values(array_unique(array_column($manifest['methods'], 'file'))),
                'tmpDir' => $directory . '/phpstan',
                'bootstrapFiles' => [$bootstrap ?? $root . '/vendor/autoload.php'],
                'parallel' => ['maximumNumberOfProcesses' => 1],
            ],
            'services' => [
                ['class' => CmsElementDataCollector::class, 'arguments' => ['manifestFile' => $directory . '/resolvers.json'], 'tags' => ['phpstan.collector']],
                'errorFormatter.cmsElementData' => ['class' => CmsElementDataFormatter::class, 'arguments' => ['manifestFile' => $directory . '/resolvers.json', 'outputFile' => $directory . '/context.json']],
            ],
        ];
        $filesystem->dumpFile($directory . '/collect.php', '<?php return ' . var_export($configuration, true) . ';');
        echo 'Inferring CMS slot data from ', \count($manifest['resolvers']), ' registered resolvers...', \PHP_EOL;
        $process = new Process([\PHP_BINARY, $phpstanBin ?? $root . '/vendor/bin/phpstan', 'analyse', '--configuration=' . $directory . '/collect.php', '--error-format=cmsElementData', '--no-progress'], $root, timeout: null);
        $process->mustRun(static function (string $type, string $buffer): void {
            echo $buffer;
        });
    }
}
