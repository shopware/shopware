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
        'src/Command/SystemGenerateAppSecretCommand.php' => [
            'd41d8cd98f00b204e9800998ecf8427e',
            '376eca7d076e95a7e0a424c45574e0bc',
            '22a3af8b85cc3320aa6fd2343e1a4c69',
        ],
        'src/Command/SystemGenerateJwtSecretCommand.php' => [
            'd41d8cd98f00b204e9800998ecf8427e',
            'ffc16a3bdee7ac6e9fe2434e6bce1169',
            'a302abcb37b47be0308e4f5470028f2a',
            '8410f0425b56f02ed5720ac7b0e5cc25',
            '9763b707ffb2a3e039d3a44d7ddd3064',
            'bcf9ad3b72ad417c738cc1a4f38dd07a',
        ],
        'src/Command/SystemInstallCommand.php' => [
            'd41d8cd98f00b204e9800998ecf8427e',
            '1538281e5c318177554cdf8cac63ad05',
            'f4416c95a1d8e7277dc4334e43d6767e',
            'c2627a30bfdca441790d0c7248ed70f4',
            'e416bee87ffa5cedefbbed61f4b71536',
            '9e39501a7f9579dcabfd811b034af61e',
            '313a06f8f9f053bcae01fcd85bb91f67',
            'c888a6643bff1150a6da9546a788b5be',
            '0a2fa98179d58a4d7c471da28dfab24f',
            '750435d74bd658ba588c260e39d09514',
            '29051501cff7160c9aa540e1a8163b46',
            '3199ed465d2696d9c1b21eeb0d188c1b',
            '5906f83b9cfe296b8fc8e19c3c844524',
            '3430b59619404511d4599664168226b0',
            'b69d57cab9604c477d3cc960986e67bd',
            '7debe5e002d110febe2c03e907371515',
            '8979e773db06fa65a8d1f3443d1d3c03',
            '09a332ffa61201928cf42e5ad7b1f30d',
            '5e3fc62228a20ef21af85bafd28e753d',
            '1e2ffdd45f8b4a66efd4afb42f15069c',
        ],
        'src/Command/SystemSetupCommand.php' => [
            'd41d8cd98f00b204e9800998ecf8427e',
            '73848c4c568e81aec33dab3f71eaefec',
            '5b4b97c9ed5b7e140915575ca9f3a836',
            'b9d7de5f58af609b0702c53727dcb6ef',
            '3c247bf022f4632c4ee7a1a38563792c',
            '251976f9d3b58d48d8544ad50a8734db',
            'c31461507c6de8ecf21a6f7ad8639b1b',
            '3f692418e8f2faa9763e3f8bb73e8b9f',
            'c4008b5c1feee88fb1b9dbe251a5618d',
            '1369f240496725ae990ebbe5b5f19f15',
            'efeb996475b661cf47effaffa6c704ca',
            'f78457bcfce408844f41f20366310f2e',
            '25192a0c79ca459c2be74de42ec504dc',
            '01b7f02ce18a95b8d292fab056f1aecc',
            'fe7dacbe83cd138329843508878beed1',
            '2ebeea9ee837a14f9bfb3436cdbd700b',
            'd16e305c406dc14807799d655f0639b0',
            'd868e38fbb9bb3fc2919faa0293b39bc',
            'e47574bcaded209a66e6e48cc4386e4c',
            'ff006ae4f5ed15cf1eb7a22bbc814b21',
            'abd7e7dfa722c34454cf0a44d0cfa0b1',
            '9e7b9a04c5c1f96ba697e1eae7875d16',
            'bb261902bd7712078f61408392270e73',
            '43c65b1348767c7c28b5e6a688b02283',
            'e2db5997da6d44c0bfc66f9ff9fb56a0',
        ],
        'src/Command/SystemUpdateFinishCommand.php' => [
            'd41d8cd98f00b204e9800998ecf8427e',
            '65a05e73589bbcb92d3ef88a91feb82e',
            'e1dea4fd0f29552c7d626da455ca6f51',
            '69f6637f1a3e25e032d65eeb48f46abe',
            '1a88e07221c6179965d2db32162a7cfc',
            'cfab58e3844ea59a089095d22a376026',
            'a93e871840b3e4b52e551df7d6ccc7d9',
            'b8ebe17b9c290a8f73e1b3cdd1ede54e',
            '90cd5cb579b53792038339ce27436d44',
            '88fcc3ed4362445b10036475b9e2267a',
            '32f0b38682914b842fda86df27fab3ac',
            '2cb4b89fae93d39f0aadd354467a025b',
            '17c83f142c7481832fabac4f235785a5',
            'ca2848e1da72c0c14c2cffc144ecc86b',
        ],
        'src/Command/SystemUpdatePrepareCommand.php' => [
            'd41d8cd98f00b204e9800998ecf8427e',
            '022277e3cbd26c296f07d965c0f2b047',
            '047289ec8de582ddab6f90fa8a0eb3ce',
            '7443fe004a9769d8e9f7f490b8e50130',
            'd91f376c53e1bad4dc30de3f82eb79a4',
            'd6c7b7e359949a06b47c1b1e2ab1e451',
            'c199ff02972c0d0980dc4cdc3d869c65',
            '1a5e3227c5a40ade76ecda70334538e3',
            'e4b38de22a22a9776354fb3d045ed224',
            '623717845716651d657d2d1e9c38e0c1',
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
