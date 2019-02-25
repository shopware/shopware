<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\Generator;

interface DocumentGeneratorInterface
{
    public function generateAsString(string $html): string;

    public function generateAsStream(string $html): void;
}
