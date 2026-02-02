<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Log;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentStatus;

#[Package('data-services')]
interface ConsentLogInterface
{
    public function log(ConsentStatus $action, string $consentName, ?string $identifier, string $actor): void;
}
