import useSystem from './use-system';

describe('use-system', () => {
    it('has initial state', () => {
        const store = useSystem();
        expect(store.locales.value).toEqual([]);
    });

    it('can register admin locale', () => {
        const store = useSystem();
        store.registerAdminLocale('en-GB');
        expect(store.locales.value).toEqual(['en-GB']);
    });

    it('can register admin locale only once', () => {
        const store = useSystem();
        store.registerAdminLocale('en-GB');
        store.registerAdminLocale('en-GB');
        expect(store.locales.value).toEqual(['en-GB']);
    });
});
