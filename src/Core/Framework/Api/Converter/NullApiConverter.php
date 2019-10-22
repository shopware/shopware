<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Converter;

class NullApiConverter extends ApiConverter
{
    public function getApiVersion(): int
    {
        return 0;
    }

    protected function getDeprecations(): array
    {
        return [];
    }

    protected function getNewFields(): array
    {
        return [];
    }

    protected function getConverterFunctions(): array
    {
        return [];
    }
}
