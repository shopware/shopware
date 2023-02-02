<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
interface SourcedPayloadInterface extends \JsonSerializable
{
    public function setSource(Source $source): void;
}
