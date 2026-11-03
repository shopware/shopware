/**
 * @sw-package framework
 * @private
 */

/** Whether a source names the file or directory as a segment of a path, such as `'./_mock/schema.json'`. */
const namesPath = (source: string, name: string) =>
    new RegExp(`[/'"\`]${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}(?=[/'"\`])`).test(source);

/**
 * Whether a file or directory next to a component is a test fixture, such as a `_mock/` folder a spec reads with
 * `path.join(__dirname, './_mock/schema.json')`: a spec names it and no other file of the component does.
 * A fixture moves into `tests/` with the specs, so the paths the specs build from `__dirname` still lead to it.
 *
 * @example
 * isTestFixture('_mock', ["path.join(__dirname, './_mock/a.json')"], ["import template from './x.html.twig';"]);
 * // → true
 */
export function isTestFixture(name: string, specSources: string[], componentSources: string[]): boolean {
    return (
        specSources.some((source) => namesPath(source, name)) && !componentSources.some((source) => namesPath(source, name))
    );
}
