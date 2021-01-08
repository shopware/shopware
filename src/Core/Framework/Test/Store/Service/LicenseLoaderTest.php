<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Store\Service;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Store\Services\LicenseLoader;
use Shopware\Core\Framework\Store\Struct\PermissionCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class LicenseLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var LicenseLoader
     */
    private $licenseLoader;

    public function setUp(): void
    {
        Feature::skipTestIfInActive('FEATURE_NEXT_12608', $this);
        $this->licenseLoader = $this->getContainer()->get(LicenseLoader::class);
    }

    public function testItLoadsLicensesFromResponseLikeArray(): void
    {
        $licensedResponse = $this->getLicensedResponseFixture()[0];

        $license = $this->licenseLoader->loadFromArray($licensedResponse);

        static::assertEquals($licensedResponse['id'], $license->getId());
        static::assertEquals((new \DateTimeImmutable($licensedResponse['creationDate'])), $license->getCreationDate());
        static::assertEquals($licensedResponse['netPrice'], $license->getNetPrice());
        static::assertEquals($licensedResponse['variant'], $license->getVariant());
        static::assertInstanceOf(\DateTimeImmutable::class, $license->getNextBookingDate());

        $licensedExtension = $license->getLicensedExtension();
        $firstPermission = $licensedExtension->getPermissions()->first();

        static::assertInstanceOf(PermissionCollection::class, $licensedExtension->getPermissions());
        static::assertEquals('product', $firstPermission->getEntity());
        static::assertEquals($licensedResponse['extension']['id'], $licensedExtension->getId());
        static::assertEquals($licensedResponse['extension']['icon'], $licensedExtension->getIcon());
        static::assertEquals($licensedResponse['extension']['name'], $licensedExtension->getName());
        static::assertEquals($licensedResponse['extension']['label'], $licensedExtension->getLabel());
        static::assertEquals($licensedResponse['extension']['shortDescription'], $licensedExtension->getShortDescription());
    }

    private function getLicensedResponseFixture(): array
    {
        $content = \file_get_contents(__DIR__ . '/../_fixtures/responses/licenses.json');

        return \json_decode($content, true);
    }
}
