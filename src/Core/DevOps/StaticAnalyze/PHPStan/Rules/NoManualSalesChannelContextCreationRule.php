<?php

declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGeneratorTest;

/**
 * This PHPStan rule prevents the manual creation of a `SalesChannelContext`.
 * It checks if the `SalesChannelContext` or any of its children are created manually.
 * Usually it should be sufficient to use the `SalesChannelContextFactory` or the `Generator::createSalesChannelContext` method.
 *
 * @internal
 *
 * @implements Rule<New_>
 */
#[Package('core')]
class NoManualSalesChannelContextCreationRule implements Rule
{
    /**
     * @var list<class-string>
     */
    private static array $allowedClassesWhichCanCreateSalesChannelContext = [
        SalesChannelContextFactory::class,
        Generator::class,
        EntityCacheKeyGeneratorTest::class, // A bit complicated to refactor this test
    ];

    public function __construct(
        private ReflectionProvider $reflectionProvider,
    ) {
    }

    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node instanceof New_) {
            return [];
        }

        $class = $node->class;
        if (!$class instanceof Name) {
            return [];
        }

        $className = $class->toString();
        if (!$this->isSalesChannelContextOrChild($className)) {
            return [];
        }

        $currentClass = $scope->getClassReflection();
        if ($currentClass && \in_array($currentClass->getName(), self::$allowedClassesWhichCanCreateSalesChannelContext, true)) {
            return [];
        }

        return [
            RuleErrorBuilder::message('Manual creation of `Shopware\Core\System\SalesChannel\SalesChannelContext` is not allowed.')
                ->identifier('shopware.noManualSalesChannelContextCreation')
                ->addTip('Use `Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory` or `Shopware\Core\Test\Generator::createSalesChannelContext` instead.')
                ->build(),
        ];
    }

    private function isSalesChannelContextOrChild(string $className): bool
    {
        if (!$this->reflectionProvider->hasClass($className)) {
            return false;
        }

        $class = $this->reflectionProvider->getClass($className);
        if ($class->getName() === SalesChannelContext::class) {
            return true;
        }

        return $class->isSubclassOf(SalesChannelContext::class);
    }
}
