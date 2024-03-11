import { mount } from '@vue/test-utils';

async function createWrapper(customString = '') {
    return mount(await wrapTestComponent('sw-settings-store', {
        sync: true,
    }), {
        global: {
            renderStubDefaultSlot: true,
            stubs: {
                'sw-page': {
                    template: '<div><slot name="content"></slot></div>',
                },
                'sw-card-view': true,
                'sw-system-config': {
                    template: '<div></div>',
                    data() {
                        return {
                            actualConfigData: {
                                null: {
                                    'core.store.licenseHost': customString,
                                },
                            },
                        };
                    },
                },
                'sw-skeleton': true,
            },
        },
    });
}

/**
 * @package services-settings
 */
describe('src/module/sw-settings-store/page/sw-settings-store', () => {
    it('should be a vue.js component', async () => {
        const wrapper = await createWrapper();
        expect(wrapper.vm).toBeTruthy();
    });

    it('should trim empty spaces from license host', async () => {
        const wrapper = await createWrapper('  foobar  ');

        expect(wrapper.vm.$refs.systemConfig.actualConfigData).toStrictEqual({
            null: {
                'core.store.licenseHost': '  foobar  ',
            },
        });

        wrapper.vm.trimHost();

        compareStringWithLicenseHost('foobar');

        setTrimAndCompare(' String with spaces ', 'String with spaces');

        setTrimAndCompare(
            '                               ' +
            '                                     String with many spaces at the beginning',
            'String with many spaces at the beginning',
        );

        setTrimAndCompare(' https://www.shopware.com/de/ ', 'https://www.shopware.com/de/');

        // sets the licenseHost to the String 'set', then calls the trimHost method and compares the trimmed licenseHost
        // with the expected string
        function setTrimAndCompare(set, expected) {
            wrapper.vm.$refs.systemConfig.actualConfigData.null['core.store.licenseHost'] = set;
            trimAndCompare(expected);
        }

        // calls the trim method and then compares the licenseHost with the expected(trimmed) string
        function trimAndCompare(expected) {
            wrapper.vm.trimHost();
            compareStringWithLicenseHost(expected);
        }

        // Compares strictly, Makes for better readability
        function compareStringWithLicenseHost(expected) {
            compareStrings(wrapper.vm.$refs.systemConfig.actualConfigData, expected);
        }

        // Compares Strict
        function compareStrings(given, expected) {
            expect(given).toStrictEqual({
                null: {
                    'core.store.licenseHost': expected,
                },
            });
        }
    });
});
