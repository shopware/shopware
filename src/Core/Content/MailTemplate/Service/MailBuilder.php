<?php declare(strict_types=1);

namespace Shopware\Core\Content\MailTemplate\Service;

use Shopware\Core\Content\MailTemplate\Exception\SalesChannelNotFoundException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

class MailBuilder
{
    /**
     * @var EntityRepositoryInterface
     */
    private $salesChannelRepo;

    public function __construct(EntityRepositoryInterface $salesChannelRepo)
    {
        $this->salesChannelRepo = $salesChannelRepo;
    }

    /**
     * Attaches header and footer to given email bodies
     *
     * @param array $bodies e.g. ['text/plain' => 'foobar', 'text/html' => '<h1>foobar</h1>']
     *
     * @return array e.g. ['text/plain' => '{{foobar}}', 'text/html' => '<h1>{{foobar}}</h1>']
     */
    public function buildContents(Context $context, array $bodies, string $salesChannelId): array
    {
        $mailHeaderFooter = $this->findSalesChannel($context, $salesChannelId)->getMailHeaderFooter();
        if ($mailHeaderFooter !== null) {
            return [
                'text/plain' => $mailHeaderFooter->getHeaderPlain() . $bodies['text/plain'] . $mailHeaderFooter->getFooterPlain(),
                'text/html' => $mailHeaderFooter->getHeaderHtml() . $bodies['text/html'] . $mailHeaderFooter->getFooterHtml(),
            ];
        }

        return $bodies;
    }

    private function findSalesChannel(Context $context, string $id): SalesChannelEntity
    {
        $criteria = new Criteria([$id]);
        $entity = $this->salesChannelRepo->search($criteria, $context)->getEntities()->first();
        if ($entity === null) {
            throw new SalesChannelNotFoundException();
        }

        return $entity;
    }
}
