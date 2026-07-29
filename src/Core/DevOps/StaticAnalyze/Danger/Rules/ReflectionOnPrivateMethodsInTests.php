<?php declare(strict_types=1);

namespace Shopware\Core\DevOps\StaticAnalyze\Danger\Rules;

use Danger\Context;
use Danger\Struct\File;
use Danger\Struct\FileCollection;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Tests must not invoke private or protected methods of Shopware classes via reflection: test the
 * behaviour through the public API instead. Third-party targets and metadata reads stay acceptable.
 *
 * For each reflective invocation added to a test file, the rule resolves the target class and the
 * method's visibility from the changed files and the checkout. A proven non-public Shopware method
 * fails the build; a target the diff cannot prove only raises a resolvable warning.
 *
 * @internal
 */
#[Package('framework')]
class ReflectionOnPrivateMethodsInTests
{
    private const INVOCATION_PATTERN = '/(?:\$(\w+))?->(?:invoke|invokeArgs|setAccessible)\s*\(/';

    // new \ReflectionMethod(Target::class, 'method') — captures class reference and method name
    private const REFLECTION_METHOD_SUBPATTERN = 'new\s+\\\\?ReflectionMethod\s*\(\s*\\\\?([\w\\\\]+)::class\s*,\s*[\'"](\w+)[\'"]';

    // (new \ReflectionClass(Target::class))->getMethod('method') — captures class reference and method name
    private const REFLECTION_CLASS_GET_METHOD_SUBPATTERN = 'new\s+\\\\?ReflectionClass\s*\(\s*\\\\?([\w\\\\]+)::class\s*\)\s*\)\s*->getMethod\s*\(\s*[\'"](\w+)[\'"]';

    public function __construct(
        private readonly string $projectDir = __DIR__ . '/../../../../../..',
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    public function __invoke(Context $context): void
    {
        $files = $context->platform->pullRequest->getFiles();

        $violations = [];
        $unprovenFiles = [];

        foreach ($files as $file) {
            if (!\in_array($file->status, [File::STATUS_ADDED, File::STATUS_MODIFIED], true)) {
                continue;
            }

            if (preg_match('#^(?:tests|src)/.*Test\.php$#', $file->name) !== 1) {
                continue;
            }

            // rule-test fixtures deliberately contain the pattern
            if (str_contains($file->name, '/data/')) {
                continue;
            }

            $invocationLines = [];
            foreach (explode("\n", $file->patch) as $line) {
                if (str_starts_with($line, '+') && preg_match(self::INVOCATION_PATTERN, $line)) {
                    $invocationLines[] = $line;
                }
            }

            if ($invocationLines === []) {
                continue;
            }

            $content = $file->getContent();
            [$reflectedMethods, $unproven] = $this->reflectedMethods($invocationLines, $content);

            $proven = false;
            foreach ($reflectedMethods as [$classReference, $method]) {
                $class = $this->resolveClass($classReference, $content);

                // third-party targets stay acceptable, test-support classes are not production API
                if (!str_starts_with($class, 'Shopware\\') || str_starts_with($class, 'Shopware\\Tests\\')) {
                    continue;
                }

                $visibility = $this->methodVisibility($class, $method, $files);

                if ($visibility === 'private' || $visibility === 'protected') {
                    $violations[] = \sprintf('`%s`: `%s::%s()` is %s', $file->name, $class, $method, $visibility);
                    $proven = true;
                } elseif ($visibility === null) {
                    $unproven = true;
                }
            }

            if ($unproven && !$proven) {
                $unprovenFiles[] = $file->name;
            }
        }

        if ($violations !== []) {
            $context->failure(
                'These tests invoke a private or protected method of a Shopware class via reflection.'
                . ' A non-public method is an implementation detail: test the behaviour through the public API,'
                . ' or restructure the code (e.g. extract the logic into a collaborator) so it is publicly testable:<br/>'
                . implode('<br/>', $violations)
            );
        }

        if ($unprovenFiles !== []) {
            $context->warning(
                'These test files invoke a method via reflection (`->invoke()`, `->invokeArgs()`, `->setAccessible()`)'
                . ' on a target this check cannot resolve from the diff.'
                . ' If the target is a private or protected method of a Shopware class, test the behaviour through the'
                . ' public API instead, or restructure the code (e.g. extract the logic into a collaborator) so it is'
                . ' publicly testable. `setAccessible()` has no effect since PHP 8.1.<br/>'
                . 'Resolve this thread if the reflection targets a third-party class, where no public alternative exists,'
                . ' or a public method.<br/>'
                . implode('<br/>', $unprovenFiles)
            );
        }
    }

    /**
     * Pairs each added reflective invocation with the method it reflects, using the construction
     * sites in the full file (they are not necessarily part of the same diff).
     *
     * @param list<string> $invocationLines
     *
     * @return array{0: list<array{string, string}>, 1: bool} [class reference, method name] pairs,
     *                                                        and whether any invocation stayed unresolved
     */
    private function reflectedMethods(array $invocationLines, string $content): array
    {
        // $ref = new \ReflectionClass(Target::class) / new \ReflectionObject(...)
        $classVariables = [];
        preg_match_all('/\$(\w+)\s*=\s*new\s+\\\\?Reflection(?:Class|Object)\s*\(\s*\\\\?([\w\\\\]+)::class/', $content, $matches, \PREG_SET_ORDER);
        foreach ($matches as $match) {
            $classVariables[$match[1]] = $match[2];
        }

        // $method = new \ReflectionMethod(Target::class, 'method')
        $methodVariables = [];
        preg_match_all('/\$(\w+)\s*=\s*' . self::REFLECTION_METHOD_SUBPATTERN . '/', $content, $matches, \PREG_SET_ORDER);
        foreach ($matches as $match) {
            $methodVariables[$match[1]] = [$match[2], $match[3]];
        }

        // $method = (new \ReflectionClass(Target::class))->getMethod('method')
        preg_match_all('/\$(\w+)\s*=\s*\(' . self::REFLECTION_CLASS_GET_METHOD_SUBPATTERN . '/', $content, $matches, \PREG_SET_ORDER);
        foreach ($matches as $match) {
            $methodVariables[$match[1]] = [$match[2], $match[3]];
        }

        // $method = $ref->getMethod('method')
        preg_match_all('/\$(\w+)\s*=\s*\$(\w+)->getMethod\s*\(\s*[\'"](\w+)[\'"]/', $content, $matches, \PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (isset($classVariables[$match[2]])) {
                $methodVariables[$match[1]] = [$classVariables[$match[2]], $match[3]];
            }
        }

        $pairs = [];
        $unproven = false;

        foreach ($invocationLines as $line) {
            // construction chained into the invoking statement itself
            if (preg_match('/' . self::REFLECTION_METHOD_SUBPATTERN . '/', $line, $match)
                || preg_match('/' . self::REFLECTION_CLASS_GET_METHOD_SUBPATTERN . '/', $line, $match)) {
                $pairs[] = [$match[1], $match[2]];

                continue;
            }

            if (preg_match(self::INVOCATION_PATTERN, $line, $match) === 1
                && isset($match[1], $methodVariables[$match[1]])) {
                $pairs[] = $methodVariables[$match[1]];

                continue;
            }

            $unproven = true;
        }

        return [array_values(array_unique($pairs, \SORT_REGULAR)), $unproven];
    }

    private function resolveClass(string $reference, string $content): string
    {
        if (str_contains($reference, '\\')) {
            return $reference;
        }

        if (preg_match('/^use\s+([\w\\\\]+\\\\' . $reference . ')\s*;/m', $content, $use)) {
            return $use[1];
        }

        if (preg_match('/^use\s+([\w\\\\]+)\s+as\s+' . $reference . '\s*;/m', $content, $alias)) {
            return $alias[1];
        }

        // unqualified without an import: a class in the test's own namespace, not production API
        return $reference;
    }

    /**
     * Returns the visibility keyword, or null when the declaration is not provable from the
     * class's own file (inherited, trait, or the file is not part of the checkout).
     */
    private function methodVisibility(string $class, string $method, FileCollection $files): ?string
    {
        $source = $this->classSource($class, $files);
        if ($source === null) {
            return null;
        }

        $name = preg_quote($method, '/');

        if (preg_match('/^[^\S\n]*(?:(?:abstract|final|static)\s+)*(public|protected|private)(?:\s+(?:abstract|final|static))*\s+function\s+&?' . $name . '\s*\(/mi', $source, $match)) {
            return strtolower($match[1]);
        }

        // a declaration without a visibility modifier is public
        if (preg_match('/^[^\S\n]*(?:(?:abstract|final|static)\s+)*function\s+&?' . $name . '\s*\(/mi', $source)) {
            return 'public';
        }

        return null;
    }

    private function classSource(string $class, FileCollection $files): ?string
    {
        $relativePath = 'src/' . str_replace('\\', '/', substr($class, \strlen('Shopware\\'))) . '.php';

        // the PR's version of the class wins over the checkout, which is the target branch's state
        foreach ($files as $file) {
            if ($file->name === $relativePath) {
                return $file->status === File::STATUS_REMOVED ? null : $file->getContent();
            }
        }

        $path = $this->projectDir . '/' . $relativePath;

        return $this->filesystem->exists($path) ? $this->filesystem->readFile($path) : null;
    }
}
