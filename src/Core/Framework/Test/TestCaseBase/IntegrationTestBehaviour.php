<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

trait IntegrationTestBehaviour
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;
    use FilesystemBehaviour;
    use CacheTestBehaviour;
    use BasicTestDataBehaviour;
    use SessionTestBehaviour;
    use RequestStackTestBehaviour;
    use TranslationTestBehaviour;
}
