<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ContextController extends StorefrontController
{
    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    /**
     * @var CheckoutContextService
     */
    private $checkoutContextService;

    /**
     * @var RepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var RepositoryInterface
     */
    private $languageRepository;

    public function __construct(
        CheckoutContextPersister $contextPersister,
        CheckoutContextService $checkoutContextService,
        RepositoryInterface $currencyRepository,
        RepositoryInterface $languageRepository
    ) {
        $this->contextPersister = $contextPersister;
        $this->checkoutContextService = $checkoutContextService;
        $this->currencyRepository = $currencyRepository;
        $this->languageRepository = $languageRepository;
    }

    /**
     * @Route("/context/update", name="context_update", methods={"POST"})
     */
    public function setCurrency(Request $request, CheckoutContext $context): RedirectResponse
    {
        $payload = array_filter([
            CheckoutContextService::CURRENCY_ID => $this->validateCurrency($request->get('__currency'), $context),
            CheckoutContextService::LANGUAGE_ID => $this->validateLanguage($request->get('__language'), $context),
        ]);

        if (!empty($payload)) {
            $this->contextPersister->save($context->getToken(), $payload, $context->getTenantId());
            $this->checkoutContextService->refresh($context->getTenantId(), $context->getContext()->getSourceContext()->getSalesChannelId(), $context->getToken());
        }

        $target = $request->request->get('target') ?? $request->headers->get('referer');

        return new RedirectResponse($target);
    }

    /**
     * @throws BadRequestHttpException
     */
    private function validateCurrency(?string $currencyId, CheckoutContext $context): ?string
    {
        if (!$currencyId) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('currency.id', $currencyId));

        $currencies = $this->currencyRepository->searchIds($criteria, $context->getContext());

        if ($currencies->getTotal() !== 0) {
            return $currencyId;
        }

        throw new BadRequestHttpException('The provided currency does not exists.');
    }

    /**
     * @throws BadRequestHttpException
     */
    private function validateLanguage(?string $languageId, CheckoutContext $context): ?string
    {
        if (!$languageId) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('language.id', $languageId));

        $currencies = $this->languageRepository->searchIds($criteria, $context->getContext());

        if ($currencies->getTotal() !== 0) {
            return $languageId;
        }

        throw new BadRequestHttpException('The provided language does not exists.');
    }
}
