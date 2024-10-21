<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Rule\RuleException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\UnsupportedCommandTypeException;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

/**
 * @internal
 */
#[CoversClass(RuleException::class)]
class RuleExceptionTest extends TestCase
{
    public function testUnsupportedCommandType(): void
    {
        $definition = new ProductDefinition();
        $definition->compile($this->createMock(DefinitionInstanceRegistry::class));
        $exception = RuleException::unsupportedCommandType(new InsertCommand(
            $definition,
            [],
            [],
            new EntityExistence(ProductDefinition::ENTITY_NAME, [], true, false, false, []),
            ''
        ));

        static::assertInstanceOf(UnsupportedCommandTypeException::class, $exception);
        static::assertSame('Command of class Shopware\Core\Framework\DataAbstractionLayer\Write\Command\InsertCommand is not supported by product', $exception->getMessage());
    }
}
