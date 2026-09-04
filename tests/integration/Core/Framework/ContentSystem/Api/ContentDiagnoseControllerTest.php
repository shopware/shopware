<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
class ContentDiagnoseControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const DIAGNOSE_URL = '/api/_action/content-system/layout/diagnose';

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ids = new IdsCollection();
    }

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

    #[TestDox('reports an unregistered style option as an unknown_style_option violation keyed on the option name')]
    public function testDiagnoseReportsUnknownStyleOption(): void
    {
        $element = $this->element($this->registeredComponent());
        $element['style'] = ['definitely-not-a-style-option' => ['xs' => 'x']];

        $body = $this->diagnose(['layout' => [$element]]);

        static::assertFalse($body['diagnostics']['wellFormed']);

        $violations = array_values(array_filter(
            $body['diagnostics']['violations'],
            static fn (array $violation): bool => $violation['code'] === 'unknown_style_option',
        ));

        static::assertCount(1, $violations);
        static::assertSame('intrinsic', $violations[0]['scope']);
        static::assertSame('error', $violations[0]['severity']);
        static::assertSame('definitely-not-a-style-option', $violations[0]['key']);
    }

    #[TestDox('reports a numeric wiring key as an invalid_config violation attributed to the offending element')]
    public function testDiagnoseReportsNumericWiringKeyAsInvalidConfigViolation(): void
    {
        $elementId = $this->ids->get('element');
        $element = [
            'id' => $elementId,
            'component' => $this->registeredComponent(),
            'properties' => [1 => 'x'],
        ];

        $body = $this->diagnose(['layout' => [$element]]);

        static::assertFalse($body['diagnostics']['wellFormed']);

        $violations = array_values(array_filter(
            $body['diagnostics']['violations'],
            static fn (array $violation): bool => $violation['code'] === 'invalid_config',
        ));

        static::assertCount(1, $violations);
        static::assertSame($elementId, $violations[0]['elementId']);
        static::assertSame('Element property map key must be string, got int', $violations[0]['message']);
    }

    #[TestDox('resolves the root source from the rootSource field and returns a resolvability verdict')]
    public function testDiagnoseWithRootSource(): void
    {
        $body = $this->diagnose([
            'layout' => [$this->element($this->registeredComponent())],
            'rootSource' => 'product',
        ]);

        static::assertArrayHasKey('resolvable', $body['diagnostics']);
    }

    #[TestDox('rejects an unknown rootSource with a 400 and the unknownRootSource code, never reaching resolve')]
    public function testDiagnoseRejectsUnknownRootSource(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::DIAGNOSE_URL, [
            'layout' => [$this->element($this->registeredComponent())],
            'rootSource' => 'definitely-not-a-root-source',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::UNKNOWN_ROOT_SOURCE, array_column($body['errors'], 'code'));
    }

    #[TestDox('rejects an unknown request field with a 400 and the unknownRequestField code')]
    public function testDiagnoseRejectsUnknownRequestField(): void
    {
        $this->getBrowser()->jsonRequest('POST', self::DIAGNOSE_URL, [
            'layout' => [$this->element($this->registeredComponent())],
            'entityType' => 'product',
        ]);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), (string) $response->getContent());

        $body = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains(ContentSystemException::UNKNOWN_REQUEST_FIELD, array_column($body['errors'], 'code'));
    }

    #[TestDox('treats an empty rootSource as absent and reports intrinsic well-formedness without gating')]
    public function testDiagnoseTreatsEmptyRootSourceAsAbsent(): void
    {
        $body = $this->diagnose([
            'layout' => [$this->element($this->registeredComponent())],
            'rootSource' => '',
        ]);

        static::assertTrue($body['diagnostics']['wellFormed']);
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
        return ['id' => $this->ids->get('element'), 'component' => $component, 'properties' => []];
    }

    private function registeredComponent(): string
    {
        $types = $this->getContainer()->get(ContentSystemElementTypeRegistry::class)->all();
        $name = array_key_first($types);
        static::assertIsString($name);

        return $name;
    }
}
