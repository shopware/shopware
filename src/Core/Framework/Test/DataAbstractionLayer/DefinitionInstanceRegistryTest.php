<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Acl\Role\AclUserRoleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class DefinitionInstanceRegistryTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testFallBackEntityName(): void
    {
        $definitionInstanceRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);

        $entityDefinition = $definitionInstanceRegistry->getByEntityName('acl_user_role');
        static::assertInstanceOf(AclUserRoleDefinition::class, $entityDefinition);

        $entityDefinitionFallBack = $definitionInstanceRegistry->getByEntityName('user_role_acl');
        static::assertInstanceOf(AclUserRoleDefinition::class, $entityDefinition);
        static::assertSame($entityDefinition, $entityDefinitionFallBack);
    }

    public function testFallBackEntityNameForRepository(): void
    {
        $definitionInstanceRegistry = $this->getContainer()->get(DefinitionInstanceRegistry::class);

        $entityRepository = $definitionInstanceRegistry->getRepository('acl_user_role');
        static::assertInstanceOf(EntityRepository::class, $entityRepository);

        $entityRepositoryFallBack = $definitionInstanceRegistry->getRepository('user_role_acl');
        static::assertInstanceOf(EntityRepository::class, $entityRepositoryFallBack);

        static::assertSame($entityRepository, $entityRepositoryFallBack);
    }

    public function testFallBackRepositoryName(): void
    {
        $entityRepository = $this->getContainer()->get('acl_user_role.repository');
        static::assertInstanceOf(EntityRepository::class, $entityRepository);

        $entityRepositoryFallBack = $this->getContainer()->get('user_role_acl.repository');
        static::assertInstanceOf(EntityRepository::class, $entityRepositoryFallBack);

        static::assertSame($entityRepository, $entityRepositoryFallBack);
    }
}
