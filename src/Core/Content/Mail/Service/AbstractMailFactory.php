<?php declare(strict_types=1);

namespace Shopware\Core\Content\Mail\Service;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Mime\Email;

#[Package('system-settings')]
abstract class AbstractMailFactory
{
    /**
     * @param array $sender         e.g. ['shopware@example.com' => 'Shopware AG']
     * @param array $recipients     e.g. ['shopware@example.com' => 'Shopware AG', 'symfony@example.com' => 'Symfony']
     * @param array $contents       e.g. ['text/plain' => 'Foo', 'text/html' => '&lt;h1&gt;Bar&lt;/h1&gt;']
     * @param array $additionalData e.g. [
     *                              'recipientsCc' => 'shopware &lt;shopware@example.com&gt;,
     *                              'recipientsBcc' => 'shopware@example.com',
     *                              'replyTo' => 'reply@example.com',
     *                              'returnPath' => 'bounce@example.com'
     *                              ]
     */
    abstract public function create(
        string $subject,
        array $sender,
        array $recipients,
        array $contents,
        array $attachments,
        array $additionalData,
        ?array $binAttachments = null
    ): Email;

    abstract public function getDecorated(): AbstractMailFactory;
}
