<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelException;
use Symfony\Component\HttpFoundation\Response;

#[Package('discovery')]
class NoContextDataException extends SalesChannelException
{
    public function __construct(string $salesChannelId)
    {
        parent::__construct(
            Response::HTTP_PRECONDITION_FAILED,
            SalesChannelException::NO_CONTEXT_DATA_EXCEPTION,
            'No context data found for SalesChannel "{{ salesChannelId }}"',
            ['salesChannelId' => $salesChannelId]
        );
    }
}
