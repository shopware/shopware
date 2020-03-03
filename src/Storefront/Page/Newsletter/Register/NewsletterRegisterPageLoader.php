<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Newsletter\Register;

use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class NewsletterRegisterPageLoader
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
     * @var SalesChannelRepositoryInterface
     */
    private $salutationRepository;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        SalesChannelRepositoryInterface $salutationRepository
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->salutationRepository = $salutationRepository;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): NewsletterRegisterPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = NewsletterRegisterPage::createFrom($page);

        /** @var SalutationCollection $salutationCollection */
        $salutationCollection = $this->salutationRepository->search(new Criteria(), $salesChannelContext)->getEntities();
        $page->setSalutations($salutationCollection);

        $this->eventDispatcher->dispatch(
            new NewsletterRegisterPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }
}
