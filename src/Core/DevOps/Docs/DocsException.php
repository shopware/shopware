<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\Docs;

use PHPUnit\Framework\Attributes\CodeCoverageIgnore;
use Shopware\Core\DevOps\Docs\Script\ServiceReferenceGenerator;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\Execution\Hook;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class DocsException extends HttpException
{
    final public const NO_HOOK_CLASSES_FOUND = 'DEVOPS_DOCS_NO_HOOK_CLASSES_FOUND';
    final public const MISSING_RETURN_TYPE_ON_FACTORY_METHOD_IN_HOOK_SERVICE_FACTORY = 'DEVOPS_DOCS_MISSING_RETURN_TYPE_ON_FACTORY_METHOD_IN_HOOK_SERVICE_FACTORY';
    final public const UNTYPED_PROPERTY_IN_HOOK_CLASS = 'DEVOPS_DOCS_UNTYPED_PROPERTY_IN_HOOK_CLASS';
    final public const MISSING_PHP_DOC_COMMENT_IN_HOOK_CLASS = 'DEVOPS_DOCS_MISSING_PHP_DOC_COMMENT_IN_HOOK_CLASS';
    final public const MISSING_USE_CASE_DESCRIPTION_IN_HOOK_CLASS = 'DEVOPS_DOCS_MISSING_USE_CASE_DESCRIPTION_IN_HOOK_CLASS';
    final public const MISSING_SINCE_ANNOTATION_IN_HOOK_CLASS = 'DEVOPS_DOCS_MISSING_SINCE_ANNOTATION_IN_HOOK_CLASS';
    final public const SERVICE_SCRIPT_INCORRECT_GROUP = 'DEVOPS_DOCS_SERVICE_SCRIPT_INCORRECT_GROUP';
    final public const NO_SCRIPT_SERVICES_FOUND = 'DEVOPS_DOCS_NO_SCRIPT_SERVICES_FOUND';
    final public const MISSING_DOC_BLOCK_FOR_METHOD = 'DEVOPS_DOCS_MISSING_DOC_BLOCK_FOR_METHOD';
    final public const MISSING_DOC_BLOCK_FOR_METHOD_PARAM = 'DEVOPS_DOCS_MISSING_DOC_BLOCK_FOR_METHOD_PARAM';
    final public const MISSING_RETURN_ANNOTATION_FOR_METHOD = 'DEVOPS_DOCS_MISSING_RETURN_ANNOTATION_FOR_METHOD';
    final public const CONFIGURED_EXAMPLE_FILE_NOT_FOUND = 'DEVOPS_DOCS_CONFIGURED_EXAMPLE_FILE_NOT_FOUND';
    final public const CONFIGURED_EXAMPLE_FILE_NOT_UNIQUE = 'DEVOPS_DOCS_CONFIGURED_EXAMPLE_FILE_NOT_UNIQUE';

    public static function noHookClassesFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NO_HOOK_CLASSES_FOUND,
            'No Hook classes found'
        );
    }

    /**
     * @param class-string<Hook> $hook
     */
    public static function untypedPropertyInHookClass(string $property, string $hook): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::UNTYPED_PROPERTY_IN_HOOK_CLASS,
            'Property "{{ property }}" in Hook class "{{ hook }}" is not typed and has no @var annotation',
            [
                'property' => $property,
                'hook' => $hook,
            ],
        );
    }

    public static function missingReturnTypeOnFactoryMethodInHookServiceFactory(string $factory): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_RETURN_TYPE_ON_FACTORY_METHOD_IN_HOOK_SERVICE_FACTORY,
            '`factory()` method in HookServiceFactory "{{ factory }}" has no return type',
            [
                'factory' => $factory,
            ],
        );
    }

    /**
     * @param class-string<Hook> $hook
     */
    public static function missingPhpDocCommentInHookClass(string $hook): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_PHP_DOC_COMMENT_IN_HOOK_CLASS,
            'PhpDoc comment is missing on concrete Hook class "{{ hook }}"',
            [
                'hook' => $hook,
            ],
        );
    }

    /**
     * @param class-string<Hook> $hook
     * @param list<string> $allowedUseCases
     */
    public static function missingUseCaseDescriptionInHookClass(string $hook, array $allowedUseCases): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_USE_CASE_DESCRIPTION_IN_HOOK_CLASS,
            'Hook use case description is missing for hook "{{ hook }}". All Hook classes need to be tagged with the `@hook-use-case` tag and associated to one of the following use cases: "{{ allowedUseCases }}".',
            [
                'hook' => $hook,
                'allowedUseCases' => implode(', ', $allowedUseCases),
            ],
        );
    }

    /**
     * @param class-string<Hook> $hook
     */
    public static function missingSinceAnnotationInHookClass(string $hook): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_SINCE_ANNOTATION_IN_HOOK_CLASS,
            '`@since` annotation is missing for hook "{{ hook }}". All Hook classes need to be tagged with the `@since` annotation with the correct version, in which the hook was introduced.',
            [
                'hook' => $hook,
            ],
        );
    }

    public static function noScriptServicesFound(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NO_SCRIPT_SERVICES_FOUND,
            'No script services found'
        );
    }

    public static function incorrectGroupForScriptService(string $service): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SERVICE_SCRIPT_INCORRECT_GROUP,
            'Script Services "{{ service }}" is not correctly tagged to the group. Available groups are: "{{ allowedGroups }}".',
            [
                'service' => $service,
                'allowedGroups' => implode(', ', array_keys(ServiceReferenceGenerator::GROUPS)),
            ],
        );
    }

    /**
     * @param class-string $class
     */
    public static function missingDocBlockForMethod(string $method, string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_DOC_BLOCK_FOR_METHOD,
            'DocBlock is missing for method "{{ method }}()" in class "{{ class }}".',
            [
                'method' => $method,
                'class' => $class,
            ],
        );
    }

    /**
     * @param class-string $class
     */
    public static function missingDocBlockForMethodParam(string $param, string $method, string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_DOC_BLOCK_FOR_METHOD_PARAM,
            'Missing doc block for param "${{param}}" on method "{{ method }}()" in class "{{ class }}".',
            [
                'param' => $param,
                'method' => $method,
                'class' => $class,
            ],
        );
    }

    /**
     * @param class-string $class
     */
    public static function missingReturnAnnotationForMethod(string $method, string $class): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MISSING_RETURN_ANNOTATION_FOR_METHOD,
            'Missing @return annotation on method "{{ method }}()" in class "{{ clas }}".',
            [
                'method' => $method,
                'class' => $class,
            ],
        );
    }

    /**
     * @param class-string $class
     */
    public static function exampleFileNotFound(string $method, string $class, string $pattern): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONFIGURED_EXAMPLE_FILE_NOT_FOUND,
            'Cannot find configured example file in `@example` annotation for method "{{ method }}()" in class "{{ class }}". File with pattern "{{ pattern }}" can not be found.',
            [
                'method' => $method,
                'class' => $class,
                'pattern' => $pattern,
            ],
        );
    }

    /**
     * @param class-string $class
     * @param list<string> $files
     */
    public static function exampleFileNotUnique(string $method, string $class, string $pattern, array $files): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CONFIGURED_EXAMPLE_FILE_NOT_UNIQUE,
            'Configured file pattern in `@example` annotation for method "{{ method }}()" in class "{{ class }}" is not unique. File pattern "{{ pattern }}" matched "{{ files }}".',
            [
                'method' => $method,
                'class' => $class,
                'pattern' => $pattern,
                'files' => implode('", "', $files),
            ],
        );
    }
}
