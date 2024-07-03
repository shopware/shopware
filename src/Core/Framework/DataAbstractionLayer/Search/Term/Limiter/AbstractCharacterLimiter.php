<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Search\Term\Limiter;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\Service\ResetInterface;

#[Package('core')]
abstract class AbstractCharacterLimiter implements ResetInterface
{
    public function reset(): void
    {
        $this->getDecorated()->reset();
    }

    abstract public function getDecorated(): AbstractCharacterLimiter;

    /**
     * @param list<string> $tokens
     *
     * @return list<string>
     */
    abstract public function limit(array $tokens, Context $context): array;
}
