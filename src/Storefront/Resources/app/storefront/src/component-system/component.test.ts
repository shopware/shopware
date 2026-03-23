import { afterEach, describe, expect, it, vi } from 'vitest';
import ShopwareComponent from './component';

class TestComponent extends ShopwareComponent {
    public static initCalls = 0;

    init(): void {
        TestComponent.initCalls += 1;
    }
}

describe('ShopwareComponent', () => {
    afterEach(() => {
        TestComponent.initCalls = 0;
        vi.restoreAllMocks();
    });

    it('merges constructor options with data attribute options', () => {
        const element = document.createElement('div');
        element.setAttribute('data-component-options', JSON.stringify({ fromAttribute: true, override: 'attribute' }));

        const component = new TestComponent(element, { fromConstructor: true, override: 'constructor' }, 'Sw:Test');

        expect(TestComponent.initCalls).toBe(1);
        expect(component.options).toEqual({
            fromConstructor: true,
            fromAttribute: true,
            override: 'attribute',
        });
    });

    it('falls back to constructor options when data attribute is invalid json', () => {
        const element = document.createElement('div');
        element.setAttribute('data-component-options', '{invalid');
        const errorSpy = vi.spyOn(console, 'error').mockImplementation(() => {});

        const component = new TestComponent(element, { fallback: true }, 'Sw:Test');

        expect(component.options).toEqual({ fallback: true });
        expect(errorSpy).toHaveBeenCalledOnce();
    });

    it('dispatches custom events with payload', () => {
        const element = document.createElement('div');
        const component = new TestComponent(element, {}, 'Sw:Dispatch');
        const listener = vi.fn();

        element.addEventListener('sw:test-event', listener);
        component.dispatchEvent('sw:test-event', { value: 42 });

        expect(listener).toHaveBeenCalledOnce();
        const customEvent = listener.mock.calls[0]?.[0] as CustomEvent;
        expect(customEvent.detail).toEqual({ value: 42 });
    });

    it('throws when constructed with an invalid element', () => {
        expect(() => new TestComponent({} as HTMLElement)).toThrow('Provided element is not a valid HTMLElement.');
    });
});
