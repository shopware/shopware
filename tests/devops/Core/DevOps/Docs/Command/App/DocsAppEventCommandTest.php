<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\Docs\Command\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\DevOps\Docs\App\DocsAppEventCommand;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

/**
 * @internal
 */
#[CoversClass(DocsAppEventCommand::class)]
class DocsAppEventCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUptoDateEventDoc(): void
    {
        // Always check if the docs are up-to-date for the current minor branch
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $docsAppEventCommand = $this->getContainer()->get(DocsAppEventCommand::class);

        $savedContents = @file_get_contents($docsAppEventCommand->getListEventPath()) ?: '';

        static::assertEquals(
            md5($savedContents),
            md5((string) $docsAppEventCommand->render()),
            'The webhook events app system document is not up to date' . \PHP_EOL
            . 'Run command docs:app-system-events to get new the webhook-events-reference.md file' . \PHP_EOL
            . 'This file also need to be uploaded to gitbook at /resources/references/app-reference/webhook-events-reference.md!'
        );
    }
}
