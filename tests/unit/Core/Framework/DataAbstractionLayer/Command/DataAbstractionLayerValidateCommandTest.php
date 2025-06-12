<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Command\DataAbstractionLayerValidateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * @internal
 */
#[CoversClass(DataAbstractionLayerValidateCommand::class)]
class DataAbstractionLayerValidateCommandTest extends TestCase
{
    use KernelTestBehaviour;

    public function testValidationErrors(): void
    {
        $validator = $this->createMock(DefinitionValidator::class);
        $validator->method('validate')->willReturn([
            'Shopware\\Core\\Content\\Product\\ProductDefinition' => ['Error 1', 'Error 2'],
            'Shopware\\Core\\Content\\Category\\CategoryDefinition' => ['Error 3'],
        ]);
        $command = new DataAbstractionLayerValidateCommand($validator);
        $commandTester = new CommandTester($command);
        $commandTester->execute([]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('Found 3 errors in 2 entities', $commandTester->getDisplay());
        static::assertStringContainsString('ProductDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('CategoryDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('Error 1', $commandTester->getDisplay());
        static::assertStringContainsString('Error 3', $commandTester->getDisplay());
    }

    public function testJsonOutput(): void
    {
        $validator = $this->createMock(DefinitionValidator::class);
        $validator->method('validate')->willReturn([
            'Shopware\\Core\\Content\\Product\\ProductDefinition' => ['Error 1'],
        ]);
        $command = new DataAbstractionLayerValidateCommand($validator);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--json' => true]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('ProductDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('Error 1', $commandTester->getDisplay());
        static::assertJson($commandTester->getDisplay());
    }

    public function testNamespaceFilter(): void
    {
        $validator = $this->createMock(DefinitionValidator::class);
        $validator->method('validate')->willReturn([
            'Shopware\\Core\\Content\\Product\\ProductDefinition' => ['Error 1'],
            'Shopware\\Core\\Content\\Category\\CategoryDefinition' => ['Error 2'],
            'Other\\Namespace\\Foo' => ['Error 3'],
        ]);
        $command = new DataAbstractionLayerValidateCommand($validator);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--namespaces' => ['Shopware\\Core\\Content\\Product']]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('ProductDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('Error 1', $commandTester->getDisplay());
        static::assertStringNotContainsString('CategoryDefinition', $commandTester->getDisplay());
        static::assertStringNotContainsString('Error 2', $commandTester->getDisplay());
        static::assertStringNotContainsString('Error 3', $commandTester->getDisplay());
    }

    public function testNamespaceFilterWithPartialNamespace(): void
    {
        $validator = $this->createMock(DefinitionValidator::class);
        $validator->method('validate')->willReturn([
            'Shopware\\Core\\Content\\Product\\ProductDefinition' => ['Error 1'],
            'Shopware\\Core\\Content\\Category\\CategoryDefinition' => ['Error 2'],
            'Other\\Namespace\\Foo' => ['Error 3'],
        ]);
        $command = new DataAbstractionLayerValidateCommand($validator);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--namespaces' => ['Shopware\\Core']]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('ProductDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('CategoryDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('Error 1', $commandTester->getDisplay());
        static::assertStringContainsString('Error 2', $commandTester->getDisplay());
        static::assertStringNotContainsString('Error 3', $commandTester->getDisplay());
        static::assertStringNotContainsString('Foo', $commandTester->getDisplay());
    }

    public function testNamespaceFilterWithMultipleNamespaces(): void
    {
        $validator = $this->createMock(DefinitionValidator::class);
        $validator->method('validate')->willReturn([
            'Shopware\\Core\\Content\\Product\\ProductDefinition' => ['Error 1'],
            'Shopware\\Core\\Content\\Category\\CategoryDefinition' => ['Error 2'],
            'Other\\Namespace\\Foo' => ['Error 3'],
            'Another\\Namespace\\Bar' => ['Error 4'],
        ]);
        $command = new DataAbstractionLayerValidateCommand($validator);
        $commandTester = new CommandTester($command);
        $commandTester->execute(['--namespaces' => ['Shopware\\Core\\Content\\Product', 'Other\\Namespace']]);

        static::assertSame(1, $commandTester->getStatusCode());
        static::assertStringContainsString('ProductDefinition', $commandTester->getDisplay());
        static::assertStringContainsString('Error 1', $commandTester->getDisplay());
        static::assertStringContainsString('Foo', $commandTester->getDisplay());
        static::assertStringContainsString('Error 3', $commandTester->getDisplay());
        static::assertStringNotContainsString('CategoryDefinition', $commandTester->getDisplay());
        static::assertStringNotContainsString('Error 2', $commandTester->getDisplay());
        static::assertStringNotContainsString('Bar', $commandTester->getDisplay());
        static::assertStringNotContainsString('Error 4', $commandTester->getDisplay());
    }
}
