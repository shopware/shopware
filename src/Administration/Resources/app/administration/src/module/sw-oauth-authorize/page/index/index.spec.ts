/**
 * @sw-package framework
 */

import { mount } from '@vue/test-utils';
import type { OAuthAuthorizationInfo } from 'src/core/service/api/oauth-authorize.api.service';

const validQuery = {
    response_type: 'code',
    client_id: 'shopware-cli',
    redirect_uri: 'http://127.0.0.1:53421/callback',
    state: 'xyz',
    code_challenge: 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM',
    code_challenge_method: 'S256',
    scope: 'write',
};

const defaultInfo: OAuthAuthorizationInfo = {
    client: { id: 'shopware-cli', name: 'Shopware CLI' },
    redirectUri: 'http://127.0.0.1:53421/callback',
    scopes: ['write'],
};

type PageVm = { navigateTo: (url: string) => void; isSubmitting: boolean; redirectHost: string };

function spyOnNavigateTo(wrapper: { vm: unknown }) {
    return jest.spyOn(wrapper.vm as PageVm, 'navigateTo').mockImplementation(() => {});
}

function omit<T extends Record<string, string>>(source: T, ...keys: string[]): Record<string, string> {
    return Object.fromEntries(Object.entries(source).filter(([key]) => !keys.includes(key)));
}

function createApiError(status: number, errors: Array<{ status: string; code: string; title?: string; detail?: string }>) {
    return Object.assign(new Error(`Request failed with status code ${status}`), {
        response: { status, data: { errors } },
    });
}

async function createWrapper({
    query = validQuery,
    getInfo = jest.fn(() => Promise.resolve(defaultInfo)),
    decide = jest.fn(() => Promise.resolve({ redirectUri: 'http://127.0.0.1:53421/callback?code=abc&state=xyz' })),
    getValues = jest.fn(() => Promise.resolve({ 'core.basicInformation.shopName': 'My Shop' })),
}: {
    query?: Record<string, string>;
    getInfo?: jest.Mock;
    decide?: jest.Mock;
    getValues?: jest.Mock;
} = {}) {
    const wrapper = mount(await wrapTestComponent('sw-oauth-authorize-index', { sync: true }), {
        global: {
            mocks: {
                $route: { query },
            },
            provide: {
                oauthAuthorizeApiService: { getInfo, decide },
                systemConfigApiService: { getValues },
            },
            stubs: {
                'i18n-t': {
                    props: [
                        'keypath',
                        'tag',
                    ],
                    template: `
                        <component :is="tag || 'span'" :data-keypath="keypath">
                            <template v-for="(_, name) in $slots" :key="name">
                                <slot :name="name" />
                            </template>
                        </component>
                    `,
                },
            },
        },
    });

    await flushPromises();

    return { wrapper, getInfo, decide, getValues };
}

describe('src/module/sw-oauth-authorize/page/index', () => {
    beforeEach(() => {
        Shopware.Store.get('session').setCurrentUser({ username: 'admin' } as Entity<'user'>);
    });

    afterEach(() => {
        Shopware.Store.get('session').removeCurrentUser();
    });

    it('should be registered', async () => {
        await createWrapper();

        expect(Shopware.Component.getComponentRegistry().has('sw-oauth-authorize-index')).toBe(true);
    });

    it('should load the shopware logo', async () => {
        const { wrapper } = await createWrapper();

        expect(wrapper.get('img.sw-oauth-authorize__logo').attributes('src')).toBe(
            'administration/administration/static/img/shopware_logo_blue.svg',
        );
    });

    it('should request the authorization info with the query params and render the consent', async () => {
        const { wrapper, getInfo } = await createWrapper();

        expect(getInfo).toHaveBeenCalledTimes(1);
        expect(getInfo).toHaveBeenCalledWith(validQuery);

        expect(wrapper.find('.sw-oauth-authorize__loading-indicator').exists()).toBe(false);
        expect(wrapper.find('.sw-oauth-authorize__error').exists()).toBe(false);

        expect(wrapper.get('.sw-oauth-authorize__title').attributes('data-keypath')).toBe(
            'sw-oauth-authorize.consent.title',
        );
        expect(wrapper.get('.sw-oauth-authorize__client-name').text()).toBe('Shopware CLI');
        expect(wrapper.get('.sw-oauth-authorize__shop-name').text()).toBe('My Shop');
        expect(wrapper.get('.sw-oauth-authorize__username').text()).toBe('admin');
        expect(wrapper.get('.sw-oauth-authorize__description').text()).toBe('sw-oauth-authorize.consent.permissionHint');
        expect(wrapper.get('.sw-oauth-authorize__permissions-title').text()).toBe(
            'sw-oauth-authorize.consent.permissionTitle',
        );
        expect(wrapper.get('.sw-oauth-authorize__trust-hint').text()).toBe('sw-oauth-authorize.consent.trustHint');
        expect(wrapper.get('.sw-oauth-authorize__redirect-hint').attributes('data-keypath')).toBe(
            'sw-oauth-authorize.consent.redirectHint',
        );
        expect(wrapper.get('.sw-oauth-authorize__redirect-host').text()).toBe('127.0.0.1:53421');
        expect(wrapper.vm.redirectHost).toBe('127.0.0.1:53421');

        expect(wrapper.get('.sw-oauth-authorize__approve').text()).toBe('sw-oauth-authorize.consent.approve');
        expect(wrapper.get('.sw-oauth-authorize__deny').text()).toBe('sw-oauth-authorize.consent.deny');
    });

    it('should ignore non-string query values and only forward the known authorization params', async () => {
        const { getInfo } = await createWrapper({
            query: {
                ...validQuery,
                // @ts-expect-error arrays are valid vue-router query values but must be ignored
                state: [
                    'a',
                    'b',
                ],
                unrelated: 'value',
            },
        });

        const expected = omit(validQuery, 'state');

        expect(getInfo).toHaveBeenCalledWith(expected);
    });

    it('should fall back to the default shop name when the system config cannot be read', async () => {
        const { wrapper } = await createWrapper({
            getValues: jest.fn(() => Promise.reject(new Error('forbidden'))),
        });

        expect(wrapper.get('.sw-oauth-authorize__shop-name').text()).toBe('Shopware');
        expect(wrapper.find('.sw-oauth-authorize__error').exists()).toBe(false);
    });

    it('should submit the approval and navigate to the returned redirect uri', async () => {
        const { wrapper, decide } = await createWrapper();
        const navigateTo = spyOnNavigateTo(wrapper);

        await wrapper.get('.sw-oauth-authorize__approve').trigger('click');
        await flushPromises();

        expect(decide).toHaveBeenCalledTimes(1);
        expect(decide).toHaveBeenCalledWith(validQuery, true);
        expect(navigateTo).toHaveBeenCalledWith('http://127.0.0.1:53421/callback?code=abc&state=xyz');
    });

    it('should submit the denial and navigate to the returned redirect uri', async () => {
        const denyUri = 'http://127.0.0.1:53421/callback?error=access_denied&state=xyz';
        const { wrapper, decide } = await createWrapper({
            decide: jest.fn(() => Promise.resolve({ redirectUri: denyUri })),
        });
        const navigateTo = spyOnNavigateTo(wrapper);

        await wrapper.get('.sw-oauth-authorize__deny').trigger('click');
        await flushPromises();

        expect(decide).toHaveBeenCalledTimes(1);
        expect(decide).toHaveBeenCalledWith(validQuery, false);
        expect(navigateTo).toHaveBeenCalledWith(denyUri);
    });

    it('should disable the buttons while the decision is submitted', async () => {
        let resolveDecision: (value: { redirectUri: string }) => void = () => {};
        const { wrapper } = await createWrapper({
            decide: jest.fn(
                () =>
                    new Promise((resolve) => {
                        resolveDecision = resolve;
                    }),
            ),
        });
        spyOnNavigateTo(wrapper);

        await wrapper.get('.sw-oauth-authorize__approve').trigger('click');
        await flushPromises();

        expect(wrapper.vm.isSubmitting).toBe(true);
        expect(wrapper.get('.sw-oauth-authorize__approve').attributes('disabled')).toBeDefined();
        expect(wrapper.get('.sw-oauth-authorize__deny').attributes('disabled')).toBeDefined();

        resolveDecision({ redirectUri: 'http://127.0.0.1:53421/callback?code=abc' });
        await flushPromises();
    });

    it('should show the api error when the decision fails', async () => {
        const { wrapper } = await createWrapper({
            decide: jest.fn(() =>
                Promise.reject(
                    createApiError(403, [
                        { status: '403', code: 'X', title: 'Forbidden', detail: 'Token is not user-bound' },
                    ]),
                ),
            ),
        });
        const navigateTo = spyOnNavigateTo(wrapper);

        await wrapper.get('.sw-oauth-authorize__approve').trigger('click');
        await flushPromises();

        expect(navigateTo).not.toHaveBeenCalled();
        expect(wrapper.get('.sw-oauth-authorize__error').text()).toBe('Token is not user-bound');
        expect(wrapper.find('.sw-oauth-authorize__approve').exists()).toBe(false);
        expect(wrapper.vm.isSubmitting).toBe(false);
    });

    it('should show an error and no buttons when required params are missing', async () => {
        const incompleteQuery = omit(validQuery, 'state', 'redirect_uri');
        const { wrapper, getInfo } = await createWrapper({ query: incompleteQuery });

        expect(getInfo).not.toHaveBeenCalled();
        expect(wrapper.find('.sw-oauth-authorize__loading-indicator').exists()).toBe(false);
        expect(wrapper.get('.sw-oauth-authorize__error').text()).toBe('sw-oauth-authorize.error.missingParameters');
        expect(wrapper.find('.sw-oauth-authorize__approve').exists()).toBe(false);
        expect(wrapper.find('.sw-oauth-authorize__deny').exists()).toBe(false);
    });

    it('should show the detail of the api error when the info request fails', async () => {
        const { wrapper } = await createWrapper({
            getInfo: jest.fn(() =>
                Promise.reject(
                    createApiError(400, [
                        {
                            status: '400',
                            code: 'FRAMEWORK__OAUTH_INVALID_REDIRECT_URI',
                            title: 'Invalid redirect URI',
                            detail: 'The redirect URI is not registered for this client',
                        },
                    ]),
                ),
            ),
        });

        expect(wrapper.get('.sw-oauth-authorize__error').text()).toBe('The redirect URI is not registered for this client');
        expect(wrapper.find('.sw-oauth-authorize__approve').exists()).toBe(false);
        expect(wrapper.find('.sw-oauth-authorize__deny').exists()).toBe(false);
    });

    it('should fall back to the error title and then to the generic message', async () => {
        const { wrapper: titleWrapper } = await createWrapper({
            getInfo: jest.fn(() =>
                Promise.reject(createApiError(401, [{ status: '401', code: 'X', title: 'Unauthorized' }])),
            ),
        });
        expect(titleWrapper.get('.sw-oauth-authorize__error').text()).toBe('Unauthorized');

        const { wrapper: genericWrapper } = await createWrapper({
            getInfo: jest.fn(() => Promise.reject(new Error('network'))),
        });
        expect(genericWrapper.get('.sw-oauth-authorize__error').text()).toBe('sw-oauth-authorize.error.generic');
    });
});
