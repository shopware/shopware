<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Payload\Struct;

/**
 * @internal only for use by the app-system
 */
interface SourcedPayloadInterface extends \JsonSerializable
{
    public function setSource(Source $source): void;
}
