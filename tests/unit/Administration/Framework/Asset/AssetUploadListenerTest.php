<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\Asset;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Asset\AssetUploadListener;
use Shopware\Core\Framework\Plugin\Event\AssetUploadEvent;

/**
 * @internal
 */
#[CoversClass(AssetUploadListener::class)]
class AssetUploadListenerTest extends TestCase
{
    public function testInvoke(): void
    {
        $listener = new AssetUploadListener();
        $event = new AssetUploadEvent(
            [
                'administration/.vite/manifest.json',
                'administration/.vite/entrypoints.json',
                'test.js',
            ],
            []
        );

        $listener($event);

        static::assertSame([
            'test.js',
            'administration/.vite/entrypoints.json',
            'administration/.vite/manifest.json',
        ], $event->filesToUpload);
    }
}
