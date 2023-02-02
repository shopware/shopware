<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DataAbstractionLayer\Field\Flag;

use Shopware\Core\Framework\Feature;

/*
 * In case a column is allowed to contain HTML-esque data. Beware of injection possibilities
 */
if (Feature::isActive('FEATURE_NEXT_15172')) {
    class AllowHtml extends Flag
    {
        /**
         * @var bool
         */
        protected $sanitized;

        public function __construct(bool $sanitized = true)
        {
            $this->sanitized = $sanitized;
        }

        public function parse(): \Generator
        {
            yield 'allow_html' => true;
        }

        public function isSanitized(): bool
        {
            return $this->sanitized;
        }
    }
} else {
    class AllowHtml extends Flag
    {
        /**
         * @var bool
         */
        protected $sanitized;

        public function __construct(bool $sanitized = false)
        {
            $this->sanitized = $sanitized;
        }

        public function parse(): \Generator
        {
            yield 'allow_html' => true;
        }

        public function isSanitized(): bool
        {
            return $this->sanitized;
        }
    }
}
