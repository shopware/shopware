<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(UpdateCommand::class)]
class UpdateCommandTest extends TestCase
{
    public function testSetAndGetImmutableChanges(): void
    {
        $definition = new class extends EntityDefinition {
            final public const ENTITY_NAME = 'webhook';

            public function getEntityName(): string
            {
                return self::ENTITY_NAME;
            }

            public function getDefaults(): array
            {
                return [
                    'errorCount' => 0,
                ];
            }

            protected function defineFields(): FieldCollection
            {
                return new FieldCollection([
                    (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
                    (new StringField('name', 'name'))->addFlags(new Required()),
                    (new IntField('error_count', 'errorCount', 0))->addFlags(new Required(), new WriteProtected(Context::SYSTEM_SCOPE)),
                ]);
            }
        };

        $registry = new StaticDefinitionInstanceRegistry(
            [$definition],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $command = $this->getMockForAbstractClass(
            UpdateCommand::class,
            [
                $registry->getByEntityName($definition->getEntityName()),
                [],
                [],
                $this->createMock(EntityExistence::class),
                'path/to/entity',
            ]
        );

        $immutableChanges = ['field1', 'field2', 'field3'];
        $command->setImmutableChanges($immutableChanges);

        static::assertEquals($immutableChanges, $command->getImmutableChanges());
    }
}
