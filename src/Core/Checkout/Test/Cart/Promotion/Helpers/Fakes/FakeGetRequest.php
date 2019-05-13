<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class FakeGetRequest extends Request
{
    public function __construct()
    {
        parent::__construct();

        $this->request = new ParameterBag([]);
    }
}
