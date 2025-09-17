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
    callback: (...args: any[]) => any[];
    priority?: number;
}

/**
 * Global Shopware class.
 * 
 * This class is the central point for all component-system related functionality.
 * It is a singleton and can be accessed via `window.Shopware`.
 * 
 * @sw-package framework
 */
class Shopware extends EventEmitter {

    // Singleton instance.
    public static instance: Shopware;

    // Mutation observer to handle added and removed nodes for automatic component initialization.
    private observer: MutationObserver;

    // Registry to store all registered components.
    private componentRegistry: Map<string, typeof ShopwareComponent>;

    // Registry to store all selectors for automatic component initialization.
    private selectorRegistry: Map<string, string>;

    // Registry to store all component instances.
    private instanceRegistry: Array<ComponentRegistryEntry>;

    // Registry to store all interception events.
    private interceptionRegistry: Map<string, InterceptionRegistryEntry[]>;

    // Flag to check if the shopware instance is loaded.
    private loaded: boolean = false;

    constructor() {
        super();

        this.componentRegistry = new Map();
        this.selectorRegistry = new Map();
        this.instanceRegistry = [];

        this.interceptionRegistry = new Map();

        this.observer = new MutationObserver(this.observerCallback.bind(this));
        this.observer.observe(document.body, { childList: true, subtree: true });

        document.addEventListener('DOMContentLoaded', () => {
            this.initializeComponents();
            this.loaded = true;
        });

        // Singleton
        if (!Shopware.instance) {
            Shopware.instance = this;
        }

        return Shopware.instance;
    }

    /**
     * Register a new component.
     *
     * @param componentName - The name of the component.
     * @param component - The component class.
     */
    public registerComponent(componentName: string, component: typeof ShopwareComponent): void {
        if (this.componentRegistry.has(componentName)) {
            console.warn(`Component ${componentName} already registered. Component will be overwritten.`);
        }

        this.componentRegistry.set(componentName, component);

        if (component.selector) {
            this.selectorRegistry.set(component.selector, componentName);
        }

        if (this.loaded) {
            this.initializeComponent(componentName);
        }
    }

    /**
     * Get a component by name by its registered name.
     *
     * @param componentName - The name of the component.
     * @returns The component class.
     */
    public getComponent(componentName: string): typeof ShopwareComponent | undefined {
        return this.componentRegistry.get(componentName);
    }

    /**
     * Unregister a component by its registered name.
     *
     * @param componentName - The name of the component.
     */
    public unregisterComponent(componentName: string): void {
        if (!this.componentRegistry.has(componentName)) {
            console.warn(`Component ${componentName} not found. Component will not be unregistered.`);
            return;
        }

        // Remove component from selector registry.
        const component = this.componentRegistry.get(componentName);
        if (component?.selector) {
            this.selectorRegistry.delete(component.selector);
        }

        // Delete all instances of the component.
        this.instanceRegistry.forEach((instance, index) => {
            if (instance.componentName !== componentName) {
                return;
            }

            instance.component.destroy();
            // Remove instance from registry.
            this.instanceRegistry.splice(index, 1);
        });

        this.componentRegistry.delete(componentName);
    }

    /**
     * Get all component instances by their registered name.
     *
     * @param componentName - The name of the component.
     * @returns The component instances.
     */
    public getComponentInstances(componentName: string): ShopwareComponent[] {
        return this.instanceRegistry.filter(entry => entry.componentName === componentName).map(entry => entry.component);
    }

    /**
     * Get a component instance by its registered name and element.
     *
     * @param componentName - The name of the component.
     * @param element - The element.
     * @returns The component instance.
     */
    public getComponentInstanceByElement(componentName: string, element: Node): ShopwareComponent | undefined {
        const instance = this.instanceRegistry.find(entry => entry.element === element && entry.componentName === componentName);

        if (!instance) {
            console.warn(`Component instance for element ${element} not found.`);
            return;
        }

        return instance.component;
    }

    /**
     * Initialize a component by its registered name.
     *
     * @param componentName - The name of the component.
     */
    public initializeComponent(componentName: string): void {
        const component = this.getComponent(componentName);
        if (!component) {
            console.warn(`Component ${componentName} not found. Component will not be initialized.`);
            return;
        }

        let targetElements: NodeList|Array<Node>;
        if (component.selector) {
            targetElements = document.querySelectorAll(component.selector);
        } else {
            targetElements = [ document ];
        }

        targetElements.forEach(targetEl => {
            this.initializeComponentOnElement(componentName, targetEl);
        });
    }

    /**
     * Initialize a component by its registered name and element.
     *
     * @param componentName - The name of the component.
     * @param element - The element.
     */
    public initializeComponentOnElement(componentName: string, element: Node): void {
        const component = this.getComponent(componentName);
        if (!component) {
            console.warn(`Component ${componentName} not found. Component will not be initialized.`);
            return;
        }

        const existingInstance = this.getComponentInstanceByElement(componentName, element);

        if (existingInstance) {
            return;
        }

        const componentInstance = new component(element, component.options || {}, componentName);
        this.instanceRegistry.push({ element, componentName, component: componentInstance });
    }

    /**
     * Intercept an event by its registered name.
     *
     * @param eventName - The name of the event.
     * @param callback - The callback function.
     * @param priority - The priority of the event.
     */
    public intercept(eventName: string, callback: (...args: any[]) => any[], priority = 0): void {
        if (!this.interceptionRegistry.has(eventName)) {
            this.interceptionRegistry.set(eventName, []);
        }

        this.interceptionRegistry.get(eventName)?.push({ callback, priority });
    }

    /**
     * Emit an interceptable event by its registered name.
     *
     * @param eventName - The name of the event.
     * @param args - The arguments.
     * @returns The arguments.
     */
    public emitInterception(eventName: string, ...args: any[]): any[] {
        const interceptors = this.interceptionRegistry.get(eventName);
        if (!interceptors) {
            return args;
        }

        interceptors.sort((a, b) => (b.priority || 0) - (a.priority || 0));
        interceptors.forEach(interceptor => {
            args = interceptor.callback(...args);
        });

        return args;
    }

    /**
     * Call a method by its name on all component instances by their registered name.
     *
     * @param componentName - The name of the component.
     * @param methodName - The name of the method.
     * @param args - The arguments.
     */
    public callMethod(componentName: string, methodName: string, ...args: any[]): void {
        const componentInstances = this.getComponentInstances(componentName);

        componentInstances.forEach(instance => {
            if (instance[methodName as keyof ShopwareComponent] && 
                typeof instance[methodName as keyof ShopwareComponent] === 'function') {
                (instance[methodName as keyof ShopwareComponent] as Function).call(instance, ...args);
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
    public serializeFormJson(form: HTMLFormElement): Record<string, any> {
        const formData = this.serializeForm(form);
        const json: Record<string, any> = {};

        if (formData instanceof FormData) {
            for (const [key, value] of Array.from(formData.entries())) {
                json[key] = value;
            }
        }

        return json;
    }

    /**
     * Initialize all registered components.
     */
    private initializeComponents(): void {
        this.componentRegistry.forEach((component, componentName) => {
            this.initializeComponent(componentName);
        });
    }

    /**
     * Callback for the mutation observer.
     *
     * @param mutationRecords - The mutation records.
     * @param observer - The observer.
     */
    private observerCallback(mutationRecords: MutationRecord[], observer: MutationObserver): void {
        mutationRecords.forEach(mutationRecord => {
            this.handleAddedNodes(mutationRecord.addedNodes);
            this.handleRemovedNodes(mutationRecord.removedNodes);
        });
    }

    /**
     * Handle added nodes for automatic component initialization.
     *
     * @param addedNodes - The added nodes.
     */
    private handleAddedNodes(addedNodes: NodeList): void {
        Array.from(addedNodes).forEach(node => {
            this.selectorRegistry.forEach((componentName, selector) => {
                if (node instanceof Element && node.matches(selector)) {
                    this.initializeComponentOnElement(componentName, node);
                }
            });
        });
    }

    /**
     * Handle removed nodes for automatic component destruction.
     *
     * @param removedNodes - The removed nodes.
     */
    private handleRemovedNodes(removedNodes: NodeList): void {
        Array.from(removedNodes).forEach(node => {
            this.instanceRegistry.forEach((entry, index) => {
                if (entry.element === node) {
                    entry.component.destroy();
                    this.instanceRegistry.splice(index, 1);
                }
            });
        });
    }
}

window.Shopware = new Shopware();