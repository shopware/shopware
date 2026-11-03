/**
 * @sw-package framework
 * @private
 */

/**
 * Removes the bare import of the component that a component extends, such as `import 'src/app/component/base/sw-collapse';`.
 * It loads the base config without using it: the component registry finds the base by its name.
 *
 * @example
 * dropBaseImport("import 'src/app/component/base/sw-collapse';\nimport template from './x.html.twig';\n", 'sw-collapse')
 * // → "import template from './x.html.twig';\n"
 */
export function dropBaseImport(source: string, base: string): string {
    const escaped = base.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

    return source.replace(new RegExp(`^import '(?:[^']*/)?${escaped}(?:/index(?:\\.[jt]s)?)?';\\n`, 'gm'), '');
}
