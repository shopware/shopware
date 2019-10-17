<?php declare(strict_types=1);

namespace Shopware\Core\Content\ProductExport\Template;

interface TemplateInterface extends \JsonSerializable
{
    public function getName(): string;

    public function getTranslationKey(): string;

    public function getHeaderTemplate(): string;

    public function getBodyTemplate(): string;

    public function getFooterTemplate(): string;

    public function getFileName(): string;

    public function getEncoding(): string;

    public function getFileFormat(): string;

    public function getGenerateByCronjob(): bool;

    public function getInterval(): int;
}
