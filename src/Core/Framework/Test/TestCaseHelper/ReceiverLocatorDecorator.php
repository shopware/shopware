<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\TestCaseHelper;

use Symfony\Component\DependencyInjection\ServiceLocator;

class ReceiverLocatorDecorator extends ServiceLocator
{
    /**
     * @var ServiceLocator
     */
    private $inner;

    public function __construct(ServiceLocator $inner)
    {
        $this->inner = $inner;
        parent::__construct([]);
    }

    public function get($id)
    {
        $service = $this->inner->get($id);

        return new MakeReceiverRestartableDecorator($service);
    }

    public function has($id)
    {
        return $this->inner->has($id);
    }
}
