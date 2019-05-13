<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Newsletter\Register;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class NewsletterRegisterPageLoader
{
    /**
     * @var GenericPageLoader
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
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        EntityRepositoryInterface $salutationRepository
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->genericLoader = $genericLoader;
        $this->salutationRepository = $salutationRepository;
    }

    public function load(Request $request, SalesChannelContext $context): NewsletterRegisterPage
    {
        $page = $this->genericLoader->load($request, $context);

        $page = NewsletterRegisterPage::createFrom($page);

        $searchResult = $this->salutationRepository->search(new Criteria(), Context::createDefaultContext());
        $page->setSalutations($searchResult->getEntities());

        $this->eventDispatcher->dispatch(
            new NewsletterRegisterPageLoadedEvent($page, $context, $request),
            NewsletterRegisterPageLoadedEvent::NAME
        );

        return $page;
    }
}
