# Invariants by construction

An invariant is a guarantee that every instance of a class keeps. The class that owns an invariant enforces the invariant when it builds an instance. Other code relies on that guarantee. Other code does not check the guarantee again.

Related guideline: [Writing code for static analysis](writing-code-for-static-analysis.md) addresses values whose declared type is wider than the value the code expects.

## Rules

- Carry a guarantee in the construction of the class that owns it. The means are a static type, a private constructor with validating factory methods, or a single derivation of a value.
- Do not carry a guarantee by convention. A docblock that asks callers not to mutate an object is a convention. A `readonly` property is a construction.
- Do not state one contract in two places. Derive the second place from the first.
- Do not add a second check for a guarantee that a type or a constructor already makes.
- Do not let another layer catch and hide a violation. The method where the violation occurs throws.
- When a constructor classifies a value, readers call the predicate of the class. Readers do not classify the value again.

## Why

A contract that exists twice drifts. When a duplicate check and the original guarantee differ, a reader cannot tell which of the two is authoritative. PHPStan checks a declared type at every call site. A hand-written check runs only where a developer remembered to write it.

## Examples

Correct, private constructor: in the Content System, `SlicedDistributionConfig` is a `final readonly` class with a private constructor. Its factory methods `withSliceSize()` and `fromArray()` reject a slice size below 1. Every instance therefore holds a slice size of at least 1. No other class checks the bound.

Correct, static type: `WrapElementsRequest::$elementIds` is a `readonly` property of type `array`. `WrapElementsRequest` declares constraints on the entries and on their uniqueness. `WrapElementsRequest` declares no constraint on the array type. PHP rejects a non-array argument with a `TypeError`. An array constraint would therefore never produce a violation.

Correct, single derivation: `StoredValue::fromDecoded()` assigns the variant of a value once. Readers call `isMap()`. Readers do not repeat the key analysis.

Incorrect (invented example):

```php
public function wrap(WrapElementsRequest $request): void
{
    // The property type already guarantees an array. This check can never fire.
    if (!\is_array($request->elementIds)) {
        throw new \InvalidArgumentException('elementIds must be an array');
    }
}
```

## What this rule does not cover

- This rule excludes the validation of external input: a request body, a decoded JSON value or an app manifest. The first code that receives the input validates it.
- This rule excludes a check that serves direct callers of a class when the normal path cannot reach the check. The check may stay if its docblock states why the normal path cannot reach it.
- This rule excludes a guard in a constructor or a factory method that no production caller reaches today. The guard stays. The guard keeps future callers correct.
