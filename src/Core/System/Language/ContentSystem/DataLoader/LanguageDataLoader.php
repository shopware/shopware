<?php declare(strict_types=1);

namespace Shopware\Core\System\Language\ContentSystem\DataLoader;

use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\Language\SalesChannel\AbstractLanguageRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Loads available languages via AbstractLanguageRoute.
 *
 * @internal
 *
 * @final
 *
 * @extends AbstractContentDataLoader<LanguageCollection>
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

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
        ]);
    }

    public function load(
        LoaderInputs $inputs,
        DataRequirement $requirement,
        SalesChannelContext $context,
        Request $request
    ): ContentDataLoaderResult {
        $criteria = new Criteria();

        foreach ($inputs->stringList('associations') as $association) {
            $criteria->addAssociation($association);
        }

        // A failure Shopware modelled as an HTTP outcome degrades the element; anything beneath that line,
        // such as a \TypeError, an \AssertionError, or a database driver failure, propagates. No domain
        // exception is reachable through this chain today: LanguageRoute::load() collects a cache tag, adds
        // the translationCode association and runs one sales-channel repository search. The wrap is here for
        // uniformity across the loaders and because the reachable set is open, so a decorator or a future
        // route change can add a class no enumeration here would name.
        try {
            $response = $this->languageRoute->load($request, $context, $criteria);
        } catch (ShopwareHttpException) {
            return ContentDataLoaderResult::notFound();
        }

        // LanguageRoute handles its own caching internally
        return ContentDataLoaderResult::cachedExternally($response->getLanguages());
    }
}
