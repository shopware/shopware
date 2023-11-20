<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Exception;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;

/**
 * @deprecated tag:v6.7.0 - will be removed, use AppException::errorFlowCreateFromXmlFile instead
 */
#[Package('core')]
class AppFlowException extends XmlParsingException
{
    public function __construct(
        string $xmlFile,
        string $message
    ) {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'AppException::errorFlowCreateFromXmlFile')
        );

        parent::__construct(
            $xmlFile,
            $message
        );
    }
}
