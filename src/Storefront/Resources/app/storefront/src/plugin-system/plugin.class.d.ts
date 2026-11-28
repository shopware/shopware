/**
 * @sw-package framework
 */
import type NativeEventEmitter from 'src/helper/emitter.helper';
import type PluginManager from './plugin.manager';

// Declared here, because every Storefront plugin imports the base class.
declare global {
    interface Window {
        PluginManager: typeof PluginManager;
        PluginBaseClass: typeof Plugin;
    }
}

/**
 * Base class of all Storefront plugins.
 *
 * Pass the shape of the plugin options as type argument to type `this.options`:
 * ```
 * type MyPluginOptions = { threshold: number };
 *
 * export default class MyPlugin extends Plugin<MyPluginOptions> {
 *     static options: MyPluginOptions = { threshold: 100 };
 * }
 * ```
 *
 * `this.el` is the element the plugin was initialized on.
 * A plugin registered without a selector is initialized on `document`.
 * If your plugin is always registered with a selector, pass the element type as second type argument:
 * ```
 * export default class MyPlugin extends Plugin<MyPluginOptions, HTMLFormElement> {}
 * ```
 */
export default class Plugin<
    Options extends object = Record<string, unknown>,
    El extends HTMLElement | Document = HTMLElement | Document,
> {
    static options?: object;

    el: El;

    $emitter: NativeEventEmitter;

    options: Options;

    _pluginName: string;

    _initialized: boolean;

    constructor(el: El, options?: Partial<Options>, pluginName?: string | false);

    init(): void;

    update(): void;

    destroy(): void;

    protected _deprecateOption(key: keyof Options & string, majorFlag: string, hint?: string): void;
}
