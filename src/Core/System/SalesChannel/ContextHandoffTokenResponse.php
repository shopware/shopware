<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Struct\ContextHandoffTokenResponseStruct;

/**
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\System\SalesChannel\SalesChannel\ContextHandoffRouteTest
 *
 * @extends StoreApiResponse<ContextHandoffTokenResponseStruct>
 */
#[Package('framework')]
class ContextHandoffTokenResponse extends StoreApiResponse
{
    public function __construct(ContextHandoffTokenResponseStruct $handoffToken)
    {
        parent::__construct($handoffToken);
    }

    public function getHandoffToken(): string
    {
        return $this->object->token;
    }

    public function getExpiresAt(): string
    {
        return $this->object->expiresAt;
    }
}
