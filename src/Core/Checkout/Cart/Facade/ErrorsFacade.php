<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Facade;

use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use Shopware\Core\Framework\Log\Package;

/**
 * The ErrorsFacade is a wrapper around the errors of a cart.
 * You can use it to add new errors to the cart or remove existing ones.
 *
 * @script-service cart_manipulation
 *
 * @implements \IteratorAggregate<array-key, Error>
 */
#[Package('checkout')]
class ErrorsFacade implements \IteratorAggregate
{
    public function __construct(private ErrorCollection $collection)
    {
    }

    /**
     * The `error()` method adds a new error of type `error` to the cart.
     * The error will be displayed to the user and the checkout will be blocked if at least one error was added.
     *
     * @param string $key The snippet-key of the message that should be displayed to the user.
     * @param string|null $id An optional id that can be used to reference the error, if none is provided the $key will be used as id.
     * @param array<string, mixed> $parameters Optional: Any parameters that the snippet for the error message may need.
     *
     * @example add-errors/add-errors.twig 2 1 Add a error to the cart.
     */
    public function error(string $key, ?string $id = null, array $parameters = []): void
    {
        $this->createError($key, true, true, $parameters, Error::LEVEL_ERROR, $id);
    }

    /**
     * The `warning()` method adds a new error of type `warning` to the cart.
     * The warning will be displayed to the user, but the checkout won't be blocked.
     *
     * @param string $key The snippet-key of the message that should be displayed to the user.
     * @param string|null $id An optional id that can be used to reference the error, if none is provided the $key will be used as id.
     * @param array<string, mixed> $parameters Optional: Any parameters that the snippet for the error message may need.
     *
     * @example add-errors/add-errors.twig 3 1 Add a warning to the cart.
     */
    public function warning(string $key, ?string $id = null, array $parameters = []): void
    {
        $this->createError($key, false, true, $parameters, Error::LEVEL_WARNING, $id);
    }

    /**
     * The `notice()` method adds a new error of type `notice` to the cart.
     * The notice will be displayed to the user, but the checkout won't be blocked.
     *
     * @param string $key The snippet-key of the message that should be displayed to the user.
     * @param string|null $id An optional id that can be used to reference the error, if none is provided the $key will be used as id.
     * @param array<string, mixed> $parameters Optional: Any parameters that the snippet for the error message may need.
     *
     * @example add-errors/add-errors.twig 4 1 Add a notice to the cart.
     * @example add-errors/add-errors.twig 5 1 Add a notice to the cart with a custom id.
     * @example add-errors/add-errors.twig 6 1 Add a notice to the cart with parameters.
     */
    public function notice(string $key, ?string $id = null, array $parameters = []): void
    {
        $this->createError($key, false, true, $parameters, Error::LEVEL_NOTICE, $id);
    }

    /**
     * The `resubmittable()` method adds a new error of type `error` to the cart.
     * The notice will be displayed to the user, the order will be blocked, but the user can submit the order again.
     *
     * @param string $key The snippet-key of the message that should be displayed to the user.
     * @param string|null $id An optional id that can be used to reference the error, if none is provided the $key will be used as id.
     * @param array<mixed> $parameters Optional: Any parameters that the snippet for the error message may need.
     */
    public function resubmittable(string $key, ?string $id = null, array $parameters = []): void
    {
        $this->createError($key, false, false, $parameters, Error::LEVEL_NOTICE, $id);
    }

    /**
     * The `has()` method, checks if an error with a given id exists.
     *
     * @param string $id The id of the error that should be checked.
     *
     * @return bool Returns true if an error with that key exists, false otherwise.
     */
    public function has(string $id): bool
    {
        return $this->collection->has($id);
    }

    /**
     * The `remove()` method removes the error with the given id.
     *
     * @param string $id The id of the error that should be removed.
     */
    public function remove(string $id): void
    {
        $this->collection->remove($id);
    }

    /**
     * The `get()` method returns the error with the given id.
     *
     * @param string $id The id of the error that should be returned.
     *
     * @return Error|null The Error with the given id, null if an error with that id does not exist.
     */
    public function get(string $id): ?Error
    {
        return $this->collection->get($id);
    }

    /**
     * @internal should not be used directly, loop over an ErrorsFacade directly inside twig instead
     */
    public function getIterator(): \Traversable
    {
        yield from $this->collection;
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function createError(string $key, bool $blockOrder, bool $blockResubmit, array $parameters, int $level, ?string $id = null): void
    {
        $this->collection->add(
            new GenericCartError($id ?? $key, $key, $parameters, $level, $blockOrder, true, $blockResubmit)
        );
    }
}
