<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Webhook\Hookable;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventCollector;

/**
 * @internal
 */
class HookableEventCollectorTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var HookableEventCollector
     */
    private $hookableEventCollector;

    protected function setUp(): void
    {
        $this->hookableEventCollector = $this->getContainer()->get(HookableEventCollector::class);
    }

    public function testGetHookableEventNamesWithPrivileges(): void
    {
        $hookableEventNamesWithPrivileges = $this->hookableEventCollector->getHookableEventNamesWithPrivileges(Context::createDefaultContext());
        static::assertNotEmpty($hookableEventNamesWithPrivileges);

        foreach ($hookableEventNamesWithPrivileges as $key => $hookableEventNamesWithPrivilege) {
            static::assertIsArray($hookableEventNamesWithPrivilege);
            static::assertIsString($key);
            static::assertArrayHasKey('privileges', $hookableEventNamesWithPrivilege);
        }
    }
}
