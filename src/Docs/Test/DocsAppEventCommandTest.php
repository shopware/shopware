<?php declare(strict_types=1);

namespace Shopware\Docs\Test;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Docs\Command\App\DocsAppEventCommand;

class DocsAppEventCommandTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $remoteWebhookEvent = 'https://raw.githubusercontent.com/shopware/docs/master/resources/references/app-reference/webhook-events-reference.md';

    public function testUptoDateEventDoc(): void
    {
        $remoteContents = @file_get_contents($this->remoteWebhookEvent);

        if ($remoteContents === false) {
            // Can not accept to remote document path (error networking or site is down)
            // In that case need to be passed without blocked test
            $this->expectNotToPerformAssertions();

            return;
        }

        static::assertEquals(
            md5($remoteContents),
            md5($this->getDocumentForCheckUptoDate()),
            'The webhook events app system document is not up to date' . \PHP_EOL
            . 'Run command docs:app-system-events to get new the webhook-events-reference.md file' . \PHP_EOL
            . 'Publish that file to gitbook at /resources/references/app-reference/webhook-events-reference.md'
        );
    }

    private function getDocumentForCheckUptoDate(): string
    {
        $docsAppEventCommand = $this->getContainer()->get(DocsAppEventCommand::class);

        return $docsAppEventCommand->render();
    }
}
