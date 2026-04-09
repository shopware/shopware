<?php declare(strict_types=1);

namespace Shopware\Core\Test\PHPUnit\Extension\RequestStackDirty\Subscriber;

use PHPUnit\Event\Test\PreparationStarted;
use PHPUnit\Event\Test\PreparationStartedSubscriber;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Kernel;
use Shopware\Core\Test\PHPUnit\Extension\RequestStackDirty\StackDepth;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
class TestPreparationStartedSubscriber implements PreparationStartedSubscriber
{
    public function __construct(private readonly StackDepth $stackDepth)
    {
    }

    public function notify(PreparationStarted $event): void
    {
        $this->stackDepth->before = self::currentDepth();
    }

    public static function currentDepth(): int
    {
        $kernelProperty = new \ReflectionProperty(KernelLifecycleManager::class, 'kernel');
        $kernel = $kernelProperty->getValue();

        if (!$kernel instanceof Kernel) {
            return 0;
        }

        try {
            $stack = $kernel->getContainer()->get('request_stack');
        } catch (\Throwable) {
            return 0;
        }

        if (!$stack instanceof RequestStack) {
            return 0;
        }

        $requestsProperty = new \ReflectionProperty(RequestStack::class, 'requests');

        return \count($requestsProperty->getValue($stack));
    }
}
