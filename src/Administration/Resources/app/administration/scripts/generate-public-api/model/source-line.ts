/**
 * @sw-package framework
 *
 * Where an element of the model stands in the sources.
 */

/**
 * @private
 *
 * A line of a source file, for the findings of the break check. The snapshot files never contain it.
 */
export interface SourceLine {
    file: string;
    line: number;
}
