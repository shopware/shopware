<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\PHPStan\Rules;

use PHPat\Selector\Selector;
use PHPat\Test\Attributes\TestRule;
use PHPat\Test\Builder\Rule;
use PHPat\Test\PHPat;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[Package('core')]
class MessageHandlerFinalRule
{
    #[TestRule]
    public function isMessageHandlerFinal(): Rule
    {
        return PHPat::rule()
            ->classes(Selector::appliesAttribute(AsMessageHandler::class))
            ->shouldBeFinal();
    }
}
