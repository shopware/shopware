<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\OpenApi;

use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApiFileLoader;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('framework')]
final class OpenApiDtoGenerator
{
    /**
     * Vendor extension on an operation that declares the target PHP namespace of the
     * generated DTOs. The output directory is derived from it via PSR-4.
     */
    public const NAMESPACE_EXTENSION = 'x-dto-namespace';

    /**
     * Vendor extension on a schema or operation that declares the package attribute
     * of the generated DTOs.
     */
    public const PACKAGE_EXTENSION = 'x-dto-package';

    /**
     * Vendor extension on a component schema that marks it as a reusable composition fragment.
     * Inline components are flattened into referencing DTOs and are not generated themselves.
     */
    public const INLINE_EXTENSION = 'x-dto-inline';

    /**
     * @var list<string>
     */
    private const HTTP_METHODS = [
        'get',
        'post',
        'put',
        'patch',
        'delete',
        'options',
        'head',
        'trace',
    ];

    /**
     * @var list<string>
     */
    private const API_SCHEMA_DIRECTORIES = ['AdminApi', 'StoreApi'];

    private const GENERATED_AT_LINE_PATTERN = '# \* Last generated: \d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\n#';

    private readonly string $schemaDirectory;

    private readonly string $sourceDirectory;

    /**
     * @param array{Framework: array{path: string}} $bundles
     */
    public function __construct(
        private readonly OpenApiDtoSchemaParser $schemaParser,
        private readonly OpenApiDtoClassRenderer $classRenderer,
        private readonly Filesystem $filesystem,
        array $bundles,
    ) {
        $frameworkPath = $bundles['Framework']['path'];
        $this->schemaDirectory = $frameworkPath . '/Api/ApiDefinition/Generator/Schema';
        // Framework lives at "<src>/Core/Framework", so the PSR-4 root "Shopware\" maps to "<src>".
        $this->sourceDirectory = \dirname($frameworkPath, 2);
    }

    public function generate(): OpenApiDtoGenerationResult
    {
        $writtenFiles = [];
        foreach ($this->generateFiles() as $file) {
            if ($this->isCurrent($file)) {
                continue;
            }

            $this->filesystem->dumpFile($file->path, $file->contents);
            $writtenFiles[] = $file->path;
        }

        return new OpenApiDtoGenerationResult($writtenFiles);
    }

    public function check(): OpenApiDtoGenerationCheckResult
    {
        $outdatedFiles = [];

        foreach ($this->generateFiles() as $file) {
            if (!$this->filesystem->exists($file->path)) {
                $outdatedFiles[] = $file->path;

                continue;
            }

            if (!$this->isCurrent($file)) {
                $outdatedFiles[] = $file->path;
            }
        }

        return new OpenApiDtoGenerationCheckResult($outdatedFiles);
    }

    /**
     * @return list<OpenApiDtoGeneratedFile>
     */
    private function generateFiles(): array
    {
        $files = [];
        foreach (self::API_SCHEMA_DIRECTORIES as $apiSchemaDirectory) {
            $directory = $this->schemaDirectory . '/' . $apiSchemaDirectory;
            if (!$this->filesystem->exists($directory)) {
                continue;
            }

            $spec = (new OpenApiFileLoader([$directory]))->loadOpenapiSpecification();
            foreach ($this->generateFilesForSpecification($spec) as $file) {
                if (isset($files[$file->path]) && $this->normalizeGeneratedAtLine($files[$file->path]->contents) !== $this->normalizeGeneratedAtLine($file->contents)) {
                    throw FrameworkException::invalidArgumentException(\sprintf(
                        'Admin API and Store API DTO schemas generate conflicting class file "%s".',
                        $file->path,
                    ));
                }

                $files[$file->path] = $file;
            }
        }

        return array_values($files);
    }

    /**
     * @param array<string, mixed> $spec
     *
     * @return list<OpenApiDtoGeneratedFile>
     */
    private function generateFilesForSpecification(array $spec): array
    {
        $components = \is_array($spec['components'] ?? null) ? $spec['components'] : [];

        // Components can declare their own target namespace via the x-dto-namespace extension.
        // They are shared across APIs (e.g. SuccessResponse), so they are generated once into that
        // namespace instead of being duplicated next to every operation that references them.
        $componentsWithNamespace = $this->componentsWithNamespace($components);

        /** @var array<string, array{definition: OpenApiDtoDefinition, namespace: string}> $placements keyed by FQCN */
        $placements = [];
        // map of FQCN => namespace, used to render cross-namespace `use` imports
        $externalNamespaces = [];

        // 1. Shared components -> their own declared namespace.
        foreach ($componentsWithNamespace as $schemaName => $namespace) {
            foreach ($this->schemaParser->parseComponents($spec, [$schemaName], namespace: $namespace) as $definition) {
                $placements[$definition->name] ??= ['definition' => $definition, 'namespace' => $namespace];
                $externalNamespaces[$definition->name] = $namespace;
            }
        }

        // 2. Operation DTOs (request/response) and their referenced components co-located next to
        //    the operation, unless a component already claimed its own namespace above.
        foreach ($this->groupTaggedOperationsByNamespace($spec) as $namespace => $paths) {
            $definitions = $this->schemaParser->parse(['paths' => $paths, 'components' => $components], includeComponentSchemas: false, namespace: $namespace);
            foreach ($definitions as $definition) {
                if (isset($externalNamespaces[$definition->name])) {
                    continue;
                }

                $placements[$definition->name] ??= ['definition' => $definition, 'namespace' => $namespace];
            }
        }

        $files = [];
        foreach ($placements as $placement) {
            $contents = $this->classRenderer->renderClass($placement['definition'], $placement['namespace'], $externalNamespaces);
            $path = $this->namespaceToDirectory($placement['namespace']) . '/' . $this->shortClassName($placement['definition']->name) . '.php';
            $files[] = new OpenApiDtoGeneratedFile($path, $contents);
        }

        return $files;
    }

    private function shortClassName(string $fqcn): string
    {
        return substr($fqcn, (int) strrpos($fqcn, '\\') + 1);
    }

    /**
     * @param array<string, mixed> $components
     *
     * @return array<string, string> map of component schema name => declared target namespace
     */
    private function componentsWithNamespace(array $components): array
    {
        $schemas = \is_array($components['schemas'] ?? null) ? $components['schemas'] : [];

        $namespaces = [];
        foreach ($schemas as $name => $schema) {
            if (!\is_string($name) || !\is_array($schema)) {
                continue;
            }

            $namespace = $this->namespaceFromSchema($schema);
            if ($namespace !== null) {
                $namespaces[$name] = $namespace;
            }
        }

        return $namespaces;
    }

    /**
     * Collects all operations with the namespace extension and groups the paths
     * they belong to by the target namespace declared in {@see self::NAMESPACE_EXTENSION}.
     * Operations without the extension are skipped, since we can not tell where to put them.
     *
     * @param array<string, mixed> $spec
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    private function groupTaggedOperationsByNamespace(array $spec): array
    {
        $paths = \is_array($spec['paths'] ?? null) ? $spec['paths'] : [];

        $groups = [];
        foreach ($paths as $pathKey => $pathItem) {
            if (!\is_string($pathKey) || !\is_array($pathItem)) {
                continue;
            }

            foreach (self::HTTP_METHODS as $method) {
                $operation = $pathItem[$method] ?? null;
                if (!\is_array($operation)) {
                    continue;
                }

                $namespace = $this->namespaceFromSchema($operation);
                if ($namespace === null) {
                    continue;
                }

                $groups[$namespace][$pathKey][$method] = $operation;
            }
        }

        return $groups;
    }

    /**
     * Returns and validates the namespace declared by a schema extension.
     *
     * @param array<string, mixed> $schema
     */
    private function namespaceFromSchema(array $schema): ?string
    {
        $namespace = $schema[self::NAMESPACE_EXTENSION] ?? null;
        if ($namespace === null) {
            return null;
        }

        if (!\is_string($namespace) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*(?:\\\\[A-Za-z_][A-Za-z0-9_]*)*$/D', $namespace) !== 1) {
            throw FrameworkException::invalidArgumentException('The ' . self::NAMESPACE_EXTENSION . ' extension must contain a valid PHP namespace.');
        }

        return $namespace;
    }

    private function namespaceToDirectory(string $namespace): string
    {
        $relative = ltrim(str_replace('\\', '/', $namespace), '/');
        $relative = preg_replace('#^Shopware/#', '', $relative) ?? $relative;

        return $this->sourceDirectory . '/' . $relative;
    }

    private function isCurrent(OpenApiDtoGeneratedFile $file): bool
    {
        try {
            $currentContents = $this->filesystem->readFile($file->path);
        } catch (IOException) {
            return false;
        }

        return $this->normalizeGeneratedAtLine($currentContents) === $this->normalizeGeneratedAtLine($file->contents);
    }

    private function normalizeGeneratedAtLine(string $contents): string
    {
        $contents = str_replace("\r\n", "\n", $contents);

        return preg_replace(self::GENERATED_AT_LINE_PATTERN, " * Last generated: <normalized>\n", $contents) ?? $contents;
    }
}
