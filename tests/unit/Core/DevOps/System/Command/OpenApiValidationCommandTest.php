<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\DevOps\System\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\System\Command\OpenApiValidationCommand;
use Shopware\Core\Framework\Api\ApiDefinition\DefinitionService;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * @internal
 */
#[CoversClass(OpenApiValidationCommand::class)]
class OpenApiValidationCommandTest extends TestCase
{
    public function testRunWithoutErrors(): void
    {
        $command = new OpenApiValidationCommand(
            new MockHttpClient([new MockResponse('{"messages": [], "schemaValidationMessages": []}', [])]),
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
                [new MockResponse(json_encode([
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
