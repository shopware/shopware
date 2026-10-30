<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\RuleFixture;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class CasesOutOfScope
{
    public function sharedDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $dispatcher->addListener('event', static function (): void {});
    }
}
