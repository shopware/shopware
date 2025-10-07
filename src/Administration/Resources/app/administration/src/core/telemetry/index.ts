/**
 * @sw-package framework
 */
import { type Ref, ref } from 'vue';
import type { RouteLocation, Router } from 'vue-router';
import { TelemetryEvent, type EventTypes, type EventPayload, type ElementQuery, type Config } from './types';
import AnchorTags from './ElementQueries/anchor-tags';
import ProductAnalyticsTag from './ElementQueries/product-analytics-tag';
import TaggedButtons from './ElementQueries/tagged-buttons';
/**
 * @private
 */
export class Telemetry {
    readonly #eventTarget: EventTarget;

    readonly #elementQueries: ElementQuery[];

    #initialized: Ref<boolean>;

    private debug = false;

    // for debugging in the browser only
    private observedNodes: Node[] = [];

    constructor(config: Config) {
        this.#eventTarget = new EventTarget();
        this.#initialized = ref(false);
        this.#elementQueries = config.queries;

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

    removeListener(callback: EventListenerOrEventListenerObject) {
        this.#eventTarget.removeEventListener('telemetry', callback);
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
            const observedNodes = new Set<Element>();

            this.#elementQueries.forEach((query) => {
                query(mutations).forEach((observedNode) => {
                    observedNodes.add(observedNode);
                });
            });

            observedNodes.forEach((node) => this.observeNode(node));
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });
    }

    private observeNode(el: Element): void {
        if (this.debug) {
            this.observedNodes.push(el);
        }

        const eventName = el.getAttribute('data-analytics-event') ?? 'click';

        el.addEventListener(eventName, (event) => {
            const target = event.currentTarget ?? event.target;
            if (!this.isHTMLElement(target)) {
                return;
            }

            this.dispatchEvent('user_interaction', {
                target: target,
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

    private isHTMLElement(target: EventTarget | null): target is HTMLElement {
        return target !== null && target instanceof HTMLElement;
    }
}

/**
 * @private
 */
export default new Telemetry({
    queries: [
        AnchorTags,
        TaggedButtons,
        ProductAnalyticsTag,
    ],
});
