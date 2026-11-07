/**
 * @sw-package framework
 */
export default class Plugin {
    static options?: Record<string, unknown>;

    el: HTMLElement;

    options: Record<string, unknown>;

    constructor(el: HTMLElement, options?: Record<string, unknown>, pluginName?: string | false);

    init(): void;

    update(): void;

    destroy(): void;
}
