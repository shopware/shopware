<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Attaches the nested label PNGs that a mail references through `sw_garan_label_mail` as inline parts.
 * Symfony Mime links each `cid:<name>` reference to the part with that name when the mail is sent.
 *
 * @internal
 */
#[Package('inventory')]
class GaranLabelMailSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly GaranLabelInlineImage $inlineImage)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MailBeforeSentEvent::class => 'embedLabelImages',
        ];
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
