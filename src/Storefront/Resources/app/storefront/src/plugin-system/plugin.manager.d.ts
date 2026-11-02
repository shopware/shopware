/**
 * @sw-package framework
 */
import type Plugin from './plugin.class';

type PluginConstructor = new (
    el: HTMLElement,
    options?: Record<string, unknown>,
    pluginName?: string | false,
) => Plugin;

type AsyncPluginLoader = () => Promise<{ readonly default?: PluginConstructor }>;

export default class PluginManager {
    constructor();

    static register(
        pluginName: string,
        pluginClass: PluginConstructor | AsyncPluginLoader,
        selector?: string | NodeList | HTMLElement | Document,
        options?: Record<string, unknown>,
    ): unknown;
}
