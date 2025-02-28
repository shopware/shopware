<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[Package('checkout')]
class MessageHandlerFinalRule
{
    public function testMessageHandlersAreFinal(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::appliesAttribute(AsMessageHandler::class))
            ->excluding(Selector::isAbstract())
            ->shouldBeFinal()
            ->because('MessageHandlers must be final, so they cannot be extended/overwritten.');
    }
}
