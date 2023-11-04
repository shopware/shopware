import { createLocalVue, shallowMount } from '@vue/test-utils';
import swSettingsNewsletter from 'src/module/sw-settings-newsletter/page/sw-settings-newsletter';
import swSystemConfig from 'src/module/sw-settings/component/sw-system-config';
import 'src/app/component/utils/sw-inherit-wrapper';
import 'src/app/component/form/sw-form-field-renderer';
import 'src/app/component/form/sw-field';
import 'src/app/component/form/sw-text-field';
import 'src/app/component/form/sw-switch-field';
import 'src/app/component/form/sw-checkbox-field';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/base/sw-help-text';

/**
 * @package customer-order
 */

Shopware.Component.register('sw-settings-newsletter', swSettingsNewsletter);
Shopware.Component.register('sw-system-config', swSystemConfig);

const classes = {
    root: 'sw-page__main-content',
    cardView: 'sw-card-view',
    systemConfig: 'sw-system-config',
    settingsCard: 'sw-card',
    newsletterSubscribeUrl: 'core.newsletter.subscribeUrl',
};

async function createWrapper() {
    const localVue = createLocalVue();

    return shallowMount(await Shopware.Component.build('sw-settings-newsletter'), {
        localVue,
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
            'sw-system-config': await Shopware.Component.build('sw-system-config'),
            'sw-inherit-wrapper': await Shopware.Component.build('sw-inherit-wrapper'),
            'sw-form-field-renderer': await Shopware.Component.build('sw-form-field-renderer'),
            'sw-field': await Shopware.Component.build('sw-field'),
            'sw-text-field': await Shopware.Component.build('sw-text-field'),
            'sw-switch-field': await Shopware.Component.build('sw-switch-field'),
            'sw-checkbox-field': await Shopware.Component.build('sw-checkbox-field'),
            'sw-contextual-field': await Shopware.Component.build('sw-contextual-field'),
            'sw-base-field': await Shopware.Component.build('sw-base-field'),
            'sw-block-field': await Shopware.Component.build('sw-block-field'),
            'sw-field-error': await Shopware.Component.build('sw-field-error'),
            'sw-help-text': await Shopware.Component.build('sw-help-text'),
            'sw-search-bar': true,
            'sw-notification-center': true,
            'sw-loader': true,
            'sw-skeleton': true,
        },
    });
}

function getValues() {
    return {
        'core.newsletter.doubleOptIn': true,
        'core.newsletter.subscribeUrl': '/newsletter-subscribe?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%',
    };
}

function createConfig() {
    return [{
        title: { 'en-GB': 'Newsletter configuration', 'de-DE': 'Newsletter-Konfiguration' },
        name: null,
        elements: [{
            name: 'core.newsletter.subscribeUrl',
            type: 'text',
            defaultValue: '/newsletter-subscribe?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%',
            config: {
                label: { 'en-GB': 'Subscription url', 'de-DE': 'Anmelde-Url' },
                placeholder: { 'en-GB': '/newsletter-subscribe?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%' },
                helpText: {
                    'en-GB': 'Url to confirm the subscription to the newsletter.<br/>Available placeholders: <br/>%%HASHEDEMAIL%%<br/>%%SUBSCRIBEHASH%%',
                    'de-DE': 'Url um die Newsletteranmeldung zu bestätigen.<br/>Verfügbare Platzhalter: <br/>%%HASHEDEMAIL%%<br/>%%SUBSCRIBEHASH%%',
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
        }],
    }];
}

describe('module/sw-settings-newsletter/page/sw-settings-newsletter', () => {
    let wrapper;

    beforeEach(async () => {
        wrapper = await createWrapper();
        await flushPromises();
    });

    afterEach(() => {
        wrapper.destroy();
    });

    it('should be a Vue.js component', async () => {
        expect(wrapper.vm).toBeTruthy();
    });

    it('should contain the settings card system', async () => {
        await wrapper.vm.$nextTick();
        expect(
            wrapper.find(`.${classes.root}`)
                .find(`.${classes.cardView}`)
                .find(`.${classes.systemConfig}`)
                .find(`.${classes.settingsCard}`)
                .exists(),
        ).toBeTruthy();
    });

    it('should contain the subscribeUrl', async () => {
        await wrapper.vm.$nextTick();
        expect(
            wrapper.find(`.${classes.root}`)
                .find('.sw-system-config--field-core-newsletter-subscribe-url')
                .find('input')
                .exists(),
        ).toBeTruthy();
        expect(
            wrapper.find(`.${classes.root}`)
                .find('.sw-system-config--field-core-newsletter-subscribe-url')
                .find('input[id=\'core.newsletter.subscribeUrl\']')
                .attributes('placeholder'),
        ).toBe('/newsletter-subscribe?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%');
    });
});
