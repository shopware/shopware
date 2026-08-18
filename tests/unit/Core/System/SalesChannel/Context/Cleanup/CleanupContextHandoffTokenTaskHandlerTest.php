<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\Context\Cleanup;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Context\Cleanup\CleanupContextHandoffTokenTaskHandler;
use Shopware\Core\System\SalesChannel\Context\ContextHandoffTokenStore;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CleanupContextHandoffTokenTaskHandler::class)]
class CleanupContextHandoffTokenTaskHandlerTest extends TestCase
{
    public function testRunDeletesExpiredTokens(): void
    {
        $tokenStore = $this->createMock(ContextHandoffTokenStore::class);
        $tokenStore->expects($this->once())->method('deleteExpired');

        $handler = new CleanupContextHandoffTokenTaskHandler(
            static::createStub(EntityRepository::class),
            static::createStub(LoggerInterface::class),
            $tokenStore
        );

        $handler->run();
    }
}
