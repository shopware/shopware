<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\Service;

use Shopware\Core\Maintenance\System\Service\JwtCertificateGenerator;

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
        $this->jwtCertificateGenerator->generate(
            $this->folder . '/private.pem',
            $this->folder . '/public.pem'
        );
    }
}
