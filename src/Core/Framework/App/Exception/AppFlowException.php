<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;

#[Package('core')]
class AppFlowException extends XmlParsingException
{
    public function __construct(
        string $xmlFile,
        string $message
    ) {
        parent::__construct($xmlFile, $message);
    }
}
