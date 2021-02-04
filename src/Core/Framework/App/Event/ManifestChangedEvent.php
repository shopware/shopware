<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;

/**
 * @internal only for use by the app-system, will be considered internal from v6.4.0 onward
 */
abstract class ManifestChangedEvent extends AppChangedEvent
{
    /**
     * @var Manifest
     */
    private $manifest;

    public function __construct(AppEntity $app, Manifest $manifest, Context $context)
    {
        $this->manifest = $manifest;
        parent::__construct($app, $context);
    }

    abstract public function getName(): string;

    public function getManifest(): Manifest
    {
        return $this->manifest;
    }

    public function getWebhookPayload(): array
    {
        return [
            'appVersion' => $this->manifest->getMetadata()->getVersion(),
        ];
    }
}
