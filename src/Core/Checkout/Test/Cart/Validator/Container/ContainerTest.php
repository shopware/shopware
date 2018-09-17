<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Validator\Container;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Test\Cart\Common\FalseRule;
use Shopware\Core\Checkout\Test\Cart\Common\TrueRule;
use Shopware\Core\Framework\Rule\Container\AndRule;

class ContainerTest extends TestCase
{
    public function testConstructorWithRules(): void
    {
        $container = new AndRule([
            new TrueRule(),
        ]);

        static::assertEquals(
            [new TrueRule()],
            $container->getRules()
        );

        $container->setRules([
            new FalseRule(),
        ]);

        static::assertEquals(
            [new FalseRule()],
            $container->getRules()
        );
    }
}
