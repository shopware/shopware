<?php declare(strict_types=1);

namespace Shopware\WebInstaller\Services;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Path;

/**
 * This class is responsible to clean up config files after the installation
 * Files can end up unmanaged, when they are removed again from the recipe.
 * This happens only with the WebInstaller as we don't use the Flex Git feature.
 * Therefore, we need to remove files that are not part of the recipe anymore.
 */
#[Package('core')]
class CleanupFiles
{
    /**
     * Hashes can be found using the following command:
     *
     * git log --pretty=format:'%H' -- src/TestBootstrap.php | while read commit; do
     *      echo "Commit: $commit"
     *      git show "$commit:src/TestBootstrap.php" | md5sum
     *      echo ""
     * done
     *
     * @var array<string, array<string>>
     */
    private array $files = [
        'config/packages/shopware.yaml' => [
            '398b89fe27bed7a69fbf0b5d92a0b421', // shopware.yaml template everything commented out
        ],
        'config/packages/dev/monolog.yaml' => [
            '65f91981a4e8b944023d3b5e08627805',
        ],
        'config/packages/prod/deprecations.yaml' => [
            'f6a40c71ab5273d78e587de9b91d4e23',
        ],
        'config/packages/prod/monolog.yaml' => [
            '66d74b6b0ae4ab1e855a110691780ade',
        ],
        'config/packages/test/monolog.yaml' => [
            'c542ba3d8911744b3d90a048a3d9787f',
        ],
        'config/packages/messenger.yaml' => [
            '3b816f780610e02b546b03497f1c70fc',
            '6f1f95c01270c56da40b308efcdfa373',
        ],
        'config/packages/debug.yaml' => [
            '020c09cb58a88c9f2a7e78603277e910',
            '33170515ddf44d89747e8246435e41e4',
        ],
        'config/packages/mailer.yaml' => [
            '38d79b1c540fc4e37731f9894267be4e',
        ],
        'config/packages/validator.yaml' => [
            '568f6678405192012f071cc77d22d352',
        ],
        'config/packages/elasticsearch.yaml' => [
            '010db5eacf726060c6b8ada556ac1296',
        ],
        // From old production template
        'src/TestBootstrap.php' => [
            '1b384f143cd085e2204182e5b92f3ae4',
            '721b8c1e09dbe61422cba026a4411d53',
            '75b5e32ed2a8e5c3bbc734f6745672cd',
            '0566444d640d098707cdb5177fa342ca',
            '1111b077fe5e442d6c0ec5fcab258290',
            '9fcebb01fc372dd037c3427d37a7b7e7',
            '1ba9700ce9646130944489e3ebba80ce',
            '238af209b52a89422f30db28a2614169',
        ],
        'src/HttpKernel.php' => [
            'bb1a34e4e36a2b9d893c285b9485fa3c',
            '803e433c6d274031711ddbaa0fe24ad1',
        ],
        'src/Kernel.php' => [
            'be21d9a13094c8149cc6792ae6932081',
            'f92ccac61a85c3fc4663714d444ef2fc',
            'c70293c1bf50a9ec68c9d482c427a845',
            'a819a7b3da67fda3f3248131afe5054c',
            '3a3b448dc250b6b45f9991259e8f9d93',
            'eb0c7ea729b691d09a41b1d880708815',
            '57b9a84e8c6079087358c39789322a2e',
            '2b997d3943a1edfc1932105a079bda2d',
            '29b908479dd633e57d06eeaa8f64c014',
        ],
    ];

    public function cleanup(string $projectRoot): void
    {
        $fs = new Filesystem();

        foreach ($this->files as $file => $hashes) {
            $path = Path::join($projectRoot, $file);
            if (!$fs->exists($path)) {
                continue;
            }

            $hash = md5_file($path);

            if (!\in_array($hash, $hashes, true)) {
                continue;
            }

            $fs->remove($path);
        }
    }
}
