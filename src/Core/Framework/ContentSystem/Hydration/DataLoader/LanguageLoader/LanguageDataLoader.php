<?php declare(strict_types=1);

namespace Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LanguageLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Language\SalesChannel\AbstractLanguageRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads available languages via AbstractLanguageRoute.
 *
 * Config:
 * - associations: Additional associations to load
 *
 * @internal
 *
 * @final
 */
#[Package('framework')]
class LanguageDataLoader extends AbstractContentDataLoader
{
    public const SOURCE = 'language';

    public function __construct(
        private readonly AbstractLanguageRoute $languageRoute,
    ) {
    }

    public static function getRequirementType(): string
    {
        return self::SOURCE;
    }

    public function load(
        ContentElement $element,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $config = $requirement->config;

        $criteria = new Criteria();

        if ($config instanceof LanguageLoaderConfig) {
            foreach ($config->associations as $association) {
                $criteria->addAssociation($association);
            }
        }

        $response = $this->languageRoute->load($request, $context, $criteria);

        // LanguageRoute handles its own caching internally
        return ContentDataLoaderResult::cachedExternally($response->getLanguages());
    }
}
