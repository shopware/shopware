<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\System\Command;

use AsyncAws\Core\Test\Http\SimpleMockedResponse;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\System\Command\OpenApiValidationCommand;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;

/**
 * @internal
 *
 * @covers \Shopware\Core\DevOps\System\Command\OpenApiValidationCommand
 */
class OpenApiValidationCommandTest extends TestCase
{
    public function testRunWithoutErrors(): void
    {
        $command = new OpenApiValidationCommand(
            new MockHttpClient([new SimpleMockedResponse('{"messages": [], "schemaValidationMessages": []}', [])]),
            $this->createMock(DefinitionService::class)
        );
        $tester = new CommandTester($command);

        $tester->execute([]);

        static::assertSame($tester->getStatusCode(), 0);
    }

    public function testRunWithErrors(): void
    {
        $command = new OpenApiValidationCommand(
            new MockHttpClient(
                [new SimpleMockedResponse(json_encode([
                    'schemaValidationMessages' => [
                        [
                            'level' => 'error',
                            'domain' => 'validation',
                            'keyword' => 'oneOf',
                            'message' => 'instance failed to match exactly one schema (matched 0 out of 2)',
                            'schema' => [
                                'loadingURI' => '#',
                                'pointer' => "\/definitions\/Components\/properties\/schemas\/patternProperties\/^[a-zA-Z0-9\\.\\-_]+$",
                            ],
                            'instance' => [
                                'pointer' => "\/components\/schemas\/foo",
                            ],
                        ],
                    ],
                    'messages' => [],
                ], \JSON_THROW_ON_ERROR), [])]
            ),
            $this->createMock(DefinitionService::class)
        );
        $tester = new CommandTester($command);

        $tester->execute([]);

        static::assertSame($tester->getStatusCode(), 1);
    }
}
