<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\Snippet\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\System\Snippet\DataTransfer\TranslationUpdate\TranslationUpdateResult;
use Shopware\Core\System\Snippet\ScheduledTask\UpdateTranslationsTaskHandler;
use Shopware\Core\System\Snippet\Service\TranslationUpdater;

/**
 * @internal
 */
#[CoversClass(UpdateTranslationsTaskHandler::class)]
class UpdateTranslationsTaskHandlerTest extends TestCase
{
    public function testRunDelegatesToUpdater(): void
    {
        $updater = $this->createMock(TranslationUpdater::class);
        $updater->expects($this->once())
            ->method('updateInstalled')
            ->with(static::isInstanceOf(Context::class))
            ->willReturn(new TranslationUpdateResult());

        $this->handler($updater)->run();
    }

    private function handler(TranslationUpdater&MockObject $updater): UpdateTranslationsTaskHandler
    {
        return new UpdateTranslationsTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $updater,
        );
    }
}
