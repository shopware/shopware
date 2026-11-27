<?php declare(strict_types=1);

namespace Shopware\Core\Framework\SystemCheck\Message;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\SystemCheck\Check\SystemCheckExecutionContext;
use Shopware\Core\Framework\SystemCheck\SystemChecker;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('framework')]
#[AsMessageHandler]
final readonly class RunSystemChecksHandler
{
    public function __construct(private SystemChecker $systemChecker)
    {
    }

    public function __invoke(RunSystemChecks $message): void
    {
        $this->systemChecker->check(SystemCheckExecutionContext::cases(), $message->check);
    }
}
