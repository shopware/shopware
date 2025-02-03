<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Plugin\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class PluginComposerJsonInvalidException extends PluginException
{
    /**
     * @param list<string> $errors
     */
    public function __construct(
        string $composerJsonPath,
        array $errors
    ) {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__PLUGIN_COMPOSER_JSON_INVALID',
            'The file "{{ composerJsonPath }}" is invalid. Errors:' . \PHP_EOL . '{{ errorsString }}',
            ['composerJsonPath' => $composerJsonPath, 'errorsString' => implode(\PHP_EOL, $errors), 'errors' => $errors]
        );
    }
}
