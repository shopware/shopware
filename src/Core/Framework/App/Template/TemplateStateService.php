<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Template;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
#[Package('core')]
class TemplateStateService
{
    public function __construct(private readonly EntityRepository $templateRepo)
    {
    }

    public function activateAppTemplates(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', false));

        /** @var array<string> $templates */
        $templates = $this->templateRepo->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(fn (string $id) => ['id' => $id, 'active' => true], $templates);

        $this->templateRepo->update($updateSet, $context);
    }

    public function deactivateAppTemplates(string $appId, Context $context): void
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('appId', $appId));
        $criteria->addFilter(new EqualsFilter('active', true));

        /** @var array<string> $templates */
        $templates = $this->templateRepo->searchIds($criteria, $context)->getIds();

        $updateSet = array_map(fn (string $id) => ['id' => $id, 'active' => false], $templates);

        $this->templateRepo->update($updateSet, $context);
    }
}
