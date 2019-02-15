<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Write\ValueGenerator;

interface ValueGeneratorConnectorInterface
{
    /**
     * fetch last used increment and reserves next
     *
     * @return mixed
     */
    public function pullState();

    public function setGenerator(ValueGeneratorInterface $valueGenerator);

    public function getConnectorId(): string;
}
