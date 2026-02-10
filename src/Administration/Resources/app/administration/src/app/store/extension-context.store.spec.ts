/**
 * @sw-package framework
 */

import type { ExtensionContextStore } from './extension-context.store';
import { useCurrentExtensionId } from './extension-context.store';

describe('extension-context.store', () => {
    let store: ExtensionContextStore;

    beforeEach(() => {
        store = Shopware.Store.get('extensionContext');
        store._setCurrentExtensionContext(null);
    });

    describe('initial state', () => {
        it('has null currentExtensionContext initially', () => {
            expect(store.currentExtensionContext).toBeNull();
        });

        it('useCurrentExtensionId returns computed with null when context is null', () => {
            const id = useCurrentExtensionId();
            expect(id.value).toBeNull();
        });
    });

    describe('_setCurrentExtensionContext', () => {
        it('sets currentExtensionContext to the given context', () => {
            const context = { id: 'https://ext.example.com' };
            store._setCurrentExtensionContext(context);

            expect(store.currentExtensionContext).toEqual(context);
        });

        it('sets currentExtensionContext to null', () => {
            store._setCurrentExtensionContext({ id: 'https://ext.example.com' });
            store._setCurrentExtensionContext(null);

            expect(store.currentExtensionContext).toBeNull();
        });

        it('useCurrentExtensionId reflects the set context', () => {
            const id = useCurrentExtensionId();
            expect(id.value).toBeNull();

            store._setCurrentExtensionContext({ id: 'https://ext-a.example' });
            expect(id.value).toBe('https://ext-a.example');

            store._setCurrentExtensionContext({ id: 'https://ext-b.example' });
            expect(id.value).toBe('https://ext-b.example');

            store._setCurrentExtensionContext(null);
            expect(id.value).toBeNull();
        });
    });

    describe('wrapWithExtensionContext', () => {
        it('sets context during callback and restores previous value after', () => {
            expect(store.currentExtensionContext).toBeNull();

            const context = { id: 'https://ext.example.com' };
            let seenInCallback: typeof store.currentExtensionContext = null;
            store.wrapWithExtensionContext(context, () => {
                seenInCallback = store.currentExtensionContext;
                return undefined;
            });

            expect(seenInCallback).toEqual(context);
            expect(store.currentExtensionContext).toBeNull();
        });

        it('restores previous context when nested', () => {
            const outer = { id: 'https://outer.example' };
            const inner = { id: 'https://inner.example' };

            store.wrapWithExtensionContext(outer, () => {
                expect(store.currentExtensionContext).toEqual(outer);
                store.wrapWithExtensionContext(inner, () => {
                    expect(store.currentExtensionContext).toEqual(inner);
                    return undefined;
                });
                expect(store.currentExtensionContext).toEqual(outer);
                return undefined;
            });
            expect(store.currentExtensionContext).toBeNull();
        });

        it('restores context when callback throws', () => {
            const context = { id: 'https://ext.example.com' };
            store._setCurrentExtensionContext(null);

            expect(() => {
                store.wrapWithExtensionContext(context, () => {
                    expect(store.currentExtensionContext).toEqual(context);
                    throw new Error('callback error');
                });
            }).toThrow('callback error');

            expect(store.currentExtensionContext).toBeNull();
        });

        it('returns the return value of the callback', () => {
            const result = store.wrapWithExtensionContext({ id: 'https://ext.example.com' }, () => 42);
            expect(result).toBe(42);
        });

        it('useCurrentExtensionId sees context only during callback', () => {
            const id = useCurrentExtensionId();
            expect(id.value).toBeNull();

            let idInCallback: string | null = null;
            store.wrapWithExtensionContext({ id: 'https://ext.example.com' }, () => {
                idInCallback = id.value;
                return undefined;
            });

            expect(idInCallback).toBe('https://ext.example.com');
            expect(id.value).toBeNull();
        });
    });

    describe('useCurrentExtensionId', () => {
        it('computed updates when store context changes', () => {
            const id = useCurrentExtensionId();
            store._setCurrentExtensionContext({ id: 'https://first.example' });
            expect(id.value).toBe('https://first.example');
            store._setCurrentExtensionContext({ id: 'https://second.example' });
            expect(id.value).toBe('https://second.example');
        });
    });

    describe('registerExtensionHref / getExtensionHref', () => {
        it('returns undefined when origin was not registered', () => {
            expect(store.getExtensionHref('https://unknown.example')).toBeUndefined();
        });

        it('returns registered href for origin', () => {
            store.registerExtensionHref('https://ext.example.com', 'https://ext.example.com/app/#/detail/1');
            expect(store.getExtensionHref('https://ext.example.com')).toBe(
                'https://ext.example.com/app/#/detail/1',
            );
        });

        it('overwrites href when same origin is registered again', () => {
            store.registerExtensionHref('https://ext.example.com', 'https://ext.example.com/');
            store.registerExtensionHref('https://ext.example.com', 'https://ext.example.com/app/#/new');
            expect(store.getExtensionHref('https://ext.example.com')).toBe('https://ext.example.com/app/#/new');
        });
    });
});
