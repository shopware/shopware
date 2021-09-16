<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\Exception\LanguageOfSalesChannelDomainDeleteException;

class SalesChannelExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    /**
     * @internal (flag:FEATURE_NEXT_16640) - second parameter WriteCommand $command will be removed
     */
    public function matchException(\Exception $e, ?WriteCommand $command = null): ?\Exception
    {
        if ($e->getCode() !== 0) {
            return null;
        }

        if (preg_match('/SQLSTATE\[23000\]:.*1451.*a foreign key constraint.*sales_channel_domain.*CONSTRAINT `fk.sales_channel_domain.language_id`/', $e->getMessage())) {
            return new LanguageOfSalesChannelDomainDeleteException('', $e);
        }

        return null;
    }
}
