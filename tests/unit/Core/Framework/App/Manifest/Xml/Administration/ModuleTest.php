<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Manifest\Xml\Administration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\App\Manifest\Xml\Administration\Module;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Module::class)]
class ModuleTest extends TestCase
{
    public function testToArrayAddsLabelForTheDefaultLocale(): void
    {
        $manifest = Manifest::createFromXmlFile(__DIR__ . '/../../_fixtures/test/manifest.xml');
        $admin = $manifest->getAdmin();
        static::assertNotNull($admin);

        $result = $admin->getModules()[0]->toArray('de-AT');

        static::assertSame(
            [
                'en-GB' => 'My first own module',
                'de-DE' => 'Mein erstes eigenes Modul',
                'de-AT' => 'Mein erstes eigenes Modul',
            ],
            $result['label']
        );
        static::assertSame('first-module', $result['name']);
    }
}
