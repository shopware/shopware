<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use Shopware\Core\Framework\Log\Package;

/**
 * Builds a file's `alias => FQCN` import map from already-parsed `use` statements,
 * so short-form @see references in docblocks can be resolved without re-reading
 * the source from disk.
 *
 * @internal
 */
#[Package('framework')]
final class UseMap
{
    /**
     * @param array<Node> $stmts the statements inside a namespace
     *
     * @return array<string, string> alias => FQCN
     */
    public static function fromStmts(array $stmts): array
    {
        $map = [];
        foreach ($stmts as $stmt) {
            self::collect($stmt, $map);
        }

        return $map;
    }

    /**
     * @param array<string, string> $map
     */
    private static function collect(Node $stmt, array &$map): void
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
