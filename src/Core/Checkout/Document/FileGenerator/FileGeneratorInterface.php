<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Document\FileGenerator;

interface FileGeneratorInterface
{
    public function supports(): string;

    public function generate(string $html): string;

    public function getExtension(): string;
}
