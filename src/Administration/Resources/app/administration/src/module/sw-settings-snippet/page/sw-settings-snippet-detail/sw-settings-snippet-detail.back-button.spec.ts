/**
 * @sw-package fundamentals@discovery
 */
import { shallowMount, type VueWrapper } from '@vue/test-utils';

const swPageStub = {
    name: 'sw-page',
    props: ['backRoute', 'backLabel'],
    template: '<div></div>',
};

const snippetSets = new Map([
    ['de-set-id', { id: 'de-set-id', name: 'BASE de-DE' }],
    ['en-set-id', { id: 'en-set-id', name: 'BASE en-GB' }],
]);

function searchSnippetSets(setsLoaded: boolean) {
    // A pending search keeps the page in its loading state.
    return setsLoaded ? Promise.resolve(snippetSets) : new Promise(() => {});
}

async function createWrapper(query: Record<string, unknown>, { setsLoaded = true } = {}) {
    const wrapper = shallowMount(await wrapTestComponent('sw-settings-snippet-detail', { sync: true }), {
        global: {
            stubs: { 'sw-page': swPageStub },
            mocks: {
                $route: { meta: {}, query, params: { key: 'account.addressCreateBtn' } },
            },
            provide: {
                repositoryFactory: { create: () => ({ search: () => searchSnippetSets(setsLoaded) }) },
                snippetSetService: {},
                acl: { can: () => true },
            },
        },
    });

    await flushPromises();

    return wrapper;
}

function backButton(wrapper: VueWrapper) {
    const page = wrapper.findComponent(swPageStub);

    return { route: page.props('backRoute') as unknown, label: page.props('backLabel') as unknown };
}

describe('module/sw-settings-snippet/page/sw-settings-snippet-detail back button', () => {
    let wrapper: VueWrapper;

    beforeAll(() => {
        Shopware.Component.override('sw-settings-snippet-detail', {
            methods: { initializeSnippet() {} },
        });
    });

    afterEach(() => {
        wrapper?.unmount();
    });

    it.each([
        ['an array', ['de-set-id']],
        ['a single id', 'de-set-id'],
    ])('names the snippet set when the list shows one set given as %s', async (_, ids) => {
        wrapper = await createWrapper({ ids, page: 2, limit: 25 });

        expect(backButton(wrapper)).toEqual({
            route: { name: 'sw.settings.snippet.list', query: { ids, page: 2, limit: 25 } },
            label: 'BASE de-DE',
        });
    });

    it('shows only the icon while the snippet sets are loading', async () => {
        wrapper = await createWrapper({ ids: ['de-set-id'] }, { setsLoaded: false });

        expect(backButton(wrapper).label).toBe('');
    });

    it('reads "Snippets" when the list shows several sets', async () => {
        wrapper = await createWrapper({ ids: ['de-set-id', 'en-set-id'] });

        expect(backButton(wrapper).label).toBe('sw-settings-snippet.general.mainMenuItemGeneral');
    });

    it('leads back to the snippet sets without a list to return to', async () => {
        wrapper = await createWrapper({});

        expect(backButton(wrapper)).toEqual({
            route: { name: 'sw.settings.snippet.index' },
            label: 'sw-settings-snippet.general.mainMenuItemGeneral',
        });
    });
});
