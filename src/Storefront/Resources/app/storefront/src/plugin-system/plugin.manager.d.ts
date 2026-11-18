/**
 * @sw-package framework
 */
import type Plugin from './plugin.class';

/**
 * Accepts every plugin class, whatever its options type.
 * The element and options are typed `never` because the PluginManager does not know which ones a registered plugin expects.
 */
type PluginConstructor = new (
    el: never,
    options?: never,
    pluginName?: string | false,
) => Plugin<object>;

type AsyncPluginLoader = () => Promise<{ readonly default?: PluginConstructor }>;

type PluginSelector = string | NodeList | HTMLElement | Document;

/**
 * Methods and properties that `extend()` copies onto the prototype of a new class derived from the extended plugin.
 * Inside the methods, `this` is the plugin instance.
 * A class is not accepted, because its prototype methods would not be copied.
 */
type PluginExtension<Methods extends Record<string, unknown>> = Methods & ThisType<Plugin & Methods>;

/**
 * Use the shared instance via `window.PluginManager`.
 * Importing this module at runtime in an extension bundle creates a second registry,
 * which breaks the initialization of all Storefront plugins.
 */
export default class PluginManager {
    constructor();

    static register(
        pluginName: string,
        pluginClass: PluginConstructor | AsyncPluginLoader,
        selector?: PluginSelector,
        options?: Record<string, unknown>,
    ): unknown;

    static deregister(pluginName: string, selector?: PluginSelector): unknown;

    /**
     * Registers a new plugin `newName` that extends the plugin `fromName` with the passed methods.
     * To replace a plugin with your own class, use `override()`.
     */
    static extend<Methods extends Record<string, unknown>>(
        fromName: string,
        newName: string,
        pluginMethods: PluginExtension<Methods>,
        selector?: PluginSelector,
        options?: Record<string, unknown>,
    ): unknown;

    static override(
        overrideName: string,
        pluginClass: PluginConstructor | AsyncPluginLoader,
        selector?: PluginSelector,
        options?: Record<string, unknown>,
    ): unknown;

    static getPluginList(): Record<string, Map<string, unknown>>;

    static getPlugin(pluginName: string): Map<string, unknown> | null;

    static getPluginInstances(pluginName: string): Plugin<object>[];

    static getPluginInstanceFromElement<T extends Plugin<object> = Plugin<object>>(el: Node, pluginName: string): T | undefined;

    static getPluginInstancesFromElement(el: Node): Map<string, Plugin<object>> | null;

    static initializePlugins(): Promise<void>;

    static initializePluginsInParentElement(parentElement: HTMLElement | Document): Promise<void>;

    static initializePlugin(pluginName: string, selector: PluginSelector, options?: Record<string, unknown>): Promise<void>;

    static callPluginMethod(pluginName: string, methodName: string, ...args: unknown[]): void;
}
