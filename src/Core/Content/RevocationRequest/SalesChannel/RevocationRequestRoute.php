<?php declare(strict_types=1);

namespace Shopware\Core\Content\RevocationRequest\SalesChannel;

use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
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
        $dataBag->set('submitTime', new \DateTimeImmutable());

        $this->validateRevocationRequestForm($dataBag, $context);

        $mailConfig = $this->getMailConfig($context, $dataBag);

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
     * @return array{receivers: array<string, string>, message?: string|null}
     */
    private function getMailConfig(SalesChannelContext $context, RequestDataBag $dataBag): array
    {
        $slotId = $dataBag->get('slotId');
        $navigationId = $dataBag->get('navigationId');
        $mailConfig = ['receivers' => []];

        if (!$slotId) {
            return $this->createDefaultConfig($context, $mailConfig);
        }

        if ($navigationId) {
            $criteria = new Criteria([$navigationId]);
            $categoryEntity = $this->categoryRepository->search($criteria, $context->getContext())->first();

            if ($categoryEntity instanceof CategoryEntity && !empty($categoryEntity->getSlotConfig()[$slotId])) {
                $categoryEntityConfig = $categoryEntity->getSlotConfig()[$slotId];
                $this->addReceivers($mailConfig, $categoryEntityConfig);
                $mailConfig['message'] = $this->getStringMessage($categoryEntityConfig['confirmationText']['value']);
            }
        }

        if (!empty($mailConfig['receivers'])) {
            return $mailConfig;
        }

        $criteria = new Criteria([$slotId]);
        $slotEntity = $this->cmsSlotRepository->search($criteria, $context->getContext())->getEntities()->first();

        if (!$slotEntity) {
            return $this->createDefaultConfig($context, $mailConfig);
        }

        $slotConfig = $slotEntity->getTranslated()['config'];
        $this->addReceivers($mailConfig, $slotConfig);
        $mailConfig['message'] = $this->getStringMessage($slotConfig['confirmationText']['value']);

        if (empty($mailConfig['receivers'])) {
            return $this->createDefaultConfig($context, $mailConfig);
        }

        return $mailConfig;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{receivers: array<string>, message?: string|null}
     */
    private function createDefaultConfig(SalesChannelContext $context, array $config): array
    {
        $config['receivers'][$this->systemConfigService->get('core.basicInformation.email', $context->getSalesChannelId())] = 'Admin';

        return $config;
    }

    /**
     * @param array<string, mixed> $mailConfig
     * @param array<string, mixed> $slotConfig
     */
    private function addReceivers(array &$mailConfig, array $slotConfig): void
    {
        $receivers = $slotConfig['mailReceiver']['value'] ?? null;

        if (\is_array($receivers)) {
            foreach ($receivers as $receiver) {
                $mailConfig['receivers'][$receiver] = $receiver;
            }
        }
    }

    private function getStringMessage(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (\is_string($value)) {
            return $value;
        }

        return (string) $value;
    }
}
