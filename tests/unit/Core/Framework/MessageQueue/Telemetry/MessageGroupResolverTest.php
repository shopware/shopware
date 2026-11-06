<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\MessageQueue\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cleanup\CleanupCartTask;
use Shopware\Core\Content\Flow\Indexing\FlowIndexingMessage;
use Shopware\Core\Content\ImportExport\Message\ImportExportMessage;
use Shopware\Core\Content\Mail\Message\SendMailMessage;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Framework\Adapter\Cache\Message\RefreshHttpCacheMessage;
use Shopware\Core\Framework\App\Message\RotateAppSecretMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\MessageQueue\IterateEntityIndexerMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\RegisterScheduledTaskMessage;
use Shopware\Core\Framework\MessageQueue\Telemetry\MessageGroupResolver;
use Shopware\Core\Framework\Telemetry\Metrics\Config\LabelMappingRegistry;
use Shopware\Core\Framework\Webhook\Message\WebhookEventMessage;
use Shopware\Core\Framework\Webhook\ScheduledTask\CleanupWebhookEventLogTask;
use Shopware\Core\System\UsageData\EntitySync\DispatchEntityMessage;
use Shopware\Storefront\Theme\Message\CompileThemeMessage;
use Symfony\Component\Mailer\Messenger\SendEmailMessage;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(MessageGroupResolver::class)]
class MessageGroupResolverTest extends TestCase
{
    /**
     * Should correspond to the metric definition in telemetry.yaml
     */
    private const KNOWN_GROUPS = ['indexer', 'webhook', 'scheduled-task', 'mail', 'business', 'system', 'other'];

    #[DataProvider('messageProvider')]
    public function testResolve(string $messageClass, string $expected): void
    {
        $resolved = $this->createResolver()->resolve($messageClass);
        static::assertSame($expected, $resolved);
        // Hard guard of the documented output set
        static::assertContains($resolved, self::KNOWN_GROUPS);
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function messageProvider(): \Generator
    {
        // scheduled tasks — resolved via the ScheduledTask base class, so plugin tasks group too
        yield 'scheduled task' => [CleanupCartTask::class, 'scheduled-task'];
        // a task living under Framework\Webhook must stay a scheduled-task, not fall to the webhook fragment
        yield 'webhook cleanup task is scheduled-task, not webhook' => [CleanupWebhookEventLogTask::class, 'scheduled-task'];

        // indexer — EntityIndexingMessage subclasses + indexer control messages
        yield 'entity indexing message' => [ProductIndexingMessage::class, 'indexer'];
        yield 'flow index rebuild is indexer, not flow' => [FlowIndexingMessage::class, 'indexer'];
        yield 'indexer iterate control message' => [IterateEntityIndexerMessage::class, 'indexer'];

        yield 'webhook delivery' => [WebhookEventMessage::class, 'webhook'];

        // mail — Shopware rendering message and the Symfony mailer transport send
        yield 'mail render message' => [SendMailMessage::class, 'mail'];
        yield 'symfony mailer transport send' => [SendEmailMessage::class, 'mail'];

        // business — customer-/catalog-facing async work
        yield 'thumbnail generation' => [GenerateThumbnailsMessage::class, 'business'];
        yield 'import/export' => [ImportExportMessage::class, 'business'];

        // system — framework infrastructure & housekeeping
        yield 'http cache refresh' => [RefreshHttpCacheMessage::class, 'system'];
        yield 'app secret rotation' => [RotateAppSecretMessage::class, 'system'];
        yield 'theme compile' => [CompileThemeMessage::class, 'system'];
        yield 'scheduled task registration' => [RegisterScheduledTaskMessage::class, 'system'];
        yield 'usage data sync' => [DispatchEntityMessage::class, 'system'];

        // unknown/plugin messages fall through to other
        yield 'unknown plugin message is other' => ['Swag\\Example\\Message\\DoThingMessage', 'other'];
    }

    public function testPluginOptsInWithPrefixEntry(): void
    {
        $resolver = $this->createResolver(['Swag\\Example\\Message\\*' => 'blog']);

        static::assertSame('blog', $resolver->resolve('Swag\\Example\\Message\\DoThingMessage'));
    }

    public function testConfiguredEntryBeatsCodeLevelClassification(): void
    {
        // configured beats code-level: an extension or operator can correct the heuristic
        $resolver = $this->createResolver([CleanupCartTask::class => 'cart']);

        static::assertSame('cart', $resolver->resolve(CleanupCartTask::class));
    }

    /**
     * @param array<string, string> $entries
     */
    private function createResolver(array $entries = []): MessageGroupResolver
    {
        // core ships an empty table: the core grouping is the resolver's code-level rule
        return new MessageGroupResolver(new LabelMappingRegistry([MessageGroupResolver::MAPPING => $entries], 'other'));
    }
}
