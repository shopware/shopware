declare global {
    interface Window {
        ShopwareComponent: typeof ShopwareComponent;
    }
}

/**
 * Abstract base class for all components.
 * 
 * This class is the base class for all components.
 * It is an abstract class and cannot be instantiated directly.
 * It is used to create new components.
 * 
 * @sw-package framework
 */
class ShopwareComponent {

    // Components can define a selector to be used for automatic component initialization.
    public static selector: string|null = null;

    // Components can define default options which will be merged with the options passed to the constructor.
    public static options: Record<string, unknown>;

    // The element in which the component is initialized.
    public el: Node;

    // The name of the component.
    public componentName: string;

    // The merged component options.
    public options: Record<string, unknown>;

    // The mutation observer to observe the element for content and attribute changes.
    private observer: MutationObserver;

    // The default settings for the mutation observer.
    private observerSettings = { childList: false, subtree: false, attributes: false }

    constructor(
        element: Node, 
        options: Record<string, unknown> = {},
        componentName: string = '',
    ) {
        if (!(element instanceof Node)) {
            throw('Provided element is not a valid node.');
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

        const attributeName = this.toDashCase(this.componentName);
        const optionsAttribute = this.el.getAttribute(`data-${attributeName}-options`);

        if (optionsAttribute) {
            try {
                dataAttributeOptions = JSON.parse(optionsAttribute);
            } catch (error) {
                console.error(`The data attribute "data-${attributeName}-options" could not be parsed to json.`);
            }
        }

        return dataAttributeOptions;
    }

    /**
     * Converts a string to dash case.
     * 
     * @param string - The string to convert.
     * @returns The dash case string.
     */
    private toDashCase(string: string): string {
        return string.replace(/([A-Z])/g, '-$1').replace(/^-/, '').toLowerCase();
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
     * Reacts to content changes.
     * This method can optionally be overridden by the component.
     * 
     * @param mutationRecord - The mutation record.
     */
    onContentUpdate(mutationRecord: MutationRecord): void {}

    /**
     * Reacts to attribute changes.
     * This method can optionally be overridden by the component.
     * 
     * @param mutationRecord - The mutation record.
     */
    onAttributeUpdate(mutationRecord: MutationRecord): void {}
}

window.ShopwareComponent = ShopwareComponent;

export default ShopwareComponent;