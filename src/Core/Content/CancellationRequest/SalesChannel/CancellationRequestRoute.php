<?php declare(strict_types=1);

namespace Shopware\Core\Content\CancellationRequest\SalesChannel;

use phpseclib3\File\ASN1\Maps\HoldInstructionCode;
use Shopware\Core\Checkout\Customer\Service\EmailIdnConverter;
use Shopware\Core\Content\CancellationRequest\Event\CancellationRequestEvent;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
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
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
#[Package('after-sales')]
class CancellationRequestRoute extends AbstractCancellationRequestRoute
{
    /**
     * @param EntityRepository<CmsSlotCollection> $cmsSlotRepository
     * @param EntityRepository<CategoryCollection> $categoryRepository
     *
     * @internal
     */
    public function __construct(
        private readonly DataValidationFactoryInterface $cancellationRequestFormValidationFactory,
        private readonly DataValidator $validator,
        private readonly RequestStack $requestStack,
        private readonly RateLimiter $rateLimiter,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $cmsSlotRepository,
        private readonly EntityRepository $categoryRepository,
    ) {
    }

    public function getDecorated(): self
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/cancellation-request-form', name: 'store-api.cancellation-request.form', methods: ['POST'])]
    public function request(RequestDataBag $dataBag, SalesChannelContext $context): CancellationRequestRouteResponse
    {
        EmailIdnConverter::encodeDataBag($dataBag);
        $dataBag->set('submitTime', new \DateTimeImmutable());

        $this->validateCancellationRequestForm($dataBag, $context);

        if (($request = $this->requestStack->getMainRequest()) !== null && $request->getClientIp() !== null) {
            $this->rateLimiter->ensureAccepted(RateLimiter::CANCELLATION_REQUEST_FORM, $request->getClientIp());
        }

        $mailConfig = $this->getMailConfig($context, $dataBag);

        $merchantMailRecipientStruct = new MailRecipientStruct($mailConfig['receivers']);
        $merchantEvent = new CancellationRequestEvent($context->getContext(), $context->getSalesChannelId(), $merchantMailRecipientStruct, $dataBag);
        $this->eventDispatcher->dispatch($merchantEvent, CancellationRequestEvent::EVENT_NAME);

        $responseStruct = new CancellationRequestFormRouteResponseStruct();
        $responseStruct->assign([
            'individualSuccessMessage' => $mailConfig['message'] ?? '',
        ]);

        return new CancellationRequestRouteResponse($responseStruct);
    }

    private function validateCancellationRequestForm(DataBag $dataBag, SalesChannelContext $context): void
    {
        $definition = $this->cancellationRequestFormValidationFactory->create($context);
        $violations = $this->validator->getViolations($dataBag->all(), $definition);

        if ($violations->count() > 0) {
            throw new ConstraintViolationException($violations, $dataBag->all());
        }
    }

    /**
     * @return array{receivers: array<string, string>, message?: array<string>|null}
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
                $mailConfig['message'] = $categoryEntityConfig['confirmationText']['value'] ?? '';
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

        $this->addReceivers($mailConfig, $slotEntity->getTranslated()['config']);
        $mailConfig['message'] = $slotEntity->getTranslated()['config']['confirmationText']['value'] ?? '';

        if (empty($mailConfig['receivers'])) {
            return $this->createDefaultConfig($context, $mailConfig);
        }

        return $mailConfig;
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return array{receivers: array<string>, message?: array<int, string>|null}
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
}
