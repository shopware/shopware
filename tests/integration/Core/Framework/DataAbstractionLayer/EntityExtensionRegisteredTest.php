<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Integration\IntegrationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

/**
 * @internal
 */
class EntityExtensionRegisteredTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testAdmin(): void
    {
        try {
            $integrationRepository = static::getContainer()->get('integration.repository');
        } catch (\Exception $e) {
            static::markTestSkipped('Integration repository is not available');
        }

        static::assertInstanceOf(EntityRepository::class, $integrationRepository);
        $definition = $integrationRepository->getDefinition();
        static::assertInstanceOf(IntegrationDefinition::class, $definition);

        $fields = $definition->getFields();
        static::assertTrue($fields->has('createdNotifications'));
    }

    public function testStorefront(): void
    {
        if (!static::getContainer()->has('theme.repository')) {
            static::markTestSkipped('Theme repository is not available');
        }

        $salesChannel = static::getContainer()->get('sales_channel.repository');

        static::assertInstanceOf(EntityRepository::class, $salesChannel);
        $definition = $salesChannel->getDefinition();
        static::assertInstanceOf(SalesChannelDefinition::class, $definition);

        $fields = $definition->getFields();
        static::assertTrue($fields->has('themes'));
    }
}
