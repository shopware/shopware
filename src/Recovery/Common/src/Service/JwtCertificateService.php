<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\Service;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;

/**
 * @deprecated tag:v6.5.0 - will be removed use the JwtCertificateGenerator directly
 */
class JwtCertificateService
{
    private string $folder;

    private JwtCertificateGenerator $jwtCertificateGenerator;

    public function __construct(string $folder, JwtCertificateGenerator $jwtCertificateGenerator)
    {
        $this->folder = $folder;
        $this->jwtCertificateGenerator = $jwtCertificateGenerator;
    }

    public function generate(): void
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0', JwtCertificateGenerator::class)
        );

        $this->jwtCertificateGenerator->generate(
            $this->folder . '/private.pem',
            $this->folder . '/public.pem'
        );
    }
}
