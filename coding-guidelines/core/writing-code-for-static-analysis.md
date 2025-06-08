# Writing code for static analysis

We rely heavily on static analysis (read PHPStan) to ensure the quality of our code and enforce coding guidelines and best practices.
For static analysis to work properly, it is important that the code is written in a way that is understandable by static analysis tools, this mostly means that the code uses static types where possible, to catch a bunch of possible errors.

A main challenge is to narrow down the types when part of the code is implemented in a generic way and uses the dynamics that PHP offers.
This document will explain some of the approaches that can be used in those cases. They are presented in the order in which they should be used, the first one being the preferred one.

So this document mainly deals with issues on how to fix common PHPStan errors like:
`Can not call method getFoo() on Foo\\Bar|null.`
`Method Foo\\Bar::getFoo() expected first parameter to be string, but string|int|null given.`

## 1. Ensure the types at runtime with explicit checks

To ensure the types at runtime, the most common approach is to use an explicit type or null checks on the variable's values that we want to check.
**Examples:**
```php
$foo = $bar->getFoo(); // $foo is Foo|null, but we expect only Foo

if ($foo === null) {
    // handle the error case
    throw new \InvalidArgumentException('Foo must not be null');
}
```
or 
```php
$foo = $bar->getFoo(); // $foo is mixed, but we expect only string

if (!is_string($foo)) {
    // handle the error case
    throw new \InvalidArgumentException('$foo must not be string');  
}
```
or
```php
$foo = $bar->getFoo(); // $foo is object, but we expect it to be Foo instance

if (!$foo instanceof Foo) {
    // handle the error case
    throw new \InvalidArgumentException('$foo must be instance of Foo');  
}
```

This approach catches type mismatches at runtime and ensures that the code is executed in a way that is expected to. It allows handling the error case explicitly as well, by throwing an error or returning a default value or something else entirely based on the specific case.

Runtime checks should be preferred as with them it is impossible to have type mismatches further down in the code.
The downside is that the error case has to be handled explicitly, which is a lot of overhead in cases where type mismatches may happen in theory (because technically the type hints allow mismatches), but for all practical reasons will never happen in reality.

### 1.1 Caution when using type casts

Type casts can be used as well to ensure types at runtime. However, this should not be the goto-solution, as PHP internally does a lot of [magic when casting](https://www.php.net/manual/en/language.types.type-juggling.php#language.types.typecasting) from one type to another.
Thus, it is possible that type cast may lead to unexpected results, e.g. casting null to an empty string where null was not expected in the first place. This makes catching those types of errors even harder, as type casts might hide the root cause of an error, that then only pops up later it in the code, where it is not obvious what caused the error.
Additionally, unexpected type casts cannot be caught by static analysis tools, so the effect of the cast has to be explicitly tested for.

This means you should only use type casts when you are sure what the possible inputs are and the result of the cast is actually what we would expect.

**Examples:**
```php
$foo = $bar->getFoo(); // $foo is mixed, but we expect only string

$foo = (string) $foo; // this might hide unexpected conversions from non-string values to string
```

### 1.2 Ensuring types in unit tests

In unit tests, the type ensuring asserts from PhpUnit can be used to ensure that the types are correct. Those are also evaluated at (test) runtime, thus they guarantee have full type safety. 
This is especially useful as the error case in unit tests does not have to be handled manually, as the error will simply lead to a test failure when a type is encountered that was not expected.
**Examples:**
```php
$foo = $bar->getFoo(); // $foo is Foo|null, but we expect only Foo

static::assertNotNull($foo);
```
or
```php
$foo = $bar->getFoo(); // $foo is mixed, but we expect only string

static::assertIsString($foo);
```
or
```php
$foo = $bar->getFoo(); // $foo is object, but we expect it to be Foo instance

static::assertInstanceOf(Foo::class, $foo);
```

For unit tests, this approach should be preferred and there is basically no case where the other approaches further down this list should be used.

## 2. Ensure types during development and test with `assert()`

Instead of making the type checks explicitly and then having to handle the error case manually, [PHP's built in `assert()`](https://www.php.net/manual/en/function.assert.php) function can be used to ensure the types.
Those `asserts` work similar to explicit if-checks, the main difference is that assert checks can be turned off completely by configuration (which is the recommended setting for production setups).
This means that the `asserts` will only be evaluated in development and test environments (e.g. during local development and unit test execution), with the consequence that `asserts` don't guarantee full type safety as it might happen that in a prod environment unexpected things might happen, that where not encountered previously where the asserts where evaluated.
The upside of using `asserts` is that they will throw a generic `AssertionError` when the type is not as expected, which is a lot easier to handle than having to handle the error case manually.

**Examples:**
```php
$foo = $bar->getFoo(); // $foo is Foo|null, but we expect only Foo

assert($foo !== null);
```
or 
```php
$foo = $bar->getFoo(); // $foo is mixed, but we expect only string

assert(is_string($foo));
```
or
```php
$foo = $bar->getFoo(); // $foo is object, but we expect it to be Foo instance

assert($foo instanceof Foo);
```

## 3. Narrow types during static analysis with `@var` annotations

Lastly it is possible to use `@var` annotations to narrow down types during static analysis. 
Those annotations are evaluated by static analysis tools, but are ignored at runtime, which means that they offer no real type safety at runtime.
With the [latest PHPStan version](https://phpstan.org/blog/phpstan-1-10-comes-with-lie-detector) it is now able to detect cases where the `@var` annotations contradict with the real types specified on language level, but beside that there are no checks that the type we expect and specify as `@var` annotations are actually the correct types we get at runtime.
Which also means that wrong `@var` annotations can actively hide type mismatches that would otherwise be detected by static analysis tools.

Thus `@var` annotations should only be used when the other approaches are not possible or not feasible as a last resort.
**Examples:**
```php
/** @var Foo $foo */
$foo = $bar->getFoo(); // $foo is Foo|null, but we expect only Foo
```
or 
```php
/** @var string $foo */
$foo = $bar->getFoo(); // $foo is mixed, but we expect only string
```
or
```php
/** @var Foo $foo */
$foo = $bar->getFoo(); // $foo is object, but we expect it to be Foo instance
```

## On `@var`, `@param` and `@return` annotations

`@var`, `@param` and `@return` annotations should only be used when they cover cases that you could not accomplish using language features alone.
This mainly includes:
* [Generics](https://phpstan.org/blog/generics-in-php-using-phpdocs)
* [Array shapes](https://phpstan.org/writing-php-code/phpdoc-types#array-shapes)
* special PHPStan types e.g. [class-string](https://phpstan.org/writing-php-code/phpdoc-types#class-string), [integer ranges](https://phpstan.org/writing-php-code/phpdoc-types#integer-ranges), etc

Note that `Intersection & Union Types` are not covered here, as they are now a native language feature and the language feature should be used instead.
