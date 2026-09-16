/**
 * @sw-package framework
 */

/**
 * A half-open source offset range `[start, end)`.
 *
 * The coordinate space depends on the producer: analyzer ranges are script-local (they index into the
 * `<script setup>` content), while source-edit ranges are absolute SFC offsets. The shape is the same,
 * so it lives here once and is imported by both the analyzer and the source-edit layers.
 *
 * @private
 */
export type SourceRange = {
    start: number;
    end: number;
};
