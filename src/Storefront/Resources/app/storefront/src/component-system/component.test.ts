import { afterEach, describe, expect, it, vi } from 'vitest';
import ShopwareComponent from './component';

class TestComponent extends ShopwareComponent {
    public static initCalls = 0;

    init(): void {
        TestComponent.initCalls += 1;
    }
}

class ObserverTestComponent extends ShopwareComponent {
    public contentUpdates = 0;

    public attributeUpdates = 0;

    override onContentUpdate(): void {
        this.contentUpdates += 1;
    }

    override onAttributeUpdate(): void {
        this.attributeUpdates += 1;
    }
}

describe('ShopwareComponent', () => {
    afterEach(() => {
        TestComponent.initCalls = 0;
        vi.restoreAllMocks();
        vi.useRealTimers();
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

    it('debounce with immediate=false calls once after delay', () => {
        vi.useFakeTimers();
        const component = new TestComponent(document.createElement('div'), {}, 'Sw:Debounce');
        const callback = vi.fn();
        const debounced = component.debounce(callback, 100);

        debounced('first');
        debounced('second');

        expect(callback).not.toHaveBeenCalled();
        vi.advanceTimersByTime(100);
        expect(callback).toHaveBeenCalledOnce();
        expect(callback).toHaveBeenCalledWith('second');
    });

    it('debounce with immediate=true does not schedule trailing call', () => {
        vi.useFakeTimers();
        const component = new TestComponent(document.createElement('div'), {}, 'Sw:Debounce');
        const callback = vi.fn();
        const debounced = component.debounce(callback, 100, true);

        debounced('first');
        vi.advanceTimersByTime(100);

        expect(callback).toHaveBeenCalledOnce();
        expect(callback).toHaveBeenCalledWith('first');
    });

    it('debounce with immediate=true can be called again after wait', () => {
        vi.useFakeTimers();
        const component = new TestComponent(document.createElement('div'), {}, 'Sw:Debounce');
        const callback = vi.fn();
        const debounced = component.debounce(callback, 100, true);

        debounced('first');
        vi.advanceTimersByTime(100);
        debounced('second');

        expect(callback).toHaveBeenCalledTimes(2);
        expect(callback).toHaveBeenNthCalledWith(1, 'first');
        expect(callback).toHaveBeenNthCalledWith(2, 'second');
    });

    it('routes childList mutations to onContentUpdate when observer is enabled', () => {
        const component = new ObserverTestComponent(document.createElement('div'));
        const mutableComponent = component as unknown as {
            initializeObserver(settings: { childList?: boolean; subtree?: boolean; attributes?: boolean }): void;
            observerCallback(records: MutationRecord[], observer: MutationObserver): void;
        };

        mutableComponent.initializeObserver({ childList: true });
        mutableComponent.observerCallback(
            [{ type: 'childList' } as MutationRecord],
            {} as MutationObserver,
        );

        expect(component.contentUpdates).toBe(1);
        expect(component.attributeUpdates).toBe(0);
    });

    it('routes attribute mutations to onAttributeUpdate when observer is enabled', () => {
        const component = new ObserverTestComponent(document.createElement('div'));
        const mutableComponent = component as unknown as {
            initializeObserver(settings: { childList?: boolean; subtree?: boolean; attributes?: boolean }): void;
            observerCallback(records: MutationRecord[], observer: MutationObserver): void;
        };

        mutableComponent.initializeObserver({ attributes: true });
        mutableComponent.observerCallback(
            [{ type: 'attributes' } as MutationRecord],
            {} as MutationObserver,
        );

        expect(component.attributeUpdates).toBe(1);
        expect(component.contentUpdates).toBe(0);
    });
});
