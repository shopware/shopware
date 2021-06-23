<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Feature;

if (Feature::isActive('FEATURE_NEXT_15815')) {
    class SyncBehavior
    {
        protected ?string $indexingBehavior;

        public function __construct(?string $indexingBehavior = null)
        {
            $this->indexingBehavior = $indexingBehavior;
        }

        /**
         * @feature-deprecated (flag:FEATURE_NEXT_15815) tag:v6.5.0 - remove this function
         */
        public function useSingleOperation(): bool
        {
            return true;
        }

        public function getIndexingBehavior(): ?string
        {
            return $this->indexingBehavior;
        }
    }
} else {
    class SyncBehavior
    {
        protected bool $failOnError;

        protected bool $singleOperation;

        protected ?string $indexingBehavior;

        public function __construct(
            bool $failOnError,
            bool $singleOperation = false,
            ?string $indexingBehavior = null
        ) {
            $this->failOnError = $failOnError;
            $this->singleOperation = $singleOperation;
            $this->indexingBehavior = $indexingBehavior;
        }

        public function failOnError(): bool
        {
            return $this->failOnError;
        }

        public function useSingleOperation(): bool
        {
            return $this->singleOperation;
        }

        public function getIndexingBehavior(): ?string
        {
            return $this->indexingBehavior;
        }
    }
}
