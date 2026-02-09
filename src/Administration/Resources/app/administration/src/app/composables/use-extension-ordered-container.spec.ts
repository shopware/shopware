/**
 * @sw-package framework
 */

import { nextTick } from 'vue';

import {
    useExtensionOrderedArray,
    useExtensionOrdereredArrayMap,
} from './use-extension-ordered-container';


async function setCurrentExtension(extensionId: string | null): Promise<void> {
    const store = Shopware.Store.get('extensionContext');
    const value = extensionId === null ? null : { id: extensionId };
    store._setCurrentExtensionContext(value);
    await nextTick();
    await flushPromises();
}

describe('use-extension-ordered-container', () => {
    let eventBusListeners: Array<{ event: string; callback: (payload: unknown) => void }> = [];

    beforeAll(() => {
        Shopware.Utils.EventBus.emit = jest.fn((event: string, payload?: unknown) => {
            eventBusListeners
                .filter((l) => l.event === event)
                .forEach((l) => l.callback(payload));
        });
        const originalOn = Shopware.Utils.EventBus.on?.bind(Shopware.Utils.EventBus) ?? (() => {});
        (jest.spyOn(Shopware.Utils.EventBus, 'on') as jest.SpyInstance).mockImplementation(
            (event: string, callback: (payload: unknown) => void) => {
                eventBusListeners.push({ event, callback });
                return (originalOn as (e: string, c: (payload: unknown) => void) => void)(event, callback);
            },
        );
    });

    afterAll(() => {
        jest.restoreAllMocks();
    });

    beforeEach(async () => {
        await setCurrentExtension(null);
        eventBusListeners = [];
    });

    describe('useExtensionOrderedArray', () => {
        describe('initial state', () => {
            it('returns empty items initially', () => {
                const { items } = useExtensionOrderedArray<string>();

                expect(items.value).toEqual([]);
            });

            it('registers sw-extension-loaded listener on creation', () => {
                useExtensionOrderedArray<string>();
                const listeners = eventBusListeners.filter((l) => l.event === 'sw-extension-loaded');

                expect(listeners).toHaveLength(1);
            });
        });

        describe('push', () => {
            it('pushes a value when in core context (extensionId null)', async () => {
                const { items, push } = useExtensionOrderedArray<string>();
                await setCurrentExtension(null);

                push('core-item');

                expect(items.value).toEqual(['core-item']);
            });

            it('pushes multiple values in same extension context and preserves order', async () => {
                const { items, push } = useExtensionOrderedArray<string>();
                await setCurrentExtension('https://ext-a.example');

                push('a1');
                push('a2');
                push('a3');

                expect(items.value).toEqual(['a1', 'a2', 'a3']);
            });

            it('preserves extension order: segments in first-push order, new items append to segment', async () => {
                const { items, push } = useExtensionOrderedArray<string>();

                await setCurrentExtension(null);
                push('core-1');
                push('core-2');

                await setCurrentExtension('https://ext-a.example');
                push('a1');

                await setCurrentExtension(null);
                push('core-3');

                await setCurrentExtension('https://ext-b.example');
                push('b1');
                push('b2');

                await setCurrentExtension('https://ext-a.example');
                push('a2');

                expect(items.value).toEqual([
                    'core-1',
                    'core-2',
                    'core-3',
                    'a1',
                    'a2',
                    'b1',
                    'b2',
                ]);
            });
        });

        describe('removeFirstWhere', () => {
            it('does nothing when predicate matches no item', async () => {
                const { items, push, removeFirstWhere } = useExtensionOrderedArray<string>();
                await setCurrentExtension('ext-a');
                push('a1');
                push('a2');

                removeFirstWhere((x) => x === 'not-there');

                expect(items.value).toEqual(['a1', 'a2']);
            });

            it('removes first matching item by predicate and only the first match', async () => {
                const { items, push, removeFirstWhere } = useExtensionOrderedArray<{
                    id: string;
                    value: string;
                }>();
                await setCurrentExtension('ext-a');
                push({ id: '1', value: 'other' });
                push({ id: '2', value: 'same' });
                push({ id: '3', value: 'same' });

                removeFirstWhere((item) => item.value === 'same');

                expect(items.value).toEqual([
                    { id: '1', value: 'other' },
                    { id: '3', value: 'same' },
                ]);
            });

            it('removes from correct extension segment and keeps others intact', async () => {
                const { items, push, removeFirstWhere } = useExtensionOrderedArray<string>();
                await setCurrentExtension('ext-a');
                push('a1');
                push('a2');
                await setCurrentExtension('ext-b');
                push('b1');
                await setCurrentExtension('ext-a');
                push('a3');

                removeFirstWhere((x) => x === 'a2');

                // a3 is in the same ext-a segment as a1 (append to segment), so order is a1, a3, b1
                expect(items.value).toEqual(['a1', 'a3', 'b1']);
            });

            it('push after removeFirstWhere does not corrupt order or counts', async () => {
                const { items, push, removeFirstWhere } = useExtensionOrderedArray<string>();

                await setCurrentExtension('ext-a');
                push('a1');
                push('a2');
                await setCurrentExtension('ext-b');
                push('b1');

                removeFirstWhere((x) => x === 'a2');

                await setCurrentExtension('ext-a');
                push('a3');
                await setCurrentExtension('ext-b');
                push('b2');

                // Segment order: ext-a (a1, a3) then ext-b (b1, b2)
                expect(items.value).toEqual(['a1', 'a3', 'b1', 'b2']);
            });
        });

        describe('clear', () => {
            it('empties items and order', async () => {
                const { items, push, clear } = useExtensionOrderedArray<string>();
                await setCurrentExtension('ext-a');
                push('a1');
                push('a2');

                clear();

                expect(items.value).toEqual([]);
            });

            it('allows pushing again after clear', async () => {
                const { items, push, clear } = useExtensionOrderedArray<string>();
                await setCurrentExtension('ext-a');
                push('a1');
                clear();

                push('a2');

                expect(items.value).toEqual(['a2']);
            });
        });

        describe('sw-extension-loaded event', () => {
            it('registers a listener that can be invoked with event payload { src }', async () => {
                const { items, push } = useExtensionOrderedArray<string>();

                await setCurrentExtension('https://ext-a.example');
                push('a1');
                push('a2');

                const listeners = eventBusListeners.filter((l) => l.event === 'sw-extension-loaded');
                expect(listeners).toHaveLength(1);

                expect(() => {
                    listeners.forEach((l) => l.callback({ src: 'https://ext-a.example' }));
                }).not.toThrow();

                expect(items.value).toBeDefined();
            });

            it('when EventBus.emit is called with sw-extension-loaded, registered listeners are invoked', () => {
                const testCallback = jest.fn();
                eventBusListeners.push({
                    event: 'sw-extension-loaded',
                    callback: testCallback,
                });

                Shopware.Utils.EventBus.emit('sw-extension-loaded', {
                    src: 'https://example.com',
                });

                expect(testCallback).toHaveBeenCalledTimes(1);
                expect(testCallback).toHaveBeenCalledWith({ src: 'https://example.com' });
            });
        });

        describe('items reactivity', () => {
            it('items is a computed that reflects current array state', async () => {
                const { items, push, clear } = useExtensionOrderedArray<string>();

                expect(items.value).toEqual([]);

                await setCurrentExtension('ext-a');
                push('x');
                expect(items.value).toEqual(['x']);

                clear();
                expect(items.value).toEqual([]);
            });

            it('items is readonly and mutation throws', async () => {
                const { items, push } = useExtensionOrderedArray<string>();
                await setCurrentExtension('ext-a');
                push('a');

                expect(() => {
                    (items.value as string[]).push('b');
                }).toThrow();
                expect(() => {
                    (items.value as string[])[0] = 'x';
                }).toThrow();
            });
        });
    });

    describe('useExtensionOrdereredArrayMap', () => {
        beforeEach(async () => {
            await setCurrentExtension(null);
        });

        describe('get', () => {
            it('returns same container instance for same key', () => {
                const { get } = useExtensionOrdereredArrayMap<string>();

                const container1 = get('position-1');
                const container2 = get('position-1');

                expect(container1).toBe(container2);
            });

            it('returns different container instances for different keys', () => {
                const { get } = useExtensionOrdereredArrayMap<string>();

                const containerA = get('position-a');
                const containerB = get('position-b');

                expect(containerA).not.toBe(containerB);
            });

            it('creates new container on first access with empty items', () => {
                const { get } = useExtensionOrdereredArrayMap<string>();

                const container = get('new-key');
                expect(container.items.value).toEqual([]);
            });

            it('each key has independent push/remove state', async () => {
                const { get } = useExtensionOrdereredArrayMap<string>();

                await setCurrentExtension('ext-a');
                const pos1 = get('position-1');
                pos1.push('p1-a');
                const pos2 = get('position-2');
                pos2.push('p2-a');

                expect(pos1.items.value).toEqual(['p1-a']);
                expect(pos2.items.value).toEqual(['p2-a']);
            });
        });

        describe('items', () => {
            it('exposes readonly map of key -> items computed per container', async () => {
                const { get, items } = useExtensionOrdereredArrayMap<string>();

                await setCurrentExtension('ext-a');
                get('key1').push('a');
                get('key1').push('b');
                get('key2').push('c');

                expect(items.value.key1).toBeDefined();
                expect(items.value.key2).toBeDefined();
                expect(items.value.key1.value).toEqual(['a', 'b']);
                expect(items.value.key2.value).toEqual(['c']);
            });

            it('items map updates when new key is accessed via get', () => {
                const { get, items } = useExtensionOrdereredArrayMap<string>();

                expect(Object.keys(items.value)).toEqual([]);

                get('newKey');
                expect(Object.keys(items.value)).toContain('newKey');
                expect(items.value.newKey.value).toEqual([]);
            });

            it('items map is readonly and mutation throws', async () => {
                const { get, items } = useExtensionOrdereredArrayMap<string>();
                await setCurrentExtension('ext-a');
                get('key1').push('a');

                expect(() => {
                    (items.value as Record<string, unknown>)['newKey'] = {};
                }).toThrow();
                expect(() => {
                    delete (items.value as Record<string, unknown>)['key1'];
                }).toThrow();

                const key1Array = items.value.key1.value as string[];
                expect(() => {
                    key1Array.push('b');
                }).toThrow();
            });
        });

        describe('clear', () => {
            it('clears the map so get returns new containers', async () => {
                const { get, clear } = useExtensionOrdereredArrayMap<string>();

                await setCurrentExtension('ext-a');
                const before = get('key1');
                before.push('x');
                clear();
                const after = get('key1');

                expect(before).not.toBe(after);
                expect(after.items.value).toEqual([]);
            });

            it('after clear, items no longer contains previous keys', async () => {
                const { get, items, clear } = useExtensionOrdereredArrayMap<string>();

                await setCurrentExtension('ext-a');
                get('key1').push('a');
                get('key2').push('b');
                expect(Object.keys(items.value)).toContain('key1');
                expect(Object.keys(items.value)).toContain('key2');

                clear();
                expect(Object.keys(items.value)).toEqual([]);
            });
        });
    });
});
