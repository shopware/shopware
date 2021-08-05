<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Sync;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Struct\Struct;

if (!Feature::isActive('FEATURE_NEXT_15815')) {
    //@internal (flag:FEATURE_NEXT_15815) IMPORTANT: If condition negated, keep class in else case. (because static-analyze)
    class SyncResult extends Struct
    {
        /**
         * @feature-deprecated (flag:FEATURE_NEXT_15815) tag:v6.5.0 - will be removed
         */
        protected bool $success = false;

        protected array $data = [];

        protected array $deleted = [];

        protected array $notFound = [];

        public function __construct(array $data, bool $success, array $notFound = [], array $deleted = [])
        {
            $this->data = $data;
            $this->success = $success;
            $this->notFound = $notFound;
            $this->deleted = $deleted;
        }

        public function getData(): array
        {
            return $this->data;
        }

        /**
         * @feature-deprecated (flag:FEATURE_NEXT_15815) tag:v6.5.0 - will be removed
         */
        public function isSuccess(): bool
        {
            return $this->success;
        }

        /**
         * @feature-deprecated (flag:FEATURE_NEXT_15815) tag:v6.5.0 - will be removed
         */
        public function get(string $key): ?SyncOperationResult
        {
            return $this->data[$key] ?? null;
        }

        /**
         * @feature-deprecated (flag:FEATURE_NEXT_15815) tag:v6.5.0 - will be removed
         */
        public function add(string $key, SyncOperationResult $result): void
        {
            $this->data[$key] = $result;
        }

        /**
         * @feature-deprecated (flag:FEATURE_NEXT_15815) tag:v6.5.0 - will be removed
         */
        public function setSuccess(bool $success): void
        {
            $this->success = $success;
        }

        public function getApiAlias(): string
        {
            return 'api_sync_result';
        }

        public function getNotFound(): array
        {
            return $this->notFound;
        }

        public function getDeleted(): array
        {
            return $this->deleted;
        }
    }
} else {
    class SyncResult extends Struct
    {
        protected array $data = [];

        protected array $deleted = [];

        protected array $notFound = [];

        public function __construct(array $data, array $notFound = [], array $deleted = [])
        {
            $this->data = $data;
            $this->notFound = $notFound;
            $this->deleted = $deleted;
        }

        public function getData(): array
        {
            return $this->data;
        }

        public function getApiAlias(): string
        {
            return 'api_sync_result';
        }

        public function getNotFound(): array
        {
            return $this->notFound;
        }

        public function getDeleted(): array
        {
            return $this->deleted;
        }
    }
}
