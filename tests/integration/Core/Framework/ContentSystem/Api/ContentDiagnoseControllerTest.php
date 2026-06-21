<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ContentDiagnoseControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const DIAGNOSE_URL = '/api/_action/content-system/layout/diagnose';

    #[TestDox('reports a well-formed verdict for a layout of registered components without a bound source')]
    public function testDiagnoseReportsWellFormed(): void
    {
        $body = $this->diagnose(['layout' => [$this->element($this->registeredComponent())]]);

        static::assertTrue($body['diagnostics']['wellFormed']);
        static::assertArrayHasKey('resolutions', $body);
    }

    #[TestDox('reports an unregistered component as an intrinsic violation without persisting')]
    public function testDiagnoseReportsUnregisteredComponent(): void
    {
        $body = $this->diagnose(['layout' => [$this->element('Sw:Test:DefinitelyUnregistered')]]);

        static::assertFalse($body['diagnostics']['wellFormed']);
        $codes = array_column($body['diagnostics']['violations'], 'code');
        static::assertContains('unregistered_component', $codes);
    }

    #[TestDox('resolves a bound entity source from the entityType field and returns a binding verdict')]
    public function testDiagnoseWithEntitySource(): void
    {
        $body = $this->diagnose([
            'layout' => [$this->element($this->registeredComponent())],
            'entityType' => 'product',
        ]);

        static::assertArrayHasKey('resolvable', $body['diagnostics']);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function diagnose(array $payload): array
    {
        $this->getBrowser()->jsonRequest('POST', self::DIAGNOSE_URL, $payload);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        return json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>
     */
    private function element(string $component): array
    {
        return ['id' => Uuid::randomHex(), 'component' => $component, 'properties' => []];
    }

    private function registeredComponent(): string
    {
        $types = $this->getContainer()->get(ContentSystemElementTypeRegistry::class)->all();
        $name = array_key_first($types);
        static::assertIsString($name);

        return $name;
    }
}
