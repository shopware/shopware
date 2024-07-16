<?php declare(strict_types=1);

namespace Shopware\Core\System\Snippet\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\SnippetException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.7.0 - Will be removed in v6.7.0.0. Use SnippetException::filterNotFound instead
 *
 * @codeCoverageIgnore
 */
#[Package('services-settings')]
class FilterNotFoundException extends SnippetException
{
    public function __construct(string $filterName, string $class)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.7.0.0', 'SnippetException::filterNotFound'),
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            'FRAMEWORK__FILTER_NOT_FOUND',
            'The filter "{{ filter }}" was not found in "{{ class }}".',
            ['filter' => $filterName, 'class' => $class]
        );
    }
}
