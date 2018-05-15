<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Shopware\System\Currency\Repository\CurrencyRepository;
use Shopware\Api\Entity\Search\Criteria;
use Shopware\Api\Entity\Search\Query\TermQuery;
use Shopware\Api\Language\Repository\LanguageRepository;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\StorefrontApi\Context\StorefrontContextPersister;
use Shopware\StorefrontApi\Context\StorefrontContextService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ContextController extends StorefrontController
{
    /**
     * @var StorefrontContextPersister
     */
    private $contextPersister;

    /**
     * @var StorefrontContextService
     */
    private $storefrontContextService;

    /**
     * @var CurrencyRepository
     */
    private $currencyRepository;

    /**
     * @var LanguageRepository
     */
    private $languageRepository;

    public function __construct(
        StorefrontContextPersister $contextPersister,
        StorefrontContextService $storefrontContextService,
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
    public function setCurrency(Request $request, StorefrontContext $context)
    {
        $payload = [
            StorefrontContextService::CURRENCY_ID => $this->validateCurrency($request->get('__currency'), $context),
            StorefrontContextService::LANGUAGE_ID => $this->validateLanguage($request->get('__language'), $context),
        ];

        $payload = array_filter($payload);

        if (!empty($payload)) {
            $this->contextPersister->save($context->getToken(), $payload, $context->getTenantId());
            $this->storefrontContextService->refresh($context->getTenantId(), $context->getApplicationContext()->getApplicationId(), $context->getToken());
        }

        $target = $request->request->get('target') ?? $request->headers->get('referer');

        return new RedirectResponse($target);
    }

    /**
     * @throws BadRequestHttpException
     */
    private function validateCurrency(?string $currencyId, StorefrontContext $context): ?string
    {
        if (!$currencyId) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('currency.id', $currencyId));

        $currencies = $this->currencyRepository->searchIds($criteria, $context->getApplicationContext());

        if ($currencies->getTotal() !== 0) {
            return $currencyId;
        }

        throw new BadRequestHttpException('The provided currency does not exists.');
    }

    /**
     * @throws BadRequestHttpException
     */
    private function validateLanguage(?string $languageId, StorefrontContext $context): ?string
    {
        if (!$languageId) {
            return null;
        }

        $criteria = new Criteria();
        $criteria->addFilter(new TermQuery('language.id', $languageId));

        $currencies = $this->languageRepository->searchIds($criteria, $context->getApplicationContext());

        if ($currencies->getTotal() !== 0) {
            return $languageId;
        }

        throw new BadRequestHttpException('The provided language does not exists.');
    }
}
