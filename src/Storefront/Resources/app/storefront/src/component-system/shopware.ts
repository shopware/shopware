import { EventEmitter } from 'events';
import type ShopwareComponent from './component';

declare global {
    interface Window {
        Shopware: Shopware;
    }
}

type ComponentRegistryEntry = {
    element: Node;
    componentName: string;
    component: ShopwareComponent;
}

type InterceptionRegistryEntry = {
    callback: (data: Record<string, unknown>) => Record<string, unknown>;
    priority?: number;
}

/**
 * Global Shopware class.
 *
 * This class is the central point for all component-system related functionality.
 * It is a singleton and can be accessed via `window.Shopware`.
 *
 * @internal
 * @sw-package framework
 */
class Shopware extends EventEmitter {

    // Singleton instance.
    public static instance: Shopware;

    // Mutation observer to handle added and removed nodes for automatic component initialization.
    private observer!: MutationObserver;

    // Registry to store all registered components.
    private componentRegistry: Map<string, typeof ShopwareComponent | null> = new Map();

    // Registry to store all component instances.
    private instanceRegistry: Array<ComponentRegistryEntry> = [];

    // O(1) lookup registry for component instances by element and name.
    private instanceIndexByElement: WeakMap<Node, Map<string, ShopwareComponent>> = new WeakMap();

    // Registry to store all interception events.
    private interceptionRegistry: Map<string, InterceptionRegistryEntry[]> = new Map();

    private readonly onDomContentLoaded = () => {
        void this.initializeComponents();
    };

    constructor() {
        super();

        if (Shopware.instance) {
            return Shopware.instance;
        }

        this.setMaxListeners(50);

        this.observer = new MutationObserver(this.observerCallback.bind(this));
        this.observer.observe(document.body, { childList: true, subtree: true });

        document.addEventListener('DOMContentLoaded', this.onDomContentLoaded);

        Shopware.instance = this;
    }

    /**
     * Get a component by name by its registered name.
     *
     * @param componentName - The name of the component.
     * @returns The component class.
     */
    public async getComponent(componentName: string | null | undefined): Promise<typeof ShopwareComponent | undefined> {
        if (!componentName) {
            return undefined;
        }

        const cachedComponent = this.componentRegistry.get(componentName);
        if (cachedComponent !== undefined) {
            return cachedComponent ?? undefined;
        }

        const componentSpecifier = this.resolveImportMapSpecifier(componentName);

        let component: typeof ShopwareComponent | undefined;
        try {
            /**
             * This import has to be ignored by both bundlers — the component URL
             * is a runtime value resolved via the import map, not a static path.
             */
            const module = await import(/* webpackIgnore: true */ /* @vite-ignore */ componentSpecifier) as { default?: typeof ShopwareComponent };
            component = module.default;
        } catch (error) {
            console.error(`Failed to import component ${componentName}:`, error);
            this.componentRegistry.set(componentName, null);
            return undefined;
        }

        if (!component) {
            this.componentRegistry.set(componentName, null);
            return undefined;
        }

        this.componentRegistry.set(componentName, component);
        return component;
    }

    private resolveImportMapSpecifier(componentName: string): string {
        const importMapScripts = Array.from(document.querySelectorAll('script[type="importmap"]'));

        for (const script of importMapScripts) {
            const mapJson = script.textContent;
            if (!mapJson) {
                continue;
            }

            try {
                const importMap = JSON.parse(mapJson) as { imports?: Record<string, string> };
                const imports = importMap.imports;
                if (!imports) {
                    continue;
                }

                const directMatch = imports[componentName];
                if (directMatch) {
                    return directMatch;
                }

                let prefixMatch: string | undefined;
                for (const key of Object.keys(imports)) {
                    if (!key.endsWith('/')) {
                        continue;
                    }

                    if (!componentName.startsWith(key)) {
                        continue;
                    }

                    if (!prefixMatch || key.length > prefixMatch.length) {
                        prefixMatch = key;
                    }
                }

                if (prefixMatch && imports[prefixMatch]) {
                    return `${imports[prefixMatch]}${componentName.slice(prefixMatch.length)}`;
                }
            } catch {
                continue;
            }
        }

        return componentName;
    }

    /**
     * Get all component instances by their registered name.
     *
     * @param componentName - The name or a regular expression matching the component name.
     * @returns The component instances.
     */
    public getComponentInstances(componentName: string | RegExp): ShopwareComponent[] {
        return this.instanceRegistry.filter(entry => {
            if (componentName instanceof RegExp) {
                return componentName.test(entry.componentName);
            }

            return entry.componentName === componentName;
        }).map(entry => entry.component);
    }

    /**
     * Get a component instance by its registered name and element.
     *
     * @param componentName - The name of the component.
     * @param element - The element.
     * @returns The component instance.
     */
    public getComponentInstanceByElement(componentName: string, element: Node): ShopwareComponent | undefined {
        return this.instanceIndexByElement.get(element)?.get(componentName);
    }

    /**
     * Initialize a component by its registered name.
     *
     * @param componentName - The name of the component.
     */
    public async initializeComponent(componentName: string): Promise<void> {
        const component = await this.getComponent(componentName);

        if (!component) {
            console.warn(`Component ${componentName} not found. Component will not be initialized.`);
            return;
        }

        const selector = `[data-component="${componentName}"]`;
        const targetElements = document.querySelectorAll(selector);

        targetElements.forEach(targetEl => {
            this.initializeComponentOnElement(componentName, component, targetEl as HTMLElement);
        });
    }

    /**
     * Initialize a component by its registered name and element.
     *
     * @param componentName - The name of the component.
     * @param component - The component class.
     * @param element - The element.
     */
    public initializeComponentOnElement(
        componentName: string,
        component: typeof ShopwareComponent,
        element: HTMLElement,
    ): ShopwareComponent | undefined {
        if (!component || !element) {
            return undefined;
        }

        const existingInstance = this.getComponentInstanceByElement(componentName, element);

        if (existingInstance) {
            return existingInstance;
        }

        const componentInstance = new component(element, component.options || {}, componentName);
        this.instanceRegistry.push({ element, componentName, component: componentInstance });
        const elementInstances = this.instanceIndexByElement.get(element) ?? new Map<string, ShopwareComponent>();
        elementInstances.set(componentName, componentInstance);
        this.instanceIndexByElement.set(element, elementInstances);

        return componentInstance;
    }

    /**
     * Emit an event but queue it for execution after the current event loop.
     * Use this for events that, for example, are triggered on direct initialization.
     * It can prevent race conditions.
     *
     * @param eventName - The name of the event.
     * @param args - The event arguments passed via the event.
     */
    public emitQueued(eventName: string, ...args: unknown[]): void {
        window.queueMicrotask(() => {
            this.emit(eventName, ...args);
        });
    }

    /**
     * Intercept an event by its registered name.
     *
     * @param eventName - The name of the event.
     * @param callback - The callback function.
     * @param priority - The priority of the event.
     */
    public intercept(eventName: string, callback: (data: Record<string, unknown>) => Record<string, unknown>, priority = 0): void {
        if (!this.interceptionRegistry.has(eventName)) {
            this.interceptionRegistry.set(eventName, []);
        }

        this.interceptionRegistry.get(eventName)?.push({ callback, priority });
    }

    /**
     * Emit an interceptable event by its registered name.
     *
     * @param eventName - The name of the event.
     * @param data - The event data passed via the event.
     * @returns The arguments.
     */
    public emitInterception(eventName: string, data: Record<string, unknown>): Record<string, unknown> {
        const interceptors = this.interceptionRegistry.get(eventName);
        if (!interceptors) {
            return data;
        }

        interceptors.sort((a, b) => (b.priority || 0) - (a.priority || 0));
        interceptors.forEach(interceptor => {
            data = interceptor.callback(data);
        });

        return data;
    }

    /**
     * Call a method by its name on all component instances by their registered name.
     *
     * @param componentName - The name or a regular expression matching the component name.
     * @param methodName - The name of the method.
     * @param args - The arguments.
     */
    public callMethod(componentName: string | RegExp, methodName: string, ...args: unknown[]): void {
        const componentInstances = this.getComponentInstances(componentName);

        componentInstances.forEach(instance => {
            if (instance[methodName as keyof ShopwareComponent] &&
                typeof instance[methodName as keyof ShopwareComponent] === 'function') {
                (instance[methodName as keyof ShopwareComponent] as (...fnArgs: unknown[]) => void).call(instance, ...args);
            }
        });
    }

    /**
     * Serialize a form to FormData.
     *
     * @param form - The form element.
     * @returns The serialized form.
     */
    public serializeForm(form: HTMLFormElement): FormData {
        if (form.nodeName !== 'FORM') {
            return new FormData();
        }

        return new FormData(form);
    }

    /**
     * Serialize a form to JSON.
     *
     * @param form - The form element.
     * @returns The serialized form.
     */
    public serializeFormJson(form: HTMLFormElement): Record<string, FormDataEntryValue> {
        const formData = this.serializeForm(form);
        const json: Record<string, FormDataEntryValue> = {};

        if (formData instanceof FormData) {
            for (const [key, value] of Array.from(formData.entries())) {
                json[key] = value;
            }
        }

        return json;
    }

    /**
     * Disconnect global listeners and clear all registries.
     * Useful for tests and SPA-style teardown between navigations.
     */
    public disconnect(): void {
        this.observer.disconnect();
        document.removeEventListener('DOMContentLoaded', this.onDomContentLoaded);

        this.instanceRegistry.forEach(entry => {
            entry.component.destroy();
        });

        this.componentRegistry.clear();
        this.instanceRegistry = [];
        this.instanceIndexByElement = new WeakMap();
        this.interceptionRegistry.clear();
        this.removeAllListeners();
    }

    /**
     * Initialize all registered components.
     */
    private async initializeComponents(): Promise<void> {
        const componentElements = Array.from(document.querySelectorAll('[data-component]'));
        const componentQueue = new Map();
        const components = new Map<string, typeof ShopwareComponent>();

        // Create a queue to load all components in parallel.
        for (const element of componentElements) {
            const componentName = element.getAttribute('data-component');
            if (!componentName) {
                continue;
            }

            if (componentQueue.has(componentName)) {
                continue;
            }

            const loadComponent = (async () => {
                const component = await this.getComponent(componentName);
                if (!component) {
                    throw new Error(`Component ${componentName} not found.`);
                }

                components.set(componentName, component);

                return component;
            })();

            componentQueue.set(componentName, loadComponent);
        }

        await Promise.allSettled(Array.from(componentQueue.values()));

        for (const element of componentElements) {
            const componentName = element.getAttribute('data-component');
            if (!componentName) {
                continue;
            }

            const component = components.get(componentName);
            if (!component) {
                continue;
            }

            this.initializeComponentOnElement(componentName, component, element as HTMLElement);
        }

        this.emitQueued('Components:Initialized');
    }

    /**
     * Callback for the mutation observer.
     *
     * @param mutationRecords - The mutation records.
     * @param observer - The observer.
     */
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    private observerCallback(mutationRecords: MutationRecord[], observer: MutationObserver): void {
        mutationRecords.forEach(mutationRecord => {
            void this.handleAddedNodes(mutationRecord.addedNodes);
            this.handleRemovedNodes(mutationRecord.removedNodes);
        });
    }

    /**
     * Handle added nodes for automatic component initialization.
     *
     * @param addedNodes - The added nodes.
     */
    private async handleAddedNodes(addedNodes: NodeList): Promise<void> {
        const elements = Array.from(addedNodes);

        for (const element of elements) {
            if (!(element instanceof HTMLElement)) {
                continue;
            }

            const componentName = element.getAttribute('data-component');
            const component = await this.getComponent(componentName);

            if (componentName && component) {
                this.initializeComponentOnElement(componentName, component, element);
            }

            /**
             * MutationObserver only triggers for direct children of the added nodes.
             * For nested elements, we need to handle them recursively.
             */
            if (element.childNodes && element.childNodes.length > 0) {
                void this.handleAddedNodes(element.childNodes);
            }
        }
    }

    /**
     * Handle removed nodes for automatic component destruction.
     *
     * @param removedNodes - The removed nodes.
     */
    private handleRemovedNodes(removedNodes: NodeList): void {
        const elements = Array.from(removedNodes);

        for (const node of elements) {
            const componentInstances = this.instanceIndexByElement.get(node);
            if (componentInstances) {
                componentInstances.forEach(component => {
                    component.destroy();
                });

                this.instanceRegistry = this.instanceRegistry.filter(entry => entry.element !== node);
                this.instanceIndexByElement.delete(node);
            }

            if (node.childNodes && node.childNodes.length > 0) {
                this.handleRemovedNodes(node.childNodes);
            }
        }
    }
}

const shopware: Shopware = new Shopware();
window.Shopware = shopware;
export { shopware as Shopware };