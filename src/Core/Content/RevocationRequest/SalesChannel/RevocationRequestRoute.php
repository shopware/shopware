<?php declare(strict_types=1);

namespace Shopware\Core\Content\RevocationRequest\SalesChannel;

use Psr\Clock\ClockInterface;
use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\RevocationRequest\Event\RevocationRequestEvent;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Event\EventData\MailRecipientStruct;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Framework\Validation\DataValidationFactoryInterface;
use Shopware\Core\Framework\Validation\DataValidator;
use Shopware\Core\Framework\Validation\Exception\ConstraintViolationException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('after-sales')]
class RevocationRequestRoute extends AbstractRevocationRequestRoute
{
    /**
     * @param EntityRepository<CmsSlotCollection> $cmsSlotRepository
     * @param EntityRepository<CategoryCollection> $categoryRepository
     * @param EntityRepository<LandingPageCollection> $landingPageRepository
     * @param EntityRepository<ProductCollection> $productRepository
     *
     * @internal
     */
    public function __construct(
        private readonly DataValidationFactoryInterface $revocationRequestFormValidationFactory,
        private readonly DataValidator $validator,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $cmsSlotRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly ClockInterface $clock,
        private readonly EntityRepository $landingPageRepository,
        private readonly EntityRepository $productRepository,
    ) {
    }

    public function getDecorated(): AbstractRevocationRequestRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/revocation-request-form', name: 'store-api.revocation-request.form', methods: [Request::METHOD_POST])]
    public function request(RequestDataBag $dataBag, SalesChannelContext $context): RevocationRequestRouteResponse
    {
        if (($request = $this->requestStack->getMainRequest()) !== null && $request->getClientIp() !== null) {
            $this->rateLimiter->ensureAccepted(RateLimiter::REVOCATION_REQUEST_FORM, $request->getClientIp());
        }

        EmailIdnConverter::encodeDataBag($dataBag);
        $dataBag->set('submitTime', $this->clock->now());

        $this->validateRevocationRequestForm($dataBag, $context);

        $mailConfig = $this->getMailConfig($context, $dataBag);

        if (!\is_array($mailConfig['receivers']) || $mailConfig['receivers'] === []) {
            $mailConfig['receivers'] = [$this->systemConfigService->getString('core.basicInformation.email', $context->getSalesChannelId()) => 'Admin'];
        } else {
            $mailConfig['receivers'] = \array_combine($mailConfig['receivers'], $mailConfig['receivers']);
        }

        $merchantMailRecipientStruct = new MailRecipientStruct($mailConfig['receivers']);
        $merchantEvent = new RevocationRequestEvent($context->getContext(), $context->getSalesChannelId(), $merchantMailRecipientStruct, $dataBag);
        $this->eventDispatcher->dispatch($merchantEvent, RevocationRequestEvent::EVENT_NAME);

        return new RevocationRequestRouteResponse($mailConfig['message'] ?? '');
    }

    private function validateRevocationRequestForm(DataBag $dataBag, SalesChannelContext $context): void
    {
        $definition = $this->revocationRequestFormValidationFactory->create($context);
        $violations = $this->validator->getViolations($dataBag->all(), $definition);

        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations, $dataBag->all());
        }
    }

    /**
     * @return array{receivers: array<int, string>|null, message: string|null}
     */
    private function getMailConfig(SalesChannelContext $context, RequestDataBag $dataBag): array
    {
        $slotId = $dataBag->get('slotId');
        $navigationId = $dataBag->get('navigationId');
        $entityName = $dataBag->get('entityName');

        $mailConfig = ['receivers' => null, 'message' => null];

        if (!$slotId) {
            return $mailConfig;
        }

        if ($navigationId) {
            $mailConfig = $this->getSlotConfig($slotId, $navigationId, $context, $entityName);

            if (\is_array($mailConfig['receivers']) && \is_string($mailConfig['message'])) {
                return $mailConfig;
            }
        }

        $criteria = new Criteria([$slotId]);
        $slotEntity = $this->cmsSlotRepository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$slotEntity) {
            return $mailConfig;
        }

        if (!\is_array($mailConfig['receivers'])) {
            $mailConfig['receivers'] = $slotEntity->getTranslated()['config']['mailReceiver']['value'];
        }

        if (!\is_string($mailConfig['message'])) {
            $mailConfig['message'] = $slotEntity->getTranslated()['config']['confirmationText']['value'];
        }

        return $mailConfig;
    }

    /**
     * @return array{receivers: array<int, string>|null, message: string|null}
     */
    private function getSlotConfig(string $slotId, string $navigationId, SalesChannelContext $context, ?string $entityName = null): array
    {
        $mailConfig = ['receivers' => null, 'message' => null];

        $criteria = new Criteria([$navigationId]);

        $entity = match ($entityName) {
            ProductDefinition::ENTITY_NAME => $this->productRepository->search($criteria, $context->getContext())->getEntities()->first(),
            LandingPageDefinition::ENTITY_NAME => $this->landingPageRepository->search($criteria, $context->getContext())->getEntities()->first(),
            default => $this->categoryRepository->search($criteria, $context->getContext())->getEntities()->first(),
        };

        if (!$entity || !$entity->getSlotConfig() || !\array_key_exists($slotId, $entity->getSlotConfig())) {
            return $mailConfig;
        }

        $slotConfig = $entity->getSlotConfig()[$slotId];

        if (\array_key_exists('mailReceiver', $slotConfig) && \array_key_exists('value', $slotConfig['mailReceiver'])) {
            $mailConfig['receivers'] = $slotConfig['mailReceiver']['value'];
        }

        if (\array_key_exists('confirmationText', $slotConfig) && \array_key_exists('value', $slotConfig['confirmationText'])) {
            $mailConfig['message'] = $slotConfig['confirmationText']['value'];
        }

        return $mailConfig;
    }
}
