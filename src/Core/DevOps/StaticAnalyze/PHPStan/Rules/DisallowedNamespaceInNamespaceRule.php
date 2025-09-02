<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * @implements Rule<Node\Name\FullyQualified>
 *
 * @internal
 */
#[Package('framework')]
class DisallowedNamespaceInNamespaceRule implements Rule
{
    private string $sourceNamespace;

    /**
     * @var string[]
     */
    private array $disallowedNamespaces;

    /**
     * @param string $sourceNamespace The namespace where the rule should be enforced.
     * @param string[] $disallowedNamespaces A list of namespaces that are forbidden to be used.
     */
    public function __construct(string $sourceNamespace, array $disallowedNamespaces)
    {
        // Normalize namespaces to ensure they end with a backslash for consistent matching
        $this->sourceNamespace = rtrim($sourceNamespace, '\\') . '\\';
        $this->disallowedNamespaces = array_map(
            static fn (string $ns): string => rtrim($ns, '\\') . '\\',
            $disallowedNamespaces
        );
    }

    /**
     * We want to check every fully qualified name usage (e.g., new \Foo\Bar(), \Foo\Bar::class).
     * This covers `use` statements, type hints, static calls, new instantiations, etc.
     */
    public function getNodeType(): string
    {
        return Node\Name\FullyQualified::class;
    }

    /**
     * @param Node\Name\FullyQualified $node
     *
     * @return array<string|\PHPStan\Rules\RuleError>
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $currentNamespace = $scope->getNamespace();

        // If we are not in a namespace, this rule does not apply.
        if ($currentNamespace === null) {
            return [];
        }

        // Check if the current file is within the source namespace we care about.
        // We add a `\` to the current namespace to ensure an exact match (e.g., `Shopware\Core` vs `Shopware\CoreSomething`).
        if (!str_starts_with($currentNamespace . '\\', $this->sourceNamespace)) {
            return [];
        }

        $usedClassName = $node->toString();

        // Now, check if the used class/interface/trait belongs to one of the disallowed namespaces.
        foreach ($this->disallowedNamespaces as $disallowedNamespace) {
            if (str_starts_with($usedClassName . '\\', $disallowedNamespace)) {
                $errorMessage = \sprintf(
                    'Usage of the namespace "%s" is forbidden in the "%s" namespace.',
                    rtrim($disallowedNamespace, '\\'),
                    rtrim($this->sourceNamespace, '\\')
                );

                return [
                    RuleErrorBuilder::message($errorMessage)
                        ->tip('This violates the architectural boundaries. The Core layer should not depend on Administration, Storefront, or Elasticsearch.')
                        ->build(),
                ];
            }
        }

        // No violation found
        return [];
    }
}
