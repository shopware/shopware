<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Subscriber;

use Shopware\Core\Checkout\DocumentV2\Config\DocumentNumberGenerator;
use Shopware\Core\Checkout\DocumentV2\DocumentType;
use Shopware\Core\Framework\App\Event\AppInstalledEvent;
use Shopware\Core\Framework\App\Event\AppUpdatedEvent;
use Shopware\Core\Framework\App\Event\ManifestChangedEvent;
use Shopware\Core\Framework\App\Manifest\Xml\Document\DocumentType as AppDocumentType;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeType\NumberRangeTypeCollection;
use Shopware\Core\System\NumberRange\NumberRangeCollection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Seeds a `number_range_type` and matching `number_range` for every document type an app declares,
 * so `DocumentNumberGenerator` (which looks up range type `document_<identifier>`) can generate
 * numbers for app registered document types.
 *
 * @internal
 *
 * @codeCoverageIgnore
 *
 * @see \Shopware\Tests\Integration\Core\Checkout\DocumentV2\Subscriber\AppDocumentNumberRangeSubscriberTest
 */
#[Package('after-sales')]
class AppDocumentNumberRangeSubscriber implements EventSubscriberInterface
{
    /**
     * @param EntityRepository<NumberRangeTypeCollection> $numberRangeTypeRepository
     * @param EntityRepository<NumberRangeCollection> $numberRangeRepository
     */
    public function __construct(
        private readonly EntityRepository $numberRangeTypeRepository,
        private readonly EntityRepository $numberRangeRepository
    ) {
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            AppInstalledEvent::class => 'onAppInstalledOrUpdated',
            AppUpdatedEvent::class => 'onAppInstalledOrUpdated',
        ];
    }

    public function onAppInstalledOrUpdated(ManifestChangedEvent $event): void
    {
        $documents = $event->getManifest()->getDocuments();

        if ($documents === null) {
            return;
        }

        $context = $event->getContext();

        foreach ($documents->getDocumentTypes() as $documentType) {
            $this->createNumberRange($documentType, $context);
        }
    }

    private function createNumberRange(AppDocumentType $documentType, Context $context): void
    {
        $identifier = $documentType->getIdentifier();

        // prevent duplication of core type
        if (DocumentType::tryFrom($identifier) !== null) {
            return;
        }

        $technicalName = DocumentNumberGenerator::NUMBER_RANGE_DOCUMENT_TYPE_PREFIX . $identifier;

        $criteria = (new Criteria())->addFilter(new EqualsFilter('technicalName', $technicalName));
        $existingIds = $this->numberRangeTypeRepository->searchIds($criteria, $context);

        if ($existingIds->firstId() !== null) {
            return;
        }

        $typeId = Uuid::randomHex();

        $this->numberRangeTypeRepository->create([[
            'id' => $typeId,
            'technicalName' => $technicalName,
            'global' => true,
            'typeName' => $identifier,
        ]], $context);

        $this->numberRangeRepository->create([[
            'id' => Uuid::randomHex(),
            'typeId' => $typeId,
            'global' => true,
            'name' => $identifier,
            'pattern' => '{n}',
            'start' => 1000,
        ]], $context);
    }
}
