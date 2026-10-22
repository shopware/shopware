<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule\Decoy {
    use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

    /**
     * Same short name as the base class below, declared first in the file.
     */
    abstract class SharedNameToolBase extends McpToolResponse
    {
        public function __invoke(): string
        {
            return $this->success([]);
        }
    }
}

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\McpStoreApiToolAdminAclRule {
    use Shopware\Core\Framework\Context;
    use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;

    abstract class SharedNameToolBase extends McpToolResponse
    {
        public function __invoke(): string
        {
            if ($error = $this->requirePrivilege(Context::createDefaultContext(), 'order:read')) {
                return $error;
            }

            return $this->success([]);
        }
    }
}
