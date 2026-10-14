/**
 * @sw-package after-sales
 */
import { mount } from '@vue/test-utils';
import type { SecurityReleaseResponse } from 'src/core/service/api/update.api.service';

interface WrapperOptions {
    getSecurityRelease?: () => Promise<SecurityReleaseResponse | null>;
    canUpdate?: boolean;
    hideUpdateModule?: boolean;
}

function createWrapper({
    getSecurityRelease = () => Promise.resolve(null),
    canUpdate = true,
    hideUpdateModule = false,
}: WrapperOptions = {}) {
    return mount(
        wrapTestComponent('sw-dashboard-security-release', {
            sync: true,
        }),
        {
            global: {
                provide: {
                    updateService: {
                        getSecurityRelease,
                    },
                    acl: {
                        can: () => canUpdate,
                    },
                },
                mocks: {
                    $t: (key: string, values?: Record<string, unknown>) => {
                        let translation = key;
                        Object.entries(values ?? {}).forEach(([k, v]) => {
                            translation = translation.replace(`{${k}}`, String(v));
                        });

                        return translation;
                    },
                    $router: {
                        push: jest.fn(),
                    },
                },
                stubs: {
                    'mt-banner': {
                        template: '<div class="mt-banner"><div v-if="$slots.default"><slot /></div></div>',
                        props: ['variant', 'title'],
                    },
                    'mt-button': {
                        template: '<button class="mt-button" @click="$emit(\'click\')"><slot /></button>',
                    },
                    'mt-link': {
                        template: '<a class="mt-link" :href="$attrs.href"><slot /></a>',
                    },
                },
            },
            attachTo: document.body,
        },
    );
}

describe('module/sw-dashboard/component/sw-dashboard-security-release', () => {
    beforeEach(() => {
        Shopware.Context.app.hideUpdateModule = false;
    });

    afterEach(() => {
        jest.restoreAllMocks();
    });

    it('does not render when no security release is available', async () => {
        const wrapper = createWrapper();
        await flushPromises();

        expect(wrapper.find('.mt-banner').exists()).toBe(false);
    });

    it('does not render when the API returns an empty response object', async () => {
        const wrapper = createWrapper({
            getSecurityRelease: () => Promise.resolve({} as SecurityReleaseResponse),
        });
        await flushPromises();

        expect(wrapper.find('.mt-banner').exists()).toBe(false);
    });

    it('renders the banner when a security release is available', async () => {
        const wrapper = createWrapper({
            getSecurityRelease: () =>
                Promise.resolve({
                    isSecurityRelease: true,
                    version: '6.7.1.0',
                    title: 'Shopware 6.7.1.0',
                    date: '2026-10-13T00:00:00+00:00',
                    highestSeverity: 'critical',
                    totalVulnerabilities: 2,
                    vulnerabilities: [],
                }),
        });
        await flushPromises();

        expect(wrapper.find('.mt-banner').exists()).toBe(true);
        expect(wrapper.text()).toContain('sw-dashboard.securityRelease.title');
        expect(wrapper.text()).toContain('sw-dashboard.securityRelease.message');
    });

    it('uses the critical variant for high and critical severity', async () => {
        const wrapper = createWrapper({
            getSecurityRelease: () =>
                Promise.resolve({
                    isSecurityRelease: true,
                    version: '6.7.1.0',
                    title: 'Shopware 6.7.1.0',
                    date: '2026-10-13T00:00:00+00:00',
                    highestSeverity: 'critical',
                    totalVulnerabilities: 1,
                    vulnerabilities: [],
                }),
        });
        await flushPromises();

        expect(wrapper.find('.mt-banner').attributes('variant')).toBe('critical');
    });

    it('hides the update button when the user lacks the update privilege', async () => {
        const wrapper = createWrapper({
            canUpdate: false,
            getSecurityRelease: () =>
                Promise.resolve({
                    isSecurityRelease: true,
                    version: '6.7.1.0',
                    title: 'Shopware 6.7.1.0',
                    date: '2026-10-13T00:00:00+00:00',
                    highestSeverity: 'medium',
                    totalVulnerabilities: 1,
                    vulnerabilities: [],
                }),
        });
        await flushPromises();

        expect(
            wrapper.findAll('.mt-button').some((button) => button.text() === 'sw-dashboard.securityRelease.updateNow'),
        ).toBe(false);
    });
});
