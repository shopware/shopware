<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Command\CreateHydratorCommand;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\Framework\Util\InMemoryUtilFilesystem;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CreateHydratorCommand::class)]
class CreateHydratorCommandTest extends TestCase
{
    private const ROOT_DIR = '/app';

    private const COMMAND_DIR = self::ROOT_DIR . '/src/Tests/Unit/Core/Framework/DataAbstractionLayer/Command';

    public function testExecuteGeneratesPhpServiceDefinitionFile(): void
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [HydratorCommandTestDefinition::class, AaaHydratorCommandTestDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        // the command reads the definition source files to add getHydratorClass() when missing
        $filesystem = new InMemoryUtilFilesystem([
            self::COMMAND_DIR . '/HydratorCommandTestDefinition.php' => '<?php // contains getHydratorClass already',
            self::COMMAND_DIR . '/AaaHydratorCommandTestDefinition.php' => '<?php // contains getHydratorClass already',
        ]);

        $command = new CreateHydratorCommand($registry, $filesystem, self::ROOT_DIR);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['whitelist' => ['hydrator_command_test', 'aaa_hydrator_command_test']]);

        $commandTester->assertCommandIsSuccessful();

        $written = $filesystem->dumpedFiles();
        static::assertSame([
            self::COMMAND_DIR . '/HydratorCommandTestDefinition.php',
            self::COMMAND_DIR . '/AaaHydratorCommandTestDefinition.php',
            self::COMMAND_DIR . '/HydratorCommandTestHydrator.php',
            self::COMMAND_DIR . '/AaaHydratorCommandTestHydrator.php',
            self::ROOT_DIR . '/src/Core/Framework/DependencyInjection/hydrator.php',
        ], array_keys($written));
        static::assertSame('<?php // contains getHydratorClass already', $written[self::COMMAND_DIR . '/HydratorCommandTestDefinition.php']);

        $expected = <<<'EOF'
<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection;

use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Command\AaaHydratorCommandTestHydrator;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Command\HydratorCommandTestHydrator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(HydratorCommandTestHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(AaaHydratorCommandTestHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);
};

EOF;

        static::assertSame($expected, $written[self::ROOT_DIR . '/src/Core/Framework/DependencyInjection/hydrator.php'] ?? null);
    }
}

/**
 * @internal
 */
class AaaHydratorCommandTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'aaa_hydrator_command_test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
        ]);
    }
}

/**
 * @internal
 */
class HydratorCommandTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'hydrator_command_test';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new StringField('name', 'name'),
        ]);
    }
}
