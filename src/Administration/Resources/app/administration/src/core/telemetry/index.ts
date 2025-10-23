/**
 * @sw-package framework
 */
import { watch, ref, type Ref } from 'vue';
import type { RouteLocation, Router } from 'vue-router';
import { TelemetryEvent, type EventTypes, type EventPayload, type ElementQuery, type Config } from './types';
import AnchorTags from './ElementQueries/anchor-tags';
import ProductAnalyticsTag from './ElementQueries/product-analytics-tag';
import TaggedButtons from './ElementQueries/tagged-buttons';
/**
 * @private
 */
export class Telemetry {
    readonly #elementQueries: ElementQuery[];

    #initialized: boolean;

    #debug: Ref<boolean>;

    // for debugging in the browser only
    observedNodes: Node[] = [];

    constructor(config: Config) {
        this.#initialized = false;
        this.#elementQueries = config.queries;
        this.#debug = ref(false);
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
        this.initializeDebugListener();

        this.#initialized = true;
    }

    get isInitialized() {
        return this.#initialized;
    }

    set debug(value: boolean) {
        this.#debug.value = value;
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
        if (this.#debug.value) {
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

        Shopware.Utils.EventBus.emit('telemetry', new TelemetryEvent<N>(eventType, eventData));
    }

    private isHTMLElement(target: EventTarget | null): target is HTMLElement {
        return target !== null && target instanceof HTMLElement;
    }

    private initializeDebugListener(): void {
        const debugListener = (event: TelemetryEvent<EventTypes>): void => {
            // eslint-disable-next-line no-console
            console.debug('TelemetryEvent', event);
        };

        watch(this.#debug, (newValue) => {
            if (newValue) {
                Shopware.Utils.EventBus.on('telemetry', debugListener);
            } else {
                Shopware.Utils.EventBus.off('telemetry', debugListener);
            }
        });
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
