<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Requirement\Exception;

use Shopware\Core\Framework\Log\Package;

#[Package('core')]
class ComposerNameMissingException extends RequirementException
{
    public function __construct(
        string $pluginName,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            '"{{ pluginName }}" has no "name" property in its composer.json file',
            ['pluginName' => $pluginName],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'FRAMEWORK__PLUGIN_COMPOSER_NAME_MISSING';
    }
}
