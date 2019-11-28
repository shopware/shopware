<?php declare(strict_types=1);

namespace Shopware\Recovery\Common\Service;

class JwtCertificateService
{
    /**
     * @var string
     */
    private $folder;

    public function __construct(string $folder)
    {
        $this->folder = $folder;
    }

    public function generate(): void
    {
        $key = openssl_pkey_new([
            'digest_alg' => 'aes256',
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'encrypt_key' => false,
            'encrypt_key_cipher' => OPENSSL_CIPHER_AES_256_CBC,
        ]);

        // export private key
        openssl_pkey_export_to_file($key, $this->folder . '/private.pem');

        // export public key
        $keyData = openssl_pkey_get_details($key);
        file_put_contents($this->folder . '/public.pem', $keyData['key']);

        chmod($this->folder . '/private.pem', 0660);
        chmod($this->folder . '/public.pem', 0660);
    }
}
