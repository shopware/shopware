<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

trait IntegrationTestBehaviour
{
    use KernelTestBehaviour,
        DatabaseTransactionBehaviour,
        FilesystemBehaviour,
        CacheTestBehaviour;
}
