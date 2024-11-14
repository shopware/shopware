import { mount } from '@vue/test-utils';

/**
 * @package customer-order
 */

const classes = {
    root: 'sw-page__main-content',
    cardView: 'sw-card-view',
    systemConfig: 'sw-system-config',
    settingsCard: 'sw-card',
    newsletterSubscribeUrl: 'core.newsletter.subscribeUrl',
};

async function createWrapper() {
    return mount(
        await wrapTestComponent('sw-settings-newsletter', {
            sync: true,
        }),
        {
            global: {
                renderStubDefaultSlot: true,
                mocks: {
                    $route: {
                        meta: {},
                    },
                },
                provide: {
                    systemConfigApiService: {
                        getConfig: () => Promise.resolve(createConfig()),
                        getValues: () => Promise.resolve(getValues()),
                    },
                    validationService: {},
                    currentValue: 'test',
                    repositoryFactory: {
                        create: () => {
                            return {
                                get: () => Promise.resolve({}),
                            };
                        },
                    },
                },
                stubs: {
                    'sw-page': {
                        template: `
                     <div class="sw-page">
                          <slot name="smart-bar-actions"></slot>
                          <div class="sw-page__main-content">
                            <slot name="content"></slot>
                          </div>
                          <slot></slot>settingsCard
                     </div>`,
                    },
                    'sw-icon': true,
                    'sw-card': {
                        template: '<div class="sw-card"><slot></slot></div>',
                    },
                    'sw-card-view': {
                        template: '<div class="sw-card-view"><slot></slot></div>',
                    },
                    'sw-button-process': true,
                    'sw-system-config': await wrapTestComponent('sw-system-config'),
                    'sw-inherit-wrapper': await wrapTestComponent('sw-inherit-wrapper'),
                    'sw-form-field-renderer': await wrapTestComponent('sw-form-field-renderer'),
                    'sw-text-field': await wrapTestComponent('sw-text-field'),
                    'sw-text-field-deprecated': await wrapTestComponent('sw-text-field-deprecated', { sync: true }),
                    'sw-switch-field': await wrapTestComponent('sw-switch-field'),
                    'sw-switch-field-deprecated': await wrapTestComponent('sw-switch-field-deprecated', { sync: true }),
                    'sw-checkbox-field': await wrapTestComponent('sw-checkbox-field'),
                    'sw-checkbox-field-deprecated': await wrapTestComponent('sw-checkbox-field-deprecated', { sync: true }),
                    'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                    'sw-base-field': await wrapTestComponent('sw-base-field'),
                    'sw-block-field': await wrapTestComponent('sw-block-field'),
                    'sw-field-error': await wrapTestComponent('sw-field-error'),
                    'sw-help-text': await wrapTestComponent('sw-help-text'),
                    'sw-search-bar': true,
                    'sw-notification-center': true,
                    'sw-loader': true,
                    'sw-skeleton': true,
                    'sw-sales-channel-switch': true,
                    'sw-alert': true,
                    'sw-inheritance-switch': true,
                    'sw-field-copyable': true,
                    'sw-ai-copilot-badge': true,
                },
            },
        },
    );
}

function getValues() {
    return {
        'core.newsletter.doubleOptIn': true,
        'core.newsletter.subscribeUrl': '/newsletter-subscribe?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%',
    };
}

function createConfig() {
    return [
        {
            title: {
                'en-GB': 'Newsletter configuration',
                'de-DE': 'Newsletter-Konfiguration',
            },
            name: null,
            elements: [
                {
                    name: 'core.newsletter.subscribeUrl',
                    type: 'text',
                    defaultValue: '/newsletter-subscribe?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%',
                    config: {
                        label: {
                            'en-GB': 'Subscription url',
                            'de-DE': 'Anmelde-Url',
                        },
                        placeholder: {
                            'en-GB': '/newsletter-subscribe?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%',
                        },
                        helpText: {
                            'en-GB':
                                'Url to confirm the subscription to the newsletter.<br/>Available placeholders: <br/>%%HASHEDEMAIL%%<br/>%%SUBSCRIBEHASH%%',
                            'de-DE':
                                'Url um die Newsletteranmeldung zu bestätigen.<br/>Verfügbare Platzhalter: <br/>%%HASHEDEMAIL%%<br/>%%SUBSCRIBEHASH%%',
                        },
                    },
                },
                {
                    name: 'core.newsletter.doubleOptIn',
                    type: 'bool',
                    config: {
                        label: { 'en-GB': 'Double Opt-in' },
                        helpText: {
                            'en-GB': 'Use Double Opt-in for newsletter subscriptions',
                            'de-DE': 'Nutze das Double Opt-In Verfahren für Newsletter Anmeldungen.',
                        },
                    },
                },
            ],
        },
    ];
}

describe('module/sw-settings-newsletter/page/sw-settings-newsletter', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the settings card system', async () => {
        await wrapper.vm.$nextTick();
        expect(
            wrapper
                .find(`.${classes.root}`)
                .find(`.${classes.cardView}`)
                .find(`.${classes.systemConfig}`)
                .find(`.${classes.settingsCard}`)
                .exists(),
        ).toBeTruthy();
    });

    it('should contain the subscribeUrl', async () => {
        await wrapper.vm.$nextTick();
        expect(
            wrapper
                .find(`.${classes.root}`)
                .find('.sw-system-config--field-core-newsletter-subscribe-url')
                .find('input')
                .exists(),
        ).toBeTruthy();
        expect(
            wrapper
                .find(`.${classes.root}`)
                .find('.sw-system-config--field-core-newsletter-subscribe-url')
                .find("input[id='core.newsletter.subscribeUrl']")
                .attributes('placeholder'),
        ).toBe('/newsletter-subscribe?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%');
    });
});
