<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Contact;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @deprecated tag:v6.5.0 the according controller was already removed, use store-api ContactRoute instead
 */
class ContactPageLoader
{
    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var EntityRepository
     */
    private $salutationRepository;

    /**
     * @internal
     */
    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        EntityRepository $salutationRepository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
        $this->salutationRepository = $salutationRepository;
    }

    public function load(Request $request, SalesChannelContext $context): ContactPage
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', 'ContactRoute')
        );

        $page = $this->genericLoader->load($request, $context);

        $page = ContactPage::createFrom($page);

        /** @var SalutationCollection $salutations */
        $salutations = $this->salutationRepository->search(new Criteria(), $context->getContext())->getEntities();

        $page->setSalutations($salutations);

        $this->eventDispatcher->dispatch(new ContactPageLoadedEvent($page, $context, $request));

        return $page;
    }
}
