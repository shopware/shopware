<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Template;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Template\TemplateCollection;
use Shopware\Core\Framework\App\Template\TemplateDefinition;
use Shopware\Core\Framework\App\Template\TemplateEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TemplateDefinition::class)]
class TemplateDefinitionTest extends TestCase
{
    public function testEntityConfiguration(): void
    {
        $definition = $this->createDefinition();

        static::assertSame(TemplateDefinition::ENTITY_NAME, $definition->getEntityName());
        static::assertSame(TemplateEntity::class, $definition->getEntityClass());
        static::assertSame(TemplateCollection::class, $definition->getCollectionClass());
        static::assertSame('6.3.1.0', $definition->since());
    }

    public function testFieldsAreDefined(): void
    {
        static::assertNotNull($this->createDefinition()->getFields()->get('id'));
    }

    private function createDefinition(): TemplateDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [TemplateDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );

        $definition = $registry->getByEntityName(TemplateDefinition::ENTITY_NAME);
        static::assertInstanceOf(TemplateDefinition::class, $definition);

        return $definition;
    }
}
