<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseBase;

use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    use IntegrationTestBehaviour;
}
