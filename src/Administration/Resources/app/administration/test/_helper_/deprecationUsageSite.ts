/**
 * @sw-package framework
 * @private
 */
import { join, relative, resolve } from 'node:path';
import { getCurrentInstance, type ComponentInternalInstance } from 'vue';
import type { DeprecationOptions } from 'src/core/feature';

// Not `ADMIN_PATH`: an exported shell value may point it at another checkout.
const adminPath = resolve(__dirname, '../..');

// The guard and recorder, so the stack walk starts at the deprecated module, and the `$super` plumbing between
// an extended component and its members.
const skippedFiles = [
    'src/core/feature.ts',
    'src/app/service/feature.service.ts',
    'test/_setup/_mocks_/feature.service.mock.js',
    'src/app/plugin/deprecation.plugin.ts',
    'test/_helper_/deprecations.ts',
    'src/core/factory/async-component.factory.ts',
].map((file) => join(adminPath, file));

/**
 * Where a deprecated API is used, for a guard call that created `error`:
 * - `blame` `'author'`, for a component or prop the deprecation plugin guards: its template author and itself,
 *   e.g. `sw-order-list > sw-loader`, or just `sw-loader` when the spec mounts it.
 * - `blame` `'caller'` (default), for any other guard, where the code calling the deprecated API is at fault: the
 *   running component, e.g. `sw-order-list`, else the Administration path of the file calling the deprecated
 *   module, else the spec.
 *
 * Only the file fallback reads `error.stack`, because formatting a stack is expensive.
 *
 * Call it synchronously from the guard, while the component that uses the deprecated API is still current.
 * Vue sets no current component in watcher callbacks and template event handlers, so their usages fall back to
 * the calling file or the spec, like code after an `await`.
 *
 * @private
 * @example
 * usageSite(new Error(), 'author'); // 'sw-order-list > sw-loader'
 */
export function usageSite(error: Error, blame: DeprecationOptions['_blame'] = 'caller'): string {
    const instance = getCurrentInstance();
    const atFault = instance && blame === 'author' ? [templateAuthor(instance), instance] : [instance];
    const components = atFault.map(componentName).filter(Boolean);

    if (components.length > 0) {
        return components.join(' > ');
    }

    return callerFile(error.stack) ?? specFile();
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
    const [deprecatedModule, ...files] = ownFrames(stack).map(({ file }) => file);
    const caller = files.find((file) => file !== deprecatedModule);

    return caller && relative(adminPath, caller);
}

/**
 * One frame of a stack trace, as `ownFrames` parses it.
 *
 * @private
 * @example
 * { text: '    at oldFunction (/app/src/old.ts:12:5)', file: '/app/src/old.ts', position: '12:5' }
 */
export type StackFrame = {
    /** The frame's line in the stack, for an error whose stack should start at the usage. */
    text: string;
    /** The absolute path of the frame's file. */
    file: string;
    /** `line:column` in `file`. */
    position: string;
};

/**
 * The frames of the `stack` text, innermost first, that are part of the Administration or its specs: neither a
 * dependency, nor Node itself (`node:internal/…`), nor one of the skipped files.
 *
 * @private
 * @example
 * ownFrames('Error\n    at oldFunction (/app/src/old.ts:12:5)'); // [{ text: '    at oldFunction (…)', file: '/app/src/old.ts', position: '12:5' }]
 */
export function ownFrames(stack = ''): StackFrame[] {
    return stack.split('\n').flatMap((text) => {
        // A frame reads `    at fn (/path/file.ts:1:2)`, or `    at /path/file.ts:1:2` for an anonymous function.
        const [, file = '', position = ''] = /^\s*at (?:.* \()?(.+?):(\d+:\d+)\)?$/.exec(text) ?? [];
        const isOwn = file.startsWith('/') && !file.includes('/node_modules/') && !skippedFiles.includes(file);

        return isOwn ? [{ text, file, position }] : [];
    });
}

/**
 * The `file:line:column` of `frame`, relative to the Administration directory, for the failure report.
 *
 * @private
 * @example
 * location({ text: '…', file: '/<admin>/test/oldFunction.ts', position: '13:13' }); // 'test/oldFunction.ts:13:13'
 */
export function location({ file, position }: StackFrame): string {
    return `${relative(adminPath, file)}:${position}`;
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
