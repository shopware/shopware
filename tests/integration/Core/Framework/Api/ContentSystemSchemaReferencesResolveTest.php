<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Shopware\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;

/**
 * Resolves every content-system `$ref` in the two assembled OpenAPI documents against the components the same
 * document declares. The route-schema coverage test only compares path keys and HTTP methods, so a component
 * `$ref` pointing at a schema nobody defines ships green; this test is what closes that gap.
 *
 * @internal
 */
#[Package('framework')]
final class ContentSystemSchemaReferencesResolveTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * The Admin API node schema of the storage/render split plus the sub-schemas the stored node hangs off
     * itself. Every one of them must be reached by the sweep, otherwise the resolution assertions run over a
     * ref set that never touched them. There is no rendered node here: the Admin API serves the storage model
     * only, and the rendered counterpart lives in the Store API document.
     */
    private const ADMIN_API_ELEMENT_SCHEMAS = [
        'ContentElementContextConsumer',
        'ContentElementContextProvider',
        'ContentElementDataRequirement',
        'StoredContentElement',
    ];

    /**
     * The Store API node schemas: one per format grammar.
     */
    private const STORE_API_ELEMENT_SCHEMAS = [
        'ContentDecomposedElement',
        'ContentElement',
        'ContentSkeletonElement',
    ];

    /**
     * The hand-written content-system operations, by path prefix. This decides scope only for a reference whose
     * target is not itself content-system-named — the shared `failure` schema and the `404` response — since a
     * reference to a content-system schema is in scope wherever it sits.
     */
    private const CONTENT_SYSTEM_PATH_PREFIXES = [
        '/_action/content-system/',
        '/content/',
        '/content-data',
        '/content-decomposed',
        '/content-footer',
        '/content-header',
        '/content-skeleton',
    ];

    public function testAdminApiContentSystemReferencesResolve(): void
    {
        $document = $this->generateAdminApiDocument();

        static::assertSame(
            [],
            $this->danglingContentSystemRefs($document),
            'Content-system references in the Admin API schema do not resolve against the assembled document.'
        );
    }

    public function testStoreApiContentSystemReferencesResolve(): void
    {
        $document = $this->generateStoreApiDocument();

        static::assertSame(
            [],
            $this->danglingContentSystemRefs($document),
            'Content-system references in the Store API schema do not resolve against the assembled document.'
        );
    }

    public function testAdminApiSweepReachesEveryElementSchema(): void
    {
        $document = $this->generateAdminApiDocument();

        static::assertSame(
            [],
            array_values(array_diff(self::ADMIN_API_ELEMENT_SCHEMAS, $this->contentSystemRefTargets($document))),
            'The Admin API sweep collected no reference to these schemas, so resolving the collected set proves nothing about them.'
        );
    }

    public function testStoreApiSweepReachesEveryElementSchema(): void
    {
        $document = $this->generateStoreApiDocument();

        static::assertSame(
            [],
            array_values(array_diff(self::STORE_API_ELEMENT_SCHEMAS, $this->contentSystemRefTargets($document))),
            'The Store API sweep collected no reference to these schemas, so resolving the collected set proves nothing about them.'
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function generateAdminApiDocument(): array
    {
        $generator = $this->getContainer()->get(OpenApi3Generator::class);

        return $generator->generate(
            $this->getContainer()->get(DefinitionInstanceRegistry::class)->getDefinitions(),
            DefinitionService::API
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function generateStoreApiDocument(): array
    {
        $generator = $this->getContainer()->get(StoreApiGenerator::class);

        return $generator->generate(
            $this->getContainer()->get(SalesChannelDefinitionInstanceRegistry::class)->getDefinitions(),
            DefinitionService::STORE_API,
            DefinitionService::TYPE_JSON_API,
            null
        );
    }

    /**
     * One entry per unresolvable reference, naming both the reference and the document path it sits at, so a
     * failure points at the member that has to change rather than at the document as a whole.
     *
     * @param array<string, mixed> $document
     *
     * @return list<string>
     */
    private function danglingContentSystemRefs(array $document): array
    {
        $dangling = [];

        foreach ($this->collectContentSystemRefs($document) as $occurrence) {
            if ($this->resolves($occurrence['ref'], $document)) {
                continue;
            }

            $dangling[] = \sprintf('"%s" at %s', $occurrence['ref'], implode('.', $occurrence['path']));
        }

        return $dangling;
    }

    /**
     * The distinct component names the collected content-system references point at.
     *
     * @param array<string, mixed> $document
     *
     * @return list<string>
     */
    private function contentSystemRefTargets(array $document): array
    {
        $targets = [];

        foreach ($this->collectContentSystemRefs($document) as $occurrence) {
            $targets[] = $this->refTarget($occurrence['ref']);
        }

        return array_values(array_unique($targets));
    }

    /**
     * @param array<string, mixed> $document
     *
     * @return list<array{path: list<string>, ref: string}>
     */
    private function collectContentSystemRefs(array $document): array
    {
        $found = [];
        $this->walk($document, [], $found);

        $scoped = [];
        foreach ($found as $occurrence) {
            if ($this->isContentSystemOccurrence($occurrence['path'], $occurrence['ref'])) {
                $scoped[] = $occurrence;
            }
        }

        return $scoped;
    }

    /**
     * Descends into every array member, so a reference nested any number of levels inside a request body,
     * a response, a sub-schema or a path operation is collected the same way a top-level one is.
     *
     * @param list<string> $path
     * @param list<array{path: list<string>, ref: string}> $found
     */
    private function walk(mixed $node, array $path, array &$found): void
    {
        if (!\is_array($node)) {
            return;
        }

        foreach ($node as $key => $value) {
            if ($key === '$ref' && \is_string($value)) {
                $found[] = ['path' => $path, 'ref' => $value];

                continue;
            }

            $this->walk($value, [...$path, (string) $key], $found);
        }
    }

    /**
     * A reference belongs to the content-system surface when it points at a content-system schema, sits inside
     * one, or sits inside a content-system path operation. The third case is what keeps a reference to a
     * platform-wide schema (`failure`) in scope when a content-system operation is what carries it.
     *
     * @param list<string> $path
     */
    private function isContentSystemOccurrence(array $path, string $ref): bool
    {
        if ($this->isContentSystemSchemaName($this->refTarget($ref))) {
            return true;
        }

        if (($path[0] ?? '') === 'components' && ($path[1] ?? '') === 'schemas') {
            return $this->isContentSystemSchemaName($path[2] ?? '');
        }

        if (($path[0] ?? '') !== 'paths') {
            return false;
        }

        return $this->isContentSystemPath($path[1] ?? '');
    }

    /**
     * Every hand-written content-system schema carries `Content` in its name. So do the DAL-generated entity
     * schemas of the content-system entities (`ContentLayout`, `AppContentSystemElementType`), which this
     * therefore sweeps as well — they resolve, and a generated reference into this surface breaking is worth
     * catching here too.
     */
    private function isContentSystemSchemaName(string $name): bool
    {
        return str_contains($name, 'Content');
    }

    private function isContentSystemPath(string $path): bool
    {
        foreach (self::CONTENT_SYSTEM_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function refTarget(string $ref): string
    {
        $parts = explode('/', $ref);

        return $parts[array_key_last($parts)];
    }

    /**
     * @param array<string, mixed> $document
     */
    private function resolves(string $ref, array $document): bool
    {
        if (!str_starts_with($ref, '#/')) {
            return false;
        }

        $current = $document;

        foreach (explode('/', substr($ref, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if (!\is_array($current) || !\array_key_exists($segment, $current)) {
                return false;
            }

            $current = $current[$segment];
        }

        return true;
    }
}
