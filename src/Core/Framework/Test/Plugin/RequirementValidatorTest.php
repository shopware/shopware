<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Plugin;

use DateTime;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Framework;
use Shopware\Core\Framework\Plugin\Exception\PluginToShopwareCompatibilityException;
use Shopware\Core\Framework\Plugin\PluginEntity;
use Shopware\Core\Framework\Plugin\RequirementValidator;
use Shopware\Core\Framework\Plugin\XmlPluginInfoReader;
use Shopware\Core\Framework\ShopwareHttpException;

class RequirementValidatorTest extends TestCase
{
    private const REQUIRED_PLUGIN_NAME = 'SwagTest';

    private const VALID_PLUGIN_VERSION = '1.2.3';

    private const VALID_PLUGIN_XML_FILE = __DIR__ . '/_fixtures/valid_plugin.xml';

    private const VALID_SHOPWARE_VERSION = '6.0.1';

    private const BLACKLISTED_SHOPWARE_VERSION = '6.0.5';

    private const INVALID_SHOPWARE_VERSION_MINIMUM = '5.0.0';

    private const INVALID_SHOPWARE_VERSION_MAXIMUM = '7.0.0';

    public function testValidate(): void
    {
        $validator = $this->createRequirementValidator();
        $pluginEntity = $this->createTestPluginEntity();

        try {
            $validator->validate(
                self::VALID_PLUGIN_XML_FILE,
                self::VALID_SHOPWARE_VERSION,
                [self::REQUIRED_PLUGIN_NAME => $pluginEntity]
            );
        } catch (ShopwareHttpException $e) {
            static::fail('This method call should not throw an exception');
        }

        self::assertTrue(true);
    }

    public function testValidateInvalidXmlPath(): void
    {
        $validator = $this->createRequirementValidator();

        try {
            $validator->validate(self::VALID_PLUGIN_XML_FILE . 'foo', self::VALID_SHOPWARE_VERSION, []);
        } catch (ShopwareHttpException $e) {
            static::fail('This method call should not throw an exception');
        }

        self::assertTrue(true);
    }

    public function testValidateShopwareVersionPlaceholder(): void
    {
        $validator = $this->createRequirementValidator();
        $pluginEntity = $this->createTestPluginEntity();

        try {
            $validator->validate(
                self::VALID_PLUGIN_XML_FILE,
                Framework::VERSION,
                [self::REQUIRED_PLUGIN_NAME => $pluginEntity]
            );
        } catch (ShopwareHttpException $e) {
            static::fail('This method call should not throw an exception');
        }

        self::assertTrue(true);
    }

    public function testValidateShopwareBlacklist(): void
    {
        $validator = $this->createRequirementValidator();

        $this->expectException(PluginToShopwareCompatibilityException::class);
        $this->expectExceptionMessage(
            sprintf('Shopware version %s is blacklisted by this plugin', self::BLACKLISTED_SHOPWARE_VERSION)
        );
        $validator->validate(self::VALID_PLUGIN_XML_FILE, self::BLACKLISTED_SHOPWARE_VERSION, []);
    }

    public function testValidateShopwareMinimum(): void
    {
        $validator = $this->createRequirementValidator();

        $this->expectException(PluginToShopwareCompatibilityException::class);
        $this->expectExceptionMessage('This plugin requires at least Shopware version 6.0.0');
        $validator->validate(self::VALID_PLUGIN_XML_FILE, self::INVALID_SHOPWARE_VERSION_MINIMUM, []);
    }

    public function testValidateShopwareMaximum(): void
    {
        $validator = $this->createRequirementValidator();

        $this->expectException(PluginToShopwareCompatibilityException::class);
        $this->expectExceptionMessage('This plugin is only compatible with Shopware version smaller or equal to 6.1.0');
        $validator->validate(self::VALID_PLUGIN_XML_FILE, self::INVALID_SHOPWARE_VERSION_MAXIMUM, []);
    }

    private function createRequirementValidator(): RequirementValidator
    {
        return new RequirementValidator(new XmlPluginInfoReader());
    }

    private function createTestPluginEntity(): PluginEntity
    {
        $pluginEntity = new PluginEntity();
        $pluginEntity->setInstallationDate(new DateTime());
        $pluginEntity->setActive(true);
        $pluginEntity->setVersion(self::VALID_PLUGIN_VERSION);
        $pluginEntity->setName(self::REQUIRED_PLUGIN_NAME);

        return $pluginEntity;
    }
}
