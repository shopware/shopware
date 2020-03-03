<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Contact;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

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
     * @var EntityRepositoryInterface
     */
    private $salutationRepository;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $salutationRepository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
        $this->salutationRepository = $salutationRepository;
    }

    public function load(Request $request, SalesChannelContext $context): ContactPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = ContactPage::createFrom($page);

        /** @var SalutationCollection $salutations */
        $salutations = $this->salutationRepository->search(new Criteria(), $context->getContext())->getEntities();

        $page->setSalutations($salutations);

        $this->eventDispatcher->dispatch(new ContactPageLoadedEvent($page, $context, $request));

        return $page;
    }
}
