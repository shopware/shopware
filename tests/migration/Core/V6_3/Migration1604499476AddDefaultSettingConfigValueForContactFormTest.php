<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Migration\V6_3\Migration1604499476AddDefaultSettingConfigValueForContactForm;

/**
 * @internal
 * @covers \Shopware\Core\Migration\V6_3\Migration1604499476AddDefaultSettingConfigValueForContactForm
 */
class Migration1604499476AddDefaultSettingConfigValueForContactFormTest extends TestCase
{
    /**
     * @testWith [true]
     *           [1]
     *           ["1"]
     *           [false]
     *           [" "]
     *
     * @param bool|int|string $configPresent
     */
    public function testDoesNotOverwriteValuesWhenAlreadyConfigured($configPresent): void
    {
        $migration = new Migration1604499476AddDefaultSettingConfigValueForContactForm();
        $abortCondition = $configPresent !== false;

        $connection = $this->getMockBuilder(Connection::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['fetchColumn', 'insert'])
            ->getMock();

        $connection->method('fetchColumn')
            ->willReturn($configPresent);

        // Assert that an insert only happens when the abort condition is not met
        $connection->expects($abortCondition ? static::never() : static::atMost(3))
            ->method('insert');

        $migration->update($connection);
    }
}
