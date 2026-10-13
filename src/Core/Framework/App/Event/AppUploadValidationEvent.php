<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal
 */
#[Package('framework')]
class AppUploadValidationEvent extends Event
{
    public function __construct(
        public readonly UploadedFile $archive,
        public readonly Manifest $manifest,
        public readonly Context $context,
    ) {
    }
}
