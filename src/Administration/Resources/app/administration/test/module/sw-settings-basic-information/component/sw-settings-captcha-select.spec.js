import { shallowMount } from '@vue/test-utils';
import 'src/module/sw-settings-basic-information/component/sw-settings-captcha-select';

describe('src/module/sw-settings-basic-information/component/sw-settings-captcha-select', () => {
    function CaptchaSelect() {
        return shallowMount(Shopware.Component.build('sw-settings-captcha-select'), {
            stubs: {
                'sw-multi-select': {
                    template: '<div></div>'
                }
            },
            mocks: {
                $tc: (translationPath) => translationPath,
                getInlineSnippet: (name) => name
            },
            attrs: {
                label: 'label',
                placeholder: 'placeholder'
            },
            provide: {
                captchaService: {
                    list: () => Promise.resolve(['lorem-ipsum'])
                }
            }
        });
    }

    let captchaSelect = null;

    beforeEach(() => {
        captchaSelect = new CaptchaSelect();
    });

    it('should be a vue js component', async () => {
        expect(captchaSelect.vm).toBeTruthy();
    });

    it('should load a list of options when mounted', async () => {
        const spyList = jest.spyOn(captchaSelect.vm.captchaService, 'list');

        await captchaSelect.vm.mountedComponent();

        expect(spyList).toHaveBeenCalled();
    });

    it('should render options when setCaptchaOptions is called', async () => {
        const spyRenderCaptchaOption = jest.spyOn(captchaSelect.vm, 'renderCaptchaOption');

        captchaSelect.vm.setCaptchaOptions(['dolor-sit-amet']);

        expect(spyRenderCaptchaOption).toHaveBeenCalled();
    });

    it('should set options when setCaptchaOptions is called', async () => {
        expect(captchaSelect.vm.availableCaptchas.length).toBeLessThan(1);

        captchaSelect.vm.setCaptchaOptions(['dolor-sit-amet']);

        expect(captchaSelect.vm.availableCaptchas.length).toBeGreaterThan(0);
    });

    it('should render options correctly', async () => {
        const technicalName = 'consectetur';
        const expected = {
            label: `sw-settings-basic-information.captcha.label.${technicalName}`,
            value: technicalName
        };

        const option = captchaSelect.vm.renderCaptchaOption(technicalName);

        expect(option).toMatchObject(expected);
    });

    it('should read translations correctly', async () => {
        const expected = {
            label: 'label',
            placeholder: 'placeholder'
        };

        expect(captchaSelect.vm.getTranslations()).toMatchObject(expected);
        expect(captchaSelect.vm.getTranslations().helpText).toBeUndefined();
    });

    it('should emit the input event when the input\'s value changes', async () => {
        const value = 'lorem-ipsum';
        captchaSelect.vm.currentValue = value;

        await captchaSelect.vm.$nextTick();

        expect(captchaSelect.emitted().input).toBeTruthy();
        expect(captchaSelect.emitted().input.length).toBeGreaterThan(0);
        expect(captchaSelect.emitted().input[0]).toEqual([value]);
    });
});
