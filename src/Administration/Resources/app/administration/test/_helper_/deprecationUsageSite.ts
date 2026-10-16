/**
 * @sw-package framework
 * @private
 */
import { join, relative } from 'node:path';
import { getCurrentInstance, type ComponentInternalInstance } from 'vue';

const adminPath = process.env.ADMIN_PATH as string;

// Neither the deprecated module nor its user: the guard's own files, so the stack walk starts at the deprecated
// module that called it, and the `$super` chain between an extended component and its members.
const skippedFiles = [
    'src/core/feature.ts',
    'src/app/service/feature.service.ts',
    'test/_setup/_mocks_/feature.service.mock.js',
    'test/_helper_/deprecations.ts',
    'src/core/factory/async-component.factory.ts',
].map((file) => join(adminPath, file));

/**
 * Where a deprecated API is used, for a guard call that captured `stack`. Inside a running component, its
 * template author and itself, e.g. `sw-order-list > sw-loader`, or just `sw-loader` when the spec mounts it.
 * Else the Administration path of the file calling the deprecated module, else the spec.
 *
 * Call it synchronously from the guard, while the component that uses the deprecated API is still current.
 *
 * @private
 * @example
 * usageSite(new Error().stack); // 'sw-order-list > sw-loader'
 */
export function usageSite(stack = ''): string {
    const instance = getCurrentInstance();
    const components = instance ? [templateAuthor(instance), instance].map(componentName).filter(Boolean) : [];

    if (components.length > 0) {
        return components.join(' > ');
    }

    return callerFile(stack) ?? specFile();
}

/**
 * The Administration path of the file that called the deprecated module: the first own file on `stack` after the
 * module's own frames, which are its internals, e.g. a helper that calls the guard. A module calling its own
 * deprecated member after an `await` is keyed to the caller that started the async chain. Undefined when the
 * stack shows nothing calling it.
 *
 * @private
 * @example
 * callerFile(new Error().stack); // 'src/app/main.ts'
 */
export function callerFile(stack = ''): string | undefined {
    const [deprecatedModule, ...files] = ownFiles(stack);
    const caller = files.find((file) => file !== deprecatedModule);

    return caller && relative(adminPath, caller);
}

/**
 * The absolute files of the frames in the `stack` text, innermost first, that are part of the Administration or
 * its specs: neither a dependency, nor Node itself (`node:internal/…`), nor one of the skipped files.
 *
 * @example
 * ownFiles('Error\n    at oldFunction (/app/src/old.ts:1:2)'); // ['/app/src/old.ts']
 */
function ownFiles(stack: string): string[] {
    // A frame reads `    at fn (/path/file.ts:1:2)`, or `    at /path/file.ts:1:2` for an anonymous function.
    return stack
        .split('\n')
        .map((line) => /^\s*at (?:.* \()?(.+?):\d+:\d+\)?$/.exec(line)?.[1] ?? '')
        .filter((file) => file.startsWith('/') && !file.includes('/node_modules/') && !skippedFiles.includes(file));
}

/**
 * The registered name of `instance`, without the `__wrapped` suffix of wrapTestComponent. Undefined for the
 * root Vue Test Utils mounts the spec's component into.
 *
 * @example
 * componentName(instance); // 'sw-loader'
 */
function componentName(instance: ComponentInternalInstance | null): string | undefined {
    const name = instance?.type.name?.replace(/__wrapped$/, '');

    return name === 'VTU_ROOT' ? undefined : name;
}

/**
 * The component that rendered the vnode of `instance`, Vue's internal `vnode.ctx`. Unlike `$parent`, it is the
 * slot's author for slot content. The wrapper of an async component is skipped.
 *
 * @example
 * componentName(templateAuthor(instance)); // 'sw-order-list'
 */
function templateAuthor(instance: ComponentInternalInstance): ComponentInternalInstance | null {
    const owner = (instance.vnode as { ctx?: ComponentInternalInstance | null }).ctx ?? null;

    return owner?.type.name === 'AsyncComponentWrapper' ? templateAuthor(owner) : owner;
}

/**
 * The Administration path of the running spec, for usages no component or other file is responsible for.
 *
 * @example
 * specFile(); // 'src/app/main.spec.ts'
 */
function specFile(): string {
    return relative(adminPath, expect.getState().testPath ?? '');
}
