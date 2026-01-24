<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\MyFakeNamespace;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class ViolatingTest extends TestCase
{
    use IntegrationTestBehaviour;
}
