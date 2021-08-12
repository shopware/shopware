<?php declare(strict_types=1);

namespace Shopware\Docs\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Docs\Command\App\DocsAppEventCommand;

class DocsAppEventCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testUptoDateEventDoc(): void
    {
        $docsAppEventCommand = $this->getContainer()->get(DocsAppEventCommand::class);

        $savedContents = @file_get_contents($docsAppEventCommand->getListEventPath()) ?: '';

        static::assertEquals(
            md5($savedContents),
            md5($docsAppEventCommand->render()),
            'The webhook events app system document is not up to date' . \PHP_EOL
            . 'Run command docs:app-system-events to get new the webhook-events-reference.md file' . \PHP_EOL
            . 'This file also need to be uploaded to gitbook at /resources/references/app-reference/webhook-events-reference.md!'
        );
    }
}
