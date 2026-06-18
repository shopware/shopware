<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\CodeCoverageIgnore;

use PhpParser\Node;
use PHPStan\Reflection\ReflectionProvider;
use Shopware\Core\Framework\Log\Package;

/**
 * Decides whether a docblock's @see references exempt the annotated symbol
 * from the coverage-ignore rule. A reference exempts when it resolves to an
 * existing class whose FQCN contains \Tests\Integration\. Short-form names
 * are resolved through the file's use map; fully qualified or relative refs
 * are taken as-is.
 *
 * @internal
 */
#[Package('framework')]
final class ExemptionResolver
{
    public function __construct(
        private readonly ReflectionProvider $reflectionProvider,
    ) {
    }

    /**
     * @param array<string, string> $useMap alias => FQCN, see UseMap
     */
    public function isExempted(Node $node, array $useMap): bool
    {
        $doc = $node->getDocComment();
        if ($doc === null) {
            return false;
        }

        if (!preg_match_all('/@see\s+(\S+)/', $doc->getText(), $matches)) {
            return false;
        }

        foreach ($matches[1] as $reference) {
            $rawClass = explode('::', $reference)[0];
            $candidate = ltrim($rawClass, '\\');
            if ($candidate === '') {
                continue;
            }

            $resolved = $candidate;

            // Unqualified (no `\`) references are resolved against the file's
            // use statements. Qualified refs (with `\` or relative path) are
            // taken as-is, matching common phpdoc conventions in this codebase.
            if (!str_starts_with($rawClass, '\\') && !str_contains($candidate, '\\')) {
                $resolved = $useMap[$candidate] ?? $candidate;
            }

            if (!str_contains($resolved, '\\Tests\\Integration\\')) {
                continue;
            }

            if ($this->reflectionProvider->hasClass($resolved)) {
                return true;
            }
        }

        return false;
    }
}
