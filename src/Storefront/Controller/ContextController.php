<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextPersister;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Framework\ORM\Search\Criteria;
use Shopware\Core\Framework\ORM\Search\Query\TermQuery;
use Shopware\Core\System\Currency\CurrencyRepository;
use Shopware\Core\System\Language\LanguageRepository;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ContextController extends StorefrontController
{
    /**
     * @var CheckoutContextPersister
     */
    private $contextPersister;

    /**
     * @var CheckoutContextService
     */
    private $storefrontContextService;

    /**
     * @var CurrencyRepository
     */
    private $currencyRepository;

    /**
     * @var \Shopware\Core\System\Language\LanguageRepository
     */
    private $languageRepository;

    public function __construct(
        CheckoutContextPersister $contextPersister,
        CheckoutContextService $storefrontContextService,
        CurrencyRepository $currencyRepository,
        LanguageRepository $languageRepository
    ) {
        $this->contextPersister = $contextPersister;
        $this->storefrontContextService = $storefrontContextService;
        $this->currencyRepository = $currencyRepository;
        $this->languageRepository = $languageRepository;
    }

    /**
     * @Route("/context/update", name="context_update")
     * @Method("POST")
     */
    public function setCurrency(Request $request, CheckoutContext $context)
    {
        $payload = [
            CheckoutContextService::CURRENCY_ID => $this->validateCurrency($request->get('__currency'), $context),
            CheckoutContextService::LANGUAGE_ID => $this->validateLanguage($request->get('__language'), $context),
        ];

        $payload = array_filter($payload);

        if (!empty($payload)) {
            $this->contextPersister->save($context->getToken(), $payload, $context->getTenantId());
            $this->storefrontContextService->refresh($context->getTenantId(), $context->getContext()->getTouchpointId(), $context->getToken());
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
        $criteria->addFilter(new TermQuery('currency.id', $currencyId));

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
        $criteria->addFilter(new TermQuery('language.id', $languageId));

        $currencies = $this->languageRepository->searchIds($criteria, $context->getContext());

        if ($currencies->getTotal() !== 0) {
            return $languageId;
        }

        throw new BadRequestHttpException('The provided language does not exists.');
    }
}
