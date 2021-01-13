<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Migration\Exception;

use Shopware\Core\Framework\ShopwareException;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;

// ShopwareHttpException depends on Symfony. Symfony is not available in the updater context.
if (class_exists(HttpException::class)) {
    class UnknownMigrationSourceExceptionBase extends ShopwareHttpException
    {
        /**
         * @var string
         */
        private $name;

        public function __construct(string $name)
        {
            $this->name = $name;

            parent::__construct(
                'No source registered for "{{ name }}"',
                ['name' => $name]
            );
        }

        public function getErrorCode(): string
        {
            return 'FRAMEWORK__INVALID_MIGRATION_SOURCE';
        }

        public function getParameters(): array
        {
            return [
                'name' => $this->name,
            ];
        }
    }
} else {
    class UnknownMigrationSourceExceptionBase extends \RuntimeException implements ShopwareException
    {
        /**
         * @var string
         */
        private $name;

        public function __construct(string $name)
        {
            parent::__construct('No source registered for "' . $name . '"');
            $this->name = $name;
        }

        public function getErrorCode(): string
        {
            return 'FRAMEWORK__INVALID_MIGRATION_SOURCE';
        }

        public function getParameters(): array
        {
            return [
                'name' => $this->name,
            ];
        }
    }
}

class UnknownMigrationSourceException extends UnknownMigrationSourceExceptionBase
{
}
