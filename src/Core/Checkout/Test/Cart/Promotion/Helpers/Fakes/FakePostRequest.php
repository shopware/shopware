<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Fakes;

use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

class FakePostRequest extends Request
{
    public function __construct(array $params)
    {
        parent::__construct();

        $this->request = new ParameterBag($params);
    }

    public function getMethod()
    {
        return 'POST';
    }

    public function getScheme()
    {
        return 'HTTPS';
    }
}
