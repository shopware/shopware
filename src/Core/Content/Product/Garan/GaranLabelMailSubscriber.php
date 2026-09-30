<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeValidateEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Passes the GARAN labels of an order's products to mail templates as `garanLabels`,
 * and attaches the label PNGs the rendered mail references as inline parts.
 * Symfony Mime links each `cid:<name>` reference to the part with that name when the mail is sent.
 *
 * @internal
 */
#[Package('inventory')]
class GaranLabelMailSubscriber implements EventSubscriberInterface
{
    private const TEMPLATE_DATA_KEY = 'garanLabels';

    /**
     * @param EntityRepository<ProductCollection> $productRepository
     */
    public function __construct(
        private readonly GaranLabelInlineImage $inlineImage,
        private readonly EntityRepository $productRepository,
        private readonly GaranLabelResolver $resolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeValidateEvent::class => 'addLabels',
            MailBeforeSentEvent::class => 'embedLabelImages',
        ];
    }

    public function addLabels(MailBeforeValidateEvent $event): void
    {
        $data = $event->getData();
        $template = ($data['contentHtml'] ?? '') . ($data['contentPlain'] ?? '');

        if (!str_contains($template, self::TEMPLATE_DATA_KEY)) {
            return;
        }

        $order = $event->getTemplateData()['order'] ?? null;

        if (!$order instanceof OrderEntity) {
            return;
        }

        $productIds = array_values(array_unique(
            $order->getLineItems()?->fmap(static fn (OrderLineItemEntity $lineItem): ?string => $lineItem->getProductId()) ?? []
        ));

        // empty criteria would load every product
        if ($productIds === []) {
            return;
        }

        $criteria = new Criteria($productIds);
        $criteria->addAssociation('manufacturer');

        // variants inherit the guarantee and manufacturer, admin-triggered mails do not consider inheritance by default
        $products = $event->getContext()->enableInheritance(
            fn (Context $context): ProductCollection => $this->productRepository->search($criteria, $context)->getEntities()
        );

        $labels = [];

        foreach ($products as $product) {
            $duration = $this->resolver->resolveDuration($product);

            if ($duration === null) {
                continue;
            }

            // `cid` is null for durations without an image, so the template falls back to the duration text
            $name = $this->inlineImage->getName((int) $product->getGuaranteeMonths());

            $labels[$product->getId()] = [
                'cid' => $name !== null ? 'cid:' . $name : null,
                'duration' => $duration,
            ];
        }

        $event->addTemplateData(self::TEMPLATE_DATA_KEY, $labels);
    }

    public function embedLabelImages(MailBeforeSentEvent $event): void
    {
        $message = $event->getMessage();
        $html = $message->getHtmlBody();

        if (!\is_string($html)) {
            return;
        }

        $attachedNames = array_map(
            static fn (DataPart $part): ?string => $part->getName(),
            $message->getAttachments(),
        );

        foreach ($this->inlineImage->findReferencedNames($html) as $name) {
            if (\in_array($name, $attachedNames, true)) {
                continue;
            }

            $png = $this->inlineImage->render($name);

            if ($png === null) {
                continue;
            }

            $message->embed($png, $name, 'image/png');
        }
    }
}
