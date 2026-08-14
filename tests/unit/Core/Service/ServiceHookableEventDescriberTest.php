<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescription;
use Shopware\Core\Service\Event\CommercialLicenseProvidedEvent;
use Shopware\Core\Service\ServiceHookableEventDescriber;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ServiceHookableEventDescriber::class)]
class ServiceHookableEventDescriberTest extends TestCase
{
    public function testDescribeExposesServiceOnlyEventsSoTheyCanBeToldApartFromUnknownEvents(): void
    {
        $describer = new ServiceHookableEventDescriber();

        static::assertEquals($this->expectedDescriptions(), $describer->describe());
    }

    public function testDescribePermittedForWithholdsServiceOnlyEventsFromRegularApps(): void
    {
        $describer = new ServiceHookableEventDescriber();

        static::assertSame([], $describer->describePermittedFor($this->createManifest()));
    }

    public function testDescribePermittedForExposesServiceOnlyEventsForServices(): void
    {
        $describer = new ServiceHookableEventDescriber();
        $manifest = $this->createManifest();
        $manifest->getMetadata()->setSelfManaged(true);

        static::assertEquals($this->expectedDescriptions(), $describer->describePermittedFor($manifest));
    }

    /**
     * @return list<HookableEventDescription>
     */
    private function expectedDescriptions(): array
    {
        return [
            new HookableEventDescription(
                CommercialLicenseProvidedEvent::NAME,
                'Fires when the current commercial license data is provided to services.',
                []
            ),
        ];
    }

    private function createManifest(): Manifest
    {
        return Manifest::createFromXml(<<<XML
<?xml version="1.0" encoding="UTF-8"?>
<manifest xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
          xsi:noNamespaceSchemaLocation="https://raw.githubusercontent.com/shopware/shopware/trunk/src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd">
    <meta>
        <name>test-app</name>
        <label>Test app</label>
        <description>Test app</description>
        <author>shopware AG</author>
        <copyright>(c) by shopware AG</copyright>
        <version>1.0.0</version>
        <license>MIT</license>
    </meta>
</manifest>
XML);
    }
}
