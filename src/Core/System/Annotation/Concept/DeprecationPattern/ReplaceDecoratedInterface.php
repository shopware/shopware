<?php declare(strict_types=1);

namespace Shopware\Core\System\Annotation\Concept\DeprecationPattern;

use Doctrine\Common\Annotations\Annotation;
use Shopware\Core\Framework\Log\Package;

/**
 * @Annotation
 *
 * @Target("CLASS", "INTERFACE")
 *
 * @DeprecationPattern
 *
 * The ReplaceDecoratedInterface pattern is used every time you have to change an interface, that is designed to be used with service decoration.
 * As changing the interface in question directly would unavoidably lead to breaks in all decorators of that service interface.
 * To be able to do the change anyway you introduce a new Interface with the new public API and deprecate the old Interface to be removed in the next eligible minor version.
 * You update our service to also implement the new Interface in addition to the deprecated one.
 * If necessary and possible you refactor the service so that it shares most of the logic for both interface implementations
 * and make it as easy as possible to later remove the implemented methods of the deprecated Interface.
 *
 * In the callers of the service you have to remove the type declaration in the constructor and document that the Service can either be of the new or the deprecated interface.
 * When the service is called you have to do an instanceof check on the service to determine if the injected service implements the new or just the deprecated interface.
 * If it implements the new Interface you call the interface in the new way, if it doesn't implement the new Interface it is probably a decoration from a plugin that has not yet adapted to the Interface change, you therefore call then the deprecated Interface.
 * An example may look like this:
 * ```php
 * class MyController
 * {
 *
 * // @var DeprecatedServiceInterface|NewServiceInterface
 * private $decoratedService;
 *
 * // @param DeprecatedServiceInterface|NewServiceInterface $decoratedService
 * public function __construct($decoratedService)
 * {
 *      $this->decoratedService = $decoratedService;
 * }
 *
 * public function myAction(): Response
 * {
 *      ...
 *      if ($this->decoratedService instanceof NewServiceInterface) {
 *          $result = $this->decoratedService->newMethod($oldParam, $newParam);
 *      } else {
 *          $result = $this->decoratedService->deprecatedMethod($oldParam);
 *      }
 *      ...
 * }
 * ```
 *
 * This instanceof check has also to be done in all decorators that adapt to the new Interface and adapted Decorators still have to implement the old Interface, because of the following multi-decoration use cases.
 * 1. The decorator before your decorator has not adapted yet
 *      The decorator before you will always call your decorator with the deprecated Interface, therefore you still have to implement it
 * 2. The decorator after your decorator has not adapted yet
 *      If you call the inner service decorator with the new interface, which it does not implement yet it will lead to errors, therefore you have to also to the instanceof check in your decorators.
 */
#[Package('core')]
class ReplaceDecoratedInterface
{
    public function __construct(array $info)
    {
        if (!\array_key_exists('deprecatedInterface', $info) || !\array_key_exists('replacedBy', $info)) {
            throw new \Exception('ReplaceDecoratedInterface annotation must be created with a hint on the "deprecatedInterface" and the interface it is "replacedBy".');
        }
    }
}
