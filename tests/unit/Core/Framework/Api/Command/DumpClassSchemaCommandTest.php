<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Command\DumpClassSchemaCommand;
use Shopware\Core\Framework\Rule\RuleCollection;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(DumpClassSchemaCommand::class)]
class DumpClassSchemaCommandTest extends TestCase
{
    public function testThrowsExceptionWhenClassGivenIsInvalid(): void
    {
        $this->expectExceptionObject(
            new \InvalidArgumentException(
                \sprintf(
                    'Invalid class given %s',
                    RuleCollection::class
                )
            )
        );

        $command = new DumpClassSchemaCommand(['Framework' => ['path' => 'path/to/framework']]);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['class' => RuleCollection::class, 'name' => 'rule_collection']);
    }
}
