<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\App\Flow\Action;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\Flow\Action\Action;

/**
 * @internal
 */
#[CoversClass(Action::class)]
class ActionTest extends TestCase
{
    public function testCreateFromXmlWithFlowAction(): void
    {
        $xmlFile = __DIR__ . '/../../_fixtures/Resources/flow.xml';
        $flowActions = Action::createFromXmlFile($xmlFile);

        static::assertSame(\dirname($xmlFile), $flowActions->getPath());
        static::assertNotNull($flowActions->getActions());
        static::assertCount(1, $flowActions->getActions()->getActions());
    }

    #[DataProvider('invalidFlowActionProvider')]
    public function testCreateFromXmlFailsForInvalidFlowAction(string $fixture, string $message): void
    {
        $file = __DIR__ . '/../../_fixtures/Resources/' . $fixture;

        $this->expectExceptionObject(AppException::createFromXmlFileFlowError($file, $message));

        Action::createFromXmlFile($file);
    }

    /**
     * @return iterable<string, array{fixture: string, message: string}>
     */
    public static function invalidFlowActionProvider(): iterable
    {
        yield 'missing flow-action' => [
            'fixture' => 'flow-action-without-actions.xml',
            'message' => '[ERROR 1871] Element \'flow-actions\': Missing child element(s). Expected is ( flow-action ).',
        ];

        yield 'missing action child' => [
            'fixture' => 'flow-action-without-required-child.xml',
            'message' => '[ERROR 1871] Element \'flow-action\': Missing child element(s). Expected is one of ( headers, parameters, config ).',
        ];

        yield 'missing config child' => [
            'fixture' => 'flow-action-config-without-required-child.xml',
            'message' => '[ERROR 1871] Element \'config\': Missing child element(s). Expected is ( input-field ).',
        ];

        yield 'invalid input field type' => [
            'fixture' => 'flow-action-invalid-input-field-type.xml',
            'message' => '[ERROR 1840] Element \'input-field\', attribute \'type\': [facet \'enumeration\'] The value \'shopware\' is not an element of the set {\'text\', \'textarea\', \'text-editor\', \'url\', \'password\', \'int\', \'float\', \'bool\', \'checkbox\', \'datetime\', \'date\', \'time\', \'colorpicker\', \'single-select\', \'multi-select\'}.',
        ];
    }
}
