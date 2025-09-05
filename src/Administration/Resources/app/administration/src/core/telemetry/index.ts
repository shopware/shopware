/**
 * @sw-package framework
 */
import { type Ref, ref } from 'vue';
import type { RouteLocation, Router } from 'vue-router';
import { TelemetryEvent, type EventTypes, type EventPayload } from './types';
/**
 * @private
 */
export class Telemetry {
    readonly #eventTarget: EventTarget;

    #initialized: Ref<boolean>;

    private debug = false;

    // for debugging in the browser only
    private observedNodes: Node[] = [];

    constructor() {
        this.#eventTarget = new EventTarget();
        this.#initialized = ref(false);

        this.#eventTarget.addEventListener('telemetry', (event) => {
            if (this.debug) {
                // eslint-disable-next-line no-console
                console.log('telemetry event dispatched:', event);
            }
        });
    }

    initialize() {
        if (!Shopware.Feature.isActive('PRODUCT_ANALYTICS')) {
            return;
        }

        if (this.isInitialized) {
            throw new Error('Telemetry is already initialized');
        }

        this.initializeObservables();
        this.initializePageChanges();

        this.#initialized.value = true;
    }

    get isInitialized() {
        return this.#initialized.value;
    }

    addListener(callback: EventListenerOrEventListenerObject) {
        this.#eventTarget.addEventListener('telemetry', callback);
    }

    track(eventData: EventPayload<'programmatic'>) {
        this.dispatchEvent('programmatic', eventData);
    }

    identify(userId: string, deviceId: string, locale: string, permissions: string[]) {
        this.dispatchEvent('identify', {
            userId,
            locale,
            deviceId,
            permissions,
        });
    }

    private initializePageChanges(): void {
        void Shopware.Application.viewInitialized.then(() => {
            // @ts-expect-error router is available after viewInitialized is fulfilled
            const router = Shopware.Application.view.router as Router;

            router.afterEach((to: RouteLocation, from: RouteLocation) => {
                if (!this.isInitialized) {
                    return;
                }
                this.dispatchEvent('page_change', { from, to });
            });
        });
    }

    private initializeObservables(): void {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node: Node) => {
                    if (!(node instanceof Element)) {
                        return;
                    }

                    const n = new Set<Element>();

                    n.add(node);

                    if (node.childNodes.length > 0) {
                        node.querySelectorAll('a').forEach((link) => {
                            n.add(link);
                        });

                        node.querySelectorAll('button').forEach((buton) => {
                            n.add(buton);
                        });

                        node.querySelectorAll('[data-product-analytics=true]').forEach((trackedElement) => {
                            n.add(trackedElement);
                        });
                    }

                    n.forEach((elementToTrack) => this.observeNode(elementToTrack));
                });
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    private observeNode(node: Element): void {
        if (node.nodeName !== 'A' && node.nodeName !== 'BUTTON' && node.getAttribute('data-product-analytics') === null) {
            return;
        }

        if (this.debug) {
            this.observedNodes.push(node);
        }

        if (node.nodeName === 'A') {
            node.addEventListener('click', (event) => {
                const target = event.currentTarget ?? event.target;
                if (!this.assertIsElement(target)) {
                    return;
                }

                this.dispatchEvent('link_visited', {
                    href: target.getAttribute('href') ?? '',
                    linkType: target.getAttribute('target') === '_blank' ? 'external' : 'internal',
                });
            });

            return;
        }

        node.addEventListener('click', (event) => {
            const target = event.currentTarget ?? event.target;
            if (!this.assertIsElement(target)) {
                return;
            }

            this.dispatchEvent('user_interaction', {
                target: event.currentTarget ?? event.target,
                originalEvent: event,
            });
        });
    }

    private dispatchEvent<N extends EventTypes>(eventType: N, eventData: EventPayload<N>): void {
        if (!Shopware.Feature.isActive('PRODUCT_ANALYTICS')) {
            return;
        }

        this.#eventTarget.dispatchEvent(new TelemetryEvent<N>(eventType, eventData));
    }

    private assertIsElement(target: EventTarget | null): target is Element {
        return target !== null && target instanceof Element;
    }
}

/**
 * @private
 */
export default new Telemetry();
