/**
 * @sw-package framework
 */

import { SourceMapConsumer, type RawSourceMap } from 'source-map-js';
import type { ShopwareSetupTransformResult } from '../index';

type TransformResult = ShopwareSetupTransformResult;

/**
 * Reads a transform result's sourcemap as a typed object.
 *
 * `JSON.parse` returns `any`, so every field access on a parsed map would be unchecked. Asserting
 * `RawSourceMap` once here keeps that assertion in a single place instead of at each call site.
 */
export function parseSourceMap(result: TransformResult): RawSourceMap {
    return JSON.parse(result.map.toString()) as RawSourceMap;
}

/**
 * Converts a zero-based string index into the one-based line and zero-based column shape
 * expected by source-map-js.
 *
 * Use this when a test finds a token with `source.indexOf(...)` and needs to compare that
 * exact authored location with a sourcemap lookup result.
 *
 * Example:
 * `positionForIndex(source, source.indexOf('const headline'))`
 * returns the original line and column where the user wrote `const headline`.
 *
 */
export function positionForIndex(source: string, index: number): { line: number; column: number } {
    const beforeIndex = source.slice(0, index);
    const lines = beforeIndex.split('\n');

    return {
        line: lines.length,
        column: lines[lines.length - 1].length,
    };
}

/**
 * Finds the requested occurrence of a generated token in transformed output.
 *
 * Sourcemap tests often assert on repeated snippets, for example two identical imports or
 * multiple generated aliases. Passing `occurrence = 2` asks for the second match without
 * making the spec duplicate search-loop boilerplate.
 *
 */
function nthIndexOf(source: string, needle: string, occurrence = 1): number {
    let cursor = -1;

    for (let index = 0; index < occurrence; index += 1) {
        cursor = source.indexOf(needle, cursor + 1);

        if (cursor === -1) {
            return -1;
        }
    }

    return cursor;
}

/**
 * Looks up the original source location for a token in transformed output.
 *
 * This is the base helper for positive mapping assertions. It first verifies that the
 * generated token exists, then asks the returned sourcemap where that generated position
 * came from.
 *
 * Example:
 * `originalPositionFor(result, "const headline = computed(...)")`
 * should point back to the user's `<script setup>` line when the transform copied that code.
 *
 */
function originalPositionFor(result: TransformResult, generatedNeedle: string, occurrence = 1) {
    const generatedIndex = nthIndexOf(result.code, generatedNeedle, occurrence);

    expect(generatedIndex).toBeGreaterThanOrEqual(0);

    const generatedPosition = positionForIndex(result.code, generatedIndex);
    const consumer = new SourceMapConsumer(parseSourceMap(result));

    return consumer.originalPositionFor(generatedPosition);
}

/**
 * Looks up a generated index with an explicit source-map lookup bias.
 *
 * This is used for generated-only code. Source-map consumers may choose the closest mapping
 * before or after a generated column. Generated bridge snippets must stay unmapped for both
 * biases, otherwise debugger/editor hovers can incorrectly attribute generated code to nearby
 * user-authored lines.
 *
 */
function originalPositionForIndex(
    result: TransformResult,
    generatedIndex: number,
    bias = SourceMapConsumer.GREATEST_LOWER_BOUND,
) {
    const generatedPosition = positionForIndex(result.code, generatedIndex);
    const consumer = new SourceMapConsumer(parseSourceMap(result));

    return consumer.originalPositionFor({
        ...generatedPosition,
        bias,
    });
}

/**
 * Asserts that generated output maps back to the same original source line.
 *
 * Use this for line-level guarantees where the exact column is intentionally not important,
 * for example teleported macros or preserved imports that may be re-indented by generated
 * wrapper code but must still point to the user-authored line.
 *
 * Example:
 * `expectOriginalLine(result, source, 'defineEmits<{', 'defineEmits<{')`
 * verifies that the generated `defineEmits` call still debugs as the original macro line.
 *
 */
export function expectOriginalLine(
    result: TransformResult,
    source: string,
    generatedNeedle: string,
    originalNeedle: string,
    occurrence = 1,
): void {
    const originalIndex = source.indexOf(originalNeedle);

    expect(originalIndex).toBeGreaterThanOrEqual(0);

    const originalPosition = positionForIndex(source, originalIndex);
    const mappedPosition = originalPositionFor(result, generatedNeedle, occurrence);

    expect(mappedPosition.source).toBe(result.filename);
    expect(mappedPosition.line).toBe(originalPosition.line);
}

/**
 * Asserts that generated output maps back to the exact original source line and column.
 *
 * Use this for copied authored snippets that should preserve precise positions, such as
 * template interpolations, directive expressions, and setup statements copied into the
 * generated callback.
 *
 * Example:
 * `expectOriginalPosition(result, source, '{{ headline }}', '{{ headline }}')`
 * verifies that the final template interpolation still maps to exactly where the user wrote it.
 *
 */
export function expectOriginalPosition(
    result: TransformResult,
    source: string,
    generatedNeedle: string,
    originalNeedle: string,
    occurrence = 1,
): void {
    const originalIndex = source.indexOf(originalNeedle);

    expect(originalIndex).toBeGreaterThanOrEqual(0);

    const originalPosition = positionForIndex(source, originalIndex);
    const mappedPosition = originalPositionFor(result, generatedNeedle, occurrence);

    expect(mappedPosition.source).toBe(result.filename);
    expect(mappedPosition.line).toBe(originalPosition.line);
    expect(mappedPosition.column).toBe(originalPosition.column);
}

/**
 * Asserts that a generated token has no original source location for the default lookup bias.
 *
 * Use this for generated snippets that do not need the stronger whole-token guarantee, for
 * example single fallback statements like `return () => null;`.
 *
 * If a token sits between authored chunks and could be hit with different source-map lookup
 * biases, prefer `expectGeneratedTokenUnmapped(...)`.
 *
 */
export function expectUnmapped(result: TransformResult, generatedNeedle: string, occurrence = 1): void {
    const mappedPosition = originalPositionFor(result, generatedNeedle, occurrence);

    expect(mappedPosition.source).toBeNull();
    expect(mappedPosition.line).toBeNull();
    expect(mappedPosition.column).toBeNull();
}

/**
 * Asserts that an entire generated token stays unmapped for both source-map lookup biases.
 *
 * Use this for bridge code inserted next to user-authored code, such as
 * `(__shopwareSetupBindings.props)`, `:data="$dataScope"`, or generated `__swOverride`
 * aliases. The helper checks the start, middle, and end of the token with both
 * `GREATEST_LOWER_BOUND` and `LEAST_UPPER_BOUND`.
 *
 * Expected result: every lookup returns `{ source: null, line: null, column: null }`, proving
 * that debuggers and editor integrations do not blame nearby user code for generated output.
 *
 */
export function expectGeneratedTokenUnmapped(result: TransformResult, generatedNeedle: string): void {
    const start = result.code.indexOf(generatedNeedle);
    const middle = start + Math.floor(generatedNeedle.length / 2);
    const end = start + generatedNeedle.length - 1;

    expect(start).toBeGreaterThanOrEqual(0);

    [
        start,
        middle,
        end,
    ].forEach((index) => {
        [
            SourceMapConsumer.GREATEST_LOWER_BOUND,
            SourceMapConsumer.LEAST_UPPER_BOUND,
        ].forEach((bias) => {
            const mappedPosition = originalPositionForIndex(result, index, bias);

            expect(mappedPosition.source).toBeNull();
            expect(mappedPosition.line).toBeNull();
            expect(mappedPosition.column).toBeNull();
        });
    });
}
