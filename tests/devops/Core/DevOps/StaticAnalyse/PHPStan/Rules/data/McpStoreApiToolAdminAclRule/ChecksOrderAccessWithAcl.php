<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule;

use Shopware\Core\Framework\Context;

trait ChecksOrderAccessWithAcl
{
    public function check(Context $context): ?string
    {
        return $this->requirePrivilege($context, 'order:read');
    }
}
