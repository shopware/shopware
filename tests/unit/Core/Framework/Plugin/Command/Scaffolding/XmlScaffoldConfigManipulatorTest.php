<?php

namespace Shopware\Tests\Unit\Core\Framework\Plugin\Command\Scaffolding;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Plugin\Command\Scaffolding\XmlScaffoldConfigManipulator;

class XmlScaffoldConfigManipulatorTest extends TestCase
{
    private const SERVICE_ENTRY = '<service id="my_service" class="MyClass" />';
    private const ROUTE_ENTRY = '<import resource="../../Path/To/**/*Controller.php" type="attribute" />';
    private const CONFIG_ENTRY = '<card>
        <title>Minimal configuration</title>

        <input-field type="text">
            <name>textField</name>
            <label>Test field with default value</label>
            <defaultValue>test</defaultValue>
        </input-field>
    </card>';

    private const EXPECTED_SERVICE_CONFIG_FROM_EMPTY =
        '<?xml version="1.0"?>
<container xmlns="http://symfony.com/schema/dic/services" xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <services>
    <service id="my_service" class="MyClass"></service>
  </services>
</container>
';

    private const EXPECTED_ROUTE_CONFIG_FROM_EMPTY =
        '<?xml version="1.0"?>
<routes xmlns="http://symfony.com/schema/routing" xsi:schemaLocation="http://symfony.com/schema/routing         https://symfony.com/schema/routing/routing-1.0.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <import resource="../../Path/To/**/*Controller.php" type="attribute"></import>
</routes>
';

    private const EXPECTED_CONFIG_CONFIG_FROM_EMPTY =
        '<?xml version="1.0"?>
<config xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/System/SystemConfig/Schema/config.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <card>
    <title>Minimal configuration</title>
    <input-field type="text">
      <name>textField</name>
      <label>Test field with default value</label>
      <defaultValue>test</defaultValue>
    </input-field>
  </card>
</config>
';
    private const EXPECTED_SERVICE_CONFIG =
        '<?xml version="1.0"?>
 <container xmlns="http://symfony.com/schema/dic/services" xsi:schemaLocation="http://symfony.com/schema/dic/services http://symfony.com/schema/dic/services/services-1.0.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <parameters>
    <parameter key="param_1" type="string">true</parameter>
    <parameter key="param_2" type="string">false</parameter>
  </parameters>
   <services>
    <service id="id_service" public="true">
      <argument type="service" id="test_argument"></argument>
    </service>
     <service id="my_service" class="MyClass"></service>
   </services>
 </container>
 ';

    private const EXPECTED_ROUTE_CONFIG =
        '<?xml version="1.0"?>
<routes xmlns="http://symfony.com/schema/routing" xsi:schemaLocation="http://symfony.com/schema/routing         https://symfony.com/schema/routing/routing-1.0.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <import resource="../../Path/To/Another/**/*Controller.php" type="attribute"></import>
  <import resource="../../Path/To/**/*Controller.php" type="attribute"></import>
</routes>
';

    private const EXPECTED_CONFIG_CONFIG =
        '<?xml version="1.0"?>
<config xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/System/SystemConfig/Schema/config.xsd" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <card>
    <title>Configuration</title>
    <input-field type="int">
      <name>intField</name>
      <label>Test field with default value</label>
      <defaultValue>7</defaultValue>
    </input-field>
  </card>
  <card>
    <title>Minimal configuration</title>
    <input-field type="text">
      <name>textField</name>
      <label>Test field with default value</label>
      <defaultValue>test</defaultValue>
    </input-field>
  </card>
</config>
';

//    #[TestWith([__DIR__ . '/config-stubs/empty/services.xml', 'MyNamespace', self::SERVICE_ENTRY, self::EXPECTED_SERVICE_CONFIG])]
    #[TestWith([__DIR__ . '/config-stubs/empty/routes.xml', 'MyNamespace', self::ROUTE_ENTRY, self::EXPECTED_ROUTE_CONFIG])]
//    #[TestWith([__DIR__ . '/config-stubs/empty/config.xml', 'MyNamespace', self::CONFIG_ENTRY, self::EXPECTED_CONFIG_CONFIG])]

//    #[TestWith([__DIR__ . '/config-stubs/not-empty/services.xml', 'MyNamespace', self::SERVICE_ENTRY, self::EXPECTED_SERVICE_CONFIG])]
//    #[TestWith([__DIR__ . '/config-stubs/not-empty/routes.xml', 'MyNamespace', self::ROUTE_ENTRY, self::EXPECTED_ROUTE_CONFIG])]
//    #[TestWith([__DIR__ . '/config-stubs/not-empty/config.xml', 'MyNamespace', self::CONFIG_ENTRY, self::EXPECTED_CONFIG_CONFIG])]
    public function testAddConfig(string $configFile, string $namespace, string $entry, string $expected): void
    {
        $manipulator = new XmlScaffoldConfigManipulator();
        $config = $manipulator->addConfig(
            $configFile,
            $namespace,
            $entry
        );

        self::assertSame($expected, $config);
    }
}
