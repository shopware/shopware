/**
 * @sw-package framework
 */
export type NativeEventEmitterSubscribeOptions = {
    once?: boolean;
    scope?: unknown;
};

export default class NativeEventEmitter {
    constructor(el?: Document | HTMLElement);

    get el(): Document | HTMLElement;

    set el(value: Document | HTMLElement);

    publish<Detail = Record<string, unknown>>(eventName: string, detail?: Detail, cancelable?: boolean): CustomEvent<Detail>;

    subscribe<Detail = unknown>(
        eventName: string,
        callback: (event: CustomEvent<Detail>) => void,
        opts?: NativeEventEmitterSubscribeOptions,
    ): boolean;

    unsubscribe(eventName: string): boolean;

    reset(): boolean;
}
