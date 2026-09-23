/**
 * @sw-package framework
 */

import { SourceMapConsumer, type RawSourceMap } from 'source-map-js';
import type { ShopwareSetupTransformResult } from '../index';

type TransformResult = ShopwareSetupTransformResult;

/**
 * Reads a transform result's sourcemap as a typed object.
 */
export function parseSourceMap(result: TransformResult): RawSourceMap {
    return JSON.parse(result.map.toString()) as RawSourceMap;
}

/**
 * Converts a zero-based string index into source-map-js' one-based line and zero-based column.
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
 * Finds the `occurrence`-th match of a token in transformed output.
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
 * Looks up the original source location of a token in transformed output.
 */
function originalPositionFor(result: TransformResult, generatedNeedle: string, occurrence = 1) {
    const generatedIndex = nthIndexOf(result.code, generatedNeedle, occurrence);

    expect(generatedIndex).toBeGreaterThanOrEqual(0);

    const generatedPosition = positionForIndex(result.code, generatedIndex);
    const consumer = new SourceMapConsumer(parseSourceMap(result));

    return consumer.originalPositionFor(generatedPosition);
}

/**
 * Looks up a generated index with an explicit lookup bias, since consumers may pick the closest
 * mapping on either side of a column.
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
 * Asserts that a generated token has no original location for the default lookup bias.
 */
export function expectUnmapped(result: TransformResult, generatedNeedle: string, occurrence = 1): void {
    const mappedPosition = originalPositionFor(result, generatedNeedle, occurrence);

    expect(mappedPosition.source).toBeNull();
    expect(mappedPosition.line).toBeNull();
    expect(mappedPosition.column).toBeNull();
}

/**
 * Asserts that the start, middle and end of a generated token stay unmapped for both lookup biases,
 * so nothing attributes generated code to neighbouring author code.
 */
export function expectGeneratedTokenUnmapped(result: TransformResult, generatedNeedle: string): void {
    const start = result.code.indexOf(generatedNeedle);
    const middle = start + Math.floor(generatedNeedle.length / 2);
    const end = start + generatedNeedle.length - 1;

    expect(start).toBeGreaterThanOrEqual(0);

    [start, middle, end].forEach((index) => {
        [SourceMapConsumer.GREATEST_LOWER_BOUND, SourceMapConsumer.LEAST_UPPER_BOUND].forEach((bias) => {
            const mappedPosition = originalPositionForIndex(result, index, bias);

            expect(mappedPosition.source).toBeNull();
            expect(mappedPosition.line).toBeNull();
            expect(mappedPosition.column).toBeNull();
        });
    });
}
