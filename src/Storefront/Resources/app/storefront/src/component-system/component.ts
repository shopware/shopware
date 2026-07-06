declare global {
    interface Window {
        ShopwareComponent: typeof ShopwareComponent;
    }
}

interface EventOptions {
    cancelable?: boolean;
    bubbles?: boolean;
    composed?: boolean;
}

/**
 * Abstract base class for all components.
 *
 * This class is the base class for all components.
 * It is an abstract class and cannot be instantiated directly.
 * It is used to create new components.
 *
 * @internal
 * @sw-package framework
 */
class ShopwareComponent {

    // Components can define default options which will be merged with the options passed to the constructor.
    public static options: Record<string, unknown>;

    // The element in which the component is initialized.
    public el: HTMLElement;

    // The name of the component.
    public componentName: string;

    // The merged component options.
    public options: Record<string, unknown>;

    // The mutation observer to observe the element for content and attribute changes.
    private observer: MutationObserver;

    // The default settings for the mutation observer.
    private observerSettings = { childList: false, subtree: false, attributes: false };

    constructor(
        element: HTMLElement,
        options: Record<string, unknown> = {},
        componentName: string = '',
    ) {
        if (!(element instanceof HTMLElement)) {
            throw new Error('Provided element is not a valid HTMLElement.');
        }

        this.el = element;
        this.componentName = componentName;
        this.options = this.mergeOptions(options);

        this.observer = new MutationObserver(this.observerCallback.bind(this));

        this.initializeComponent();
    }

    /**
     * Initialize the component.
     */
    private initializeComponent(): void {
        this.init();
    }

    /**
     * Initialize the mutation observer.
     * This method can optionally be called from the component instance,
     * to determine if the observer should be initialized.
     *
     * @param observerSettings - The settings for the mutation observer.
     */
    private initializeObserver(observerSettings: { childList?: boolean, subtree?: boolean, attributes?: boolean }): void {
        this.observerSettings = { ...this.observerSettings, ...observerSettings };

        if (this.observerSettings.childList || this.observerSettings.attributes) {
            this.observer.observe(this.el, this.observerSettings);
        }
    }

    /**
     * Handles mutations changes in the element.
     *
     * @param mutationRecords
     * @param observer
     */
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    private observerCallback(mutationRecords: MutationRecord[], observer: MutationObserver): void {
        mutationRecords.forEach(mutationRecord => {
            if (mutationRecord.type === 'childList' && this.observerSettings.childList) {
                this.onContentUpdate(mutationRecord);
            }
            if (mutationRecord.type === 'attributes' && this.observerSettings.attributes) {
                this.onAttributeUpdate(mutationRecord);
            }
        });
    }

    /**
     * Merges the passed options with the options from the data attribute.
     *
     * @param options
     * @returns
     */
    private mergeOptions(options: Record<string, unknown>): Record<string, unknown> {
        if (!(this.el instanceof HTMLElement)) {
            return options;
        }

        const dataAttributeOptions = this.getOptionsFromDataAttribute();

        return { ...options, ...dataAttributeOptions };
    }

    /**
     * Reads options from the data attribute.
     *
     * @returns The options from the data attribute.
     */
    private getOptionsFromDataAttribute(): Record<string, unknown> {
        let dataAttributeOptions = {};

        if (!(this.el instanceof HTMLElement)) {
            return dataAttributeOptions;
        }

        const optionsAttribute = this.el.getAttribute('data-component-options');

        if (optionsAttribute) {
            try {
                dataAttributeOptions = JSON.parse(optionsAttribute) as Record<string, unknown>;
            } catch (error) {
                console.error('The data attribute "data-component-options" could not be parsed to json.');
            }
        }

        return dataAttributeOptions;
    }

    /**
     * Initializes the component.
     * This method should be overridden by the component.
     */
    init(): void {
        console.warn('Init method has to be implemented.');
    }

    /**
     * Destroys the component.
     * This method can optionally be overridden by the component.
     * Should be used to clean up the component.
     */
    destroy() {}

    /**
     * Helper method to dispatch custom events on the main component element.
     */
    dispatchEvent(
        eventName: string,
        detail: Record<string, unknown>,
        options: EventOptions = { cancelable: true, bubbles: true, composed: false },
    ): void {
        this.el.dispatchEvent(new CustomEvent(eventName, {
            detail,
            ...options,
        }));
    }

    /**
     * Helper method to debounce a function.
     * Use it for heavy events like user input, resize, scroll, etc.
     */
    debounce(callback: (...args: unknown[]) => void, delay = 400, immediate = false) {
        let timeout: number | undefined;

        return (...args: unknown[]) => {
            const callNow = immediate && timeout === undefined;

            if (timeout !== undefined) {
                window.clearTimeout(timeout);
            }

            timeout = window.setTimeout(() => {
                timeout = undefined;

                if (!immediate) {
                    callback(...args);
                }
            }, delay);

            if (callNow) {
                callback(...args);
            }
        };
    }

    /**
     * Reacts to content changes.
     * This method can optionally be overridden by the component.
     *
     * @param mutationRecord - The mutation record.
     */
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    onContentUpdate(mutationRecord: MutationRecord): void {}

    /**
     * Reacts to attribute changes.
     * This method can optionally be overridden by the component.
     *
     * @param mutationRecord - The mutation record.
     */
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    onAttributeUpdate(mutationRecord: MutationRecord): void {}
}

window.ShopwareComponent = ShopwareComponent;

export default ShopwareComponent;