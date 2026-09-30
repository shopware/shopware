<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules\Deprecation;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassConst;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;

/**
 * BC-planning notes must use the dedicated attributes from
 * `Shopware\Core\Framework\Deprecation\BCChange` instead of `reason:*` deprecation
 * PHPDoc markers: the markers surface as deprecation errors in static analysis of
 * third-party code although there is nothing to migrate to.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
#[Package('framework')]
class NoBCPlanningDeprecationRule implements Rule
{
    private const REPLACEMENTS = [
        'reason:return-type-change' => 'Use the #[ReturnTypeNarrowing] or #[ReturnTypeWidening] attribute instead.',
        'reason:parameter-type-change' => 'Use the #[ParameterTypeNarrowing] attribute instead.',
        'reason:parameter-type-extension' => 'Use the #[ParameterTypeWidening] attribute instead.',
        'reason:parameter-default-change' => 'Use the #[ParameterDefaultValueChange] attribute instead.',
        'reason:new-optional-parameter' => 'Use the #[NewOptionalParameter] attribute instead.',
        'reason:parameter-name-change' => 'Use the #[ParameterNameChange] attribute instead.',
        'reason:becomes-internal' => 'Use the #[BecomesInternal] attribute instead.',
        'reason:becomes-final' => 'Use the #[BecomesFinal] attribute instead.',
        'reason:class-hierarchy-change' => 'Use the #[ClassHierarchyChange] attribute instead.',
        'reason:visibility-change' => 'Use the #[VisibilityChange] or #[BecomesAbstract] attribute instead.',
        'reason:exception-change' => 'Use the #[ExceptionChange] attribute instead.',
    ];

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classNode = $node->getOriginalNode();

        $errors = $this->validateDoc($classNode->getDocComment()?->getText(), $classNode->getStartLine());

        foreach ($classNode->stmts as $stmt) {
            if (!$stmt instanceof ClassMethod && !$stmt instanceof Property && !$stmt instanceof ClassConst) {
                continue;
            }

            $errors = [...$errors, ...$this->validateDoc($stmt->getDocComment()?->getText(), $stmt->getStartLine())];
        }

        return $errors;
    }

    /**
     * @return list<IdentifierRuleError>
     */
    private function validateDoc(?string $doc, int $line): array
    {
        if ($doc === null || !\str_contains($doc, '@deprecated')) {
            return [];
        }

        $errors = [];
        foreach (self::REPLACEMENTS as $reason => $replacement) {
            if (!\str_contains($doc, $reason)) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message(\sprintf(
                'The deprecation reason "%s" is a BC-planning note, not a deprecation. Remove the deprecation annotation. %s',
                $reason,
                $replacement
            ))
                ->identifier('shopware.bcPlanningDeprecation')
                ->line($line)
                ->build();
        }

        return $errors;
    }
}
