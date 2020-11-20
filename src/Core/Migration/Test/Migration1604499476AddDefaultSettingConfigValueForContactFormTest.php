<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Migration\Migration1604499476AddDefaultSettingConfigValueForContactForm;

class Migration1604499476AddDefaultSettingConfigValueForContactFormTest extends TestCase
{
    /**
     * @testWith [true]
     *           [1]
     *           ["1"]
     *           [false]
     *           [" "]
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
