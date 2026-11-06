<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\ContentSystem\Api;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Api\ContentPreviewPayloadStore;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * The store validates a rebuilt envelope with the validator it is handed, and the unit tests hand it one they
 * build themselves — so they establish that a validator runs, never that the wired one enforces anything. The
 * read path depends on the container's validator finding constraints the DTO declares as attributes, which
 * holds only while `framework.validation.enable_attributes` is true. Flip that flag or wire a different
 * validator and the read silently stops enforcing constraints, with every other test still green.
 *
 * @internal
 */
#[Package('framework')]
class ContentPreviewPayloadStoreTest extends TestCase
{
    use IntegrationTestBehaviour;

    #[TestDox('the container-wired validator enforces the constraints the DTO declares')]
    public function testLoadRejectsAConstraintViolationThroughTheContainerValidator(): void
    {
        $token = Uuid::randomHex();
        $key = 'content-system.preview.' . $token;
        $cache = static::getContainer()->get('cache.system');

        // A structurally complete envelope — every field the DTO declares, each of the right PHP type — so the
        // field and type gates pass and the blank entityType reaches the constraint check, the only gate the
        // validator owns.
        $item = $cache->getItem($key);
        $item->set([
            'layout' => [['id' => Uuid::randomHex(), 'component' => 'Sw:Content:Text']],
            'entityType' => '',
            'entityId' => Uuid::randomHex(),
            'salesChannelId' => Uuid::randomHex(),
            'languageId' => null,
            'currencyId' => null,
            'domainId' => null,
            'customerId' => null,
            'queryParameters' => [],
        ]);
        $cache->save($item);

        try {
            $this->expectExceptionObject(ContentSystemException::previewPayloadInvalid(
                'entityType',
                'accepted by the constraints ContentPreviewRequest declares',
                'This value should not be blank.',
            ));

            static::getContainer()->get(ContentPreviewPayloadStore::class)->load($token);
        } finally {
            $cache->deleteItem($key);
        }
    }
}
