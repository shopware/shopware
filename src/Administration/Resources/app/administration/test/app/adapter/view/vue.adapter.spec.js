import VueAdapter from 'src/app/adapter/view/vue.adapter';
import ViewAdapter from 'src/core/adapter/view.adapter';

jest.mock('vue-i18n', () => (function MockI18n() {}));
Shopware.Service().register('localeHelper', () => {
    return {
        setLocaleWithId: jest.fn()
    };
});

describe('app/adapter/view/vue.adapter.js', () => {
    let vueAdapter;

    beforeEach(() => {
        // mock vue adapter
        vueAdapter = new VueAdapter({
            getContainer: () => ({
                component: '',
                locale: { getLocaleRegistry: () => [], getLastKnownLocale: () => 'en-GB' }
            })
        });

        // mock localeHelper Service
        Shopware.Service('localeHelper').setLocaleWithId.mockReset();
    });

    it('should be an class', async () => {
        const type = typeof VueAdapter;
        expect(type).toEqual('function');
    });

    it('should extends the view adapter', async () => {
        const isInstanceOfViewAdapter = VueAdapter.prototype instanceof ViewAdapter;
        expect(isInstanceOfViewAdapter).toBeTruthy();
    });

    it('initLocales should call setLocaleFromuser', async () => {
        // Mock function
        vueAdapter.setLocaleFromUser = jest.fn();

        vueAdapter.initLocales({
            subscribe: () => {},
            dispatch: () => {},
            state: { session: { currentLocale: 'en-GB' } }
        });

        expect(vueAdapter.setLocaleFromUser).toHaveBeenCalled();
    });

    it('setLocaleFromUser should not set the user when user does not exists', async () => {
        vueAdapter.setLocaleFromUser({
            state: { session: { currentUser: null } }
        });

        expect(Shopware.Service('localeHelper').setLocaleWithId).not.toHaveBeenCalled();
    });

    it('setLocaleFromUser should set the user when user does not exists', async () => {
        vueAdapter.setLocaleFromUser({
            state: { session: { currentUser: { localeId: '12345' } } }
        });

        expect(Shopware.Service('localeHelper').setLocaleWithId).toHaveBeenCalled();
    });

    it('setLocaleFromUser should call the service with the user id from the store', async () => {
        const expectedId = '12345678';

        vueAdapter.setLocaleFromUser({
            state: { session: { currentUser: { localeId: expectedId } } }
        });

        expect(Shopware.Service('localeHelper').setLocaleWithId).toHaveBeenCalledWith(expectedId);
    });
});
