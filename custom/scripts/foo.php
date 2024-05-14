<?php

namespace Scripts\Examples;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;

require_once __DIR__ . '/examples/base-script.php';

$env = 'dev'; // by default, kernel gets booted in dev

$kernel = require __DIR__ . '/boot/boot.php';

class Main extends BaseScript
{
    public function run()
    {
        $this->getContainer()->get(Connection::class)->executeStatement(
            'DELETE FROM my_entity WHERE 1 = 1'
        );

        $definition = $this->getContainer()->get('my_entity.definition');

        /** @var EntityRepository $repo */
        $repo = $this->getContainer()->get('my_entity.repository');

        $data = [
            [
                'id' => Uuid::randomHex(),
                'name' => 'foo',
                'number' => 'foo',
                'productId' => '018f7142ea7872f4a675f0eb497f190b',
                'followId' => '018f7142ea7872f4a675f0eb497f190b',
                'categories' => [
                    ['id' => '018f71422f4472bfadf392a3eba2e34f']
                ],
                'subs' => [
                    [
                        'number' => 'foo'
                    ]
                ]
            ]
        ];

        $repo->upsert($data, Context::createCLIContext());

        $criteria = new Criteria();
        $criteria->addAssociation('categories');
        $criteria->addAssociation('follow');
        $criteria->addAssociation('product');
        $criteria->addAssociation('subs');
        $entities = $repo->search($criteria, Context::createCLIContext());

        file_put_contents(__DIR__ . '/my_entity.json', json_encode($entities, JSON_PRETTY_PRINT));
    }
}


(new Main($kernel))->run();
