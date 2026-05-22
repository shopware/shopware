<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PHPStan\Reflection\ReflectionProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * Lazy file/AST access with per-run caches. Used by the coverage-ignore rule
 * to look up trait methods (for trait-scanning) and file-level use statements
 * (for resolving short-form @see references).
 *
 * @internal
 */
#[Package('framework')]
final class SourceParser
{
    private readonly Parser $parser;

    private readonly NodeFinder $finder;

    /**
     * @var array<string, list<ClassMethod>>
     */
    private array $traitMethodCache = [];

    /**
     * file path => alias => FQCN
     *
     * @var array<string, array<string, string>>
     */
    private array $useMapCache = [];

    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
        $this->parser = (new ParserFactory())->createForHostVersion();
        $this->finder = new NodeFinder();
    }

    /**
     * @return list<ClassMethod>
     */
    public function traitMethods(string $traitFqcn): array
    {
        if (\array_key_exists($traitFqcn, $this->traitMethodCache)) {
            return $this->traitMethodCache[$traitFqcn];
        }

        $this->traitMethodCache[$traitFqcn] = [];

        if (!$this->reflectionProvider->hasClass($traitFqcn)) {
            return [];
        }

        $reflection = $this->reflectionProvider->getClass($traitFqcn);
        if (!$reflection->isTrait()) {
            return [];
        }

        $file = $reflection->getFileName();
        if ($file === null) {
            return [];
        }

        $stmts = $this->parseFile($file);
        if ($stmts === null) {
            return [];
        }

        $shortName = ($pos = strrpos($traitFqcn, '\\')) === false ? $traitFqcn : substr($traitFqcn, $pos + 1);

        /** @var list<Trait_> $traitNodes */
        $traitNodes = $this->finder->findInstanceOf($stmts, Trait_::class);
        foreach ($traitNodes as $traitNode) {
            if ($traitNode->name === null || $traitNode->name->name !== $shortName) {
                continue;
            }

            return $this->traitMethodCache[$traitFqcn] = $traitNode->getMethods();
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    public function useMap(string $file): array
    {
        if (\array_key_exists($file, $this->useMapCache)) {
            return $this->useMapCache[$file];
        }

        $this->useMapCache[$file] = [];

        $stmts = $this->parseFile($file);
        if ($stmts === null) {
            return [];
        }

        $map = [];
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Stmt\Namespace_) {
                foreach ($stmt->stmts as $inner) {
                    $this->collectUses($inner, $map);
                }
            } else {
                $this->collectUses($stmt, $map);
            }
        }

        return $this->useMapCache[$file] = $map;
    }

    /**
     * @return array<Stmt>|null
     */
    private function parseFile(string $file): ?array
    {
        if (!is_file($file)) {
            return null;
        }

        $source = @file_get_contents($file);
        if ($source === false) {
            return null;
        }

        try {
            return $this->parser->parse($source);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param array<string, string> $map
     */
    private function collectUses(Node $stmt, array &$map): void
    {
        if ($stmt instanceof Stmt\Use_) {
            foreach ($stmt->uses as $use) {
                $map[$use->getAlias()->name] = $use->name->toString();
            }

            return;
        }

        if ($stmt instanceof Stmt\GroupUse) {
            $prefix = $stmt->prefix->toString();
            foreach ($stmt->uses as $use) {
                $map[$use->getAlias()->name] = $prefix . '\\' . $use->name->toString();
            }
        }
    }
}
