import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import ShopwareComponent from './component';
import { Shopware } from './shopware';

class LifecycleTestComponent extends ShopwareComponent {
    public static initCount = 0;

    public static destroyCount = 0;

    init(): void {
        LifecycleTestComponent.initCount += 1;
    }

    destroy(): void {
        LifecycleTestComponent.destroyCount += 1;
    }

    ping(value: string): string {
        return value;
    }
}

describe('Shopware runtime component lifecycle', () => {
    afterEach(() => {
        vi.restoreAllMocks();
    });

    beforeEach(() => {
        document.body.innerHTML = '';
        LifecycleTestComponent.initCount = 0;
        LifecycleTestComponent.destroyCount = 0;

        const mutableShopware = Shopware as unknown as {
            componentRegistry: Map<string, typeof ShopwareComponent>;
            instanceRegistry: unknown[];
            interceptionRegistry: Map<string, unknown[]>;
        };

        mutableShopware.componentRegistry = new Map();
        mutableShopware.instanceRegistry = [];
        mutableShopware.interceptionRegistry = new Map();
    });

    it('prevents duplicate initialization on the same element', () => {
        const componentName = 'Sw:Lifecycle:Duplicate';
        const element = document.createElement('div');

        const first = Shopware.initializeComponentOnElement(componentName, LifecycleTestComponent, element);
        const second = Shopware.initializeComponentOnElement(componentName, LifecycleTestComponent, element);

        expect(first).toBe(second);
        expect(LifecycleTestComponent.initCount).toBe(1);
        expect(Shopware.getComponentInstances(componentName)).toHaveLength(1);
    });

    it('initializes and destroys nested components recursively', async () => {
        const componentName = 'Sw:Lifecycle:Nested';
        const root = document.createElement('div');
        root.setAttribute('data-component', componentName);
        const child = document.createElement('div');
        child.setAttribute('data-component', componentName);
        root.appendChild(child);

        const host = document.createElement('div');
        host.appendChild(root);

        const mutableShopware = Shopware as unknown as {
            componentRegistry: Map<string, typeof ShopwareComponent>;
            handleAddedNodes(nodes: NodeList): Promise<void>;
            handleRemovedNodes(nodes: NodeList): void;
        };

        mutableShopware.componentRegistry.set(componentName, LifecycleTestComponent);
        await mutableShopware.handleAddedNodes(host.childNodes);

        expect(Shopware.getComponentInstances(componentName)).toHaveLength(2);

        mutableShopware.handleRemovedNodes(host.childNodes);
        expect(LifecycleTestComponent.destroyCount).toBe(2);
        expect(Shopware.getComponentInstances(componentName)).toHaveLength(0);
    });

    it('returns undefined and logs errors when component import fails', async () => {
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        const component = await Shopware.getComponent('non-existing-component-specifier');

        expect(component).toBeUndefined();
        expect(errorSpy).toHaveBeenCalledOnce();
    });

    it('runs interceptors in descending priority order', () => {
        Shopware.intercept('runtime:interceptor', (payload) => ({ ...payload, order: 'low' }), 1);
        Shopware.intercept('runtime:interceptor', (payload) => ({ ...payload, order: 'high' }), 20);

        const result = Shopware.emitInterception('runtime:interceptor', { order: 'initial' });

        expect(result).toEqual({ order: 'low' });
    });

    it('safely ignores callMethod invocations for missing methods', () => {
        const componentName = 'Sw:Lifecycle:Methods';
        const element = document.createElement('div');
        Shopware.initializeComponentOnElement(componentName, LifecycleTestComponent, element);

        expect(() => Shopware.callMethod(componentName, 'doesNotExist', 'value')).not.toThrow();
        expect(() => Shopware.callMethod(componentName, 'ping', 'pong')).not.toThrow();
    });
});
