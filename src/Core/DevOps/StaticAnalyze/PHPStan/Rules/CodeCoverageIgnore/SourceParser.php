<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use Shopware\Core\Framework\Log\Package;

/**
 * Resolves file-level `use` statements (alias => FQCN) so short-form @see
 * references in docblocks can be looked up via the file's import map.
 *
 * @internal
 */
#[Package('framework')]
final class SourceParser
{
    private readonly Parser $parser;

    /**
     * file path => alias => FQCN
     *
     * @var array<string, array<string, string>>
     */
    private array $useMapCache = [];

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForHostVersion();
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

        if (!is_file($file)) {
            return [];
        }

        $source = @file_get_contents($file);
        if ($source === false) {
            return [];
        }

        try {
            $stmts = $this->parser->parse($source);
        } catch (\Throwable) {
            return [];
        }

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
