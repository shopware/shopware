<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Zugferd;

use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdDocumentValidator;
use horstoeko\zugferd\ZugferdProfiles;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Document\DocumentException;
use Shopware\Core\Checkout\Document\Struct\DocumentGenerateOperation;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('checkout')]
class ZugferdBuilder
{
    protected ZugferdDocument $document;

    /**
     * @internal
     */
    public function __construct(
        protected EntityRepository $documentRepository,
        protected EntityRepository $countryRepository,
        protected EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function buildDocument(OrderEntity $order, DocumentGenerateOperation $operation, Context $context): ZugferdDocument
    {
        $this->init($order, $operation);

        // WIP: will be done in next MR

        $validation = (new ZugferdDocumentValidator($this->document->zugferdBuilder))->validateDocument();
        if ($validation->count()) {
            // WIP: will be done in next MR
        }

        return $this->document;
    }

    protected function init(OrderEntity $order, DocumentGenerateOperation $operation): self
    {
        $isGross = match ($order->getTaxStatus()) {
            CartPrice::TAX_STATE_GROSS => true,
            CartPrice::TAX_STATE_NET => false,
            default => throw DocumentException::generationError('Unsupported tax status'),
        };

        $this->document = new ZugferdDocument($order, $operation, ZugferdDocumentBuilder::createNew(ZugferdProfiles::PROFILE_XRECHNUNG_3), $isGross);

        return $this;
    }
}
