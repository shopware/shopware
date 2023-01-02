<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Dispatching\Aware;

use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;

#[Package('business-ops')]
interface SubjectAware extends FlowEventAware
{
    public const SUBJECT = 'subject';

    public function getSubject(): string;
}
