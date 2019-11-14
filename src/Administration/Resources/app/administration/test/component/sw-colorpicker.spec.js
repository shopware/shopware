import { shallowMount, createLocalVue } from '@vue/test-utils';
import 'src/app/component/form/sw-colorpicker';
import 'src/app/component/form/field-base/sw-contextual-field';
import 'src/app/component/form/field-base/sw-block-field';
import 'src/app/component/form/field-base/sw-base-field';
import 'src/app/component/form/field-base/sw-field-error';
import 'src/app/component/utils/sw-popover';

describe('components/form/sw-colorpicker', () => {
    let wrapper;
    let localVue;
    const eventListener = {};

    beforeAll(() => {
        window.addEventListener = jest.fn((event, cb) => {
            eventListener[event] = cb;
        });

        window.removeEventListener = jest.fn((event) => {
            delete eventListener[event];
        });
    });

    beforeEach(() => {
        localVue = createLocalVue();
        localVue.directive('popover', {});

        wrapper = shallowMount(Shopware.Component.build('sw-colorpicker'), {
            localVue,
            stubs: {
                'sw-contextual-field': Shopware.Component.build('sw-contextual-field'),
                'sw-block-field': Shopware.Component.build('sw-block-field'),
                'sw-base-field': Shopware.Component.build('sw-base-field'),
                'sw-field-error': Shopware.Component.build('sw-field-error'),
                'sw-popover': Shopware.Component.build('sw-popover')
            },
            props: {
                value: null
            }
        });
    });

    afterEach(() => {
        localVue = null;
        wrapper.destroy();
    });

    it('should be a Vue.js component', () => {
        expect(wrapper.isVueInstance()).toBeTruthy();
    });

    it('the watcher value updates the colorValue', () => {
        wrapper.setProps({
            value: '#123123'
        });

        expect(wrapper.vm.colorValue).toBe('#123123');
    });

    it('should be a number multiplied by 100', () => {
        wrapper.setData({
            alphaValue: 0.5
        });

        expect(wrapper.vm.integerAlpha).toBe(50);
    });

    it('should compute the correct alpha slider background', () => {
        wrapper.setData({
            hueValue: 50,
            saturationValue: 30,
            luminanceValue: 80
        });

        expect(wrapper.vm.sliderBackground).toBe(
            // eslint-disable-next-line max-len
            "linear-gradient(90deg, hsla(50, 30%, 80%, 0), hsl(50, 30%, 80%)), url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' width='100%25' height='100%25'%3E%3Crect width='10' height='10' x='00' y='00' fill='%23cdd5db' /%3E%3Crect width='10' height='10' x='10' y='10' fill='%23cdd5db' /%3E%3C/svg%3E\")"
        );
    });

    it('colorValid should be true when using hex', () => {
        wrapper.setData({
            colorValue: '#fff'
        });

        expect(wrapper.vm.isColorValid).toBe(true);
    });

    it('colorValid should be true when using rgb', () => {
        wrapper.setData({
            colorValue: 'rgb(50, 40, 200)'
        });

        expect(wrapper.vm.isColorValid).toBe(true);
    });

    it('colorValid should be true when using hsl', () => {
        wrapper.setData({
            colorValue: 'hsl(40, 50%, 60%)'
        });

        expect(wrapper.vm.isColorValid).toBe(true);
    });

    it('colorValid should not be true when using unvalid colorValue', () => {
        wrapper.setData({
            colorValue: 'super random color'
        });

        expect(wrapper.vm.isColorValid).toBe(false);
    });

    it('should preview the prop value', () => {
        wrapper.setProps({
            value: '#123123'
        });

        const previewColorStyleAttribute = wrapper
            .find('.sw-colorpicker__previewColor')
            .attributes('style');

        expect(previewColorStyleAttribute).toBe('background: rgb(18, 49, 35);');
    });

    it('should be the correct selector background', () => {
        wrapper.setData({
            hueValue: 154
        });

        expect(wrapper.vm.selectorBackground).toBe('hsl(154, 100%, 50%)');
    });

    it('should be the correct red value', () => {
        wrapper.setData({
            hueValue: 153,
            saturationValue: 78,
            luminanceValue: 57
        });

        expect(wrapper.vm.redValue).toBe(60);
    });

    it('red value setter should function properly', () => {
        wrapper.setData({
            greenValue: 0,
            blueValue: 45
        });
        wrapper.vm.redValue = 25;

        expect(wrapper.vm.hueValue).toBe(273);
        expect(wrapper.vm.saturationValue).toBe(100);
        expect(wrapper.vm.luminanceValue).toBe(8.8);
    });

    it('green value setter should function properly', () => {
        wrapper.setData({
            redValue: 101,
            blueValue: 100
        });
        wrapper.vm.redValue = 64;

        expect(wrapper.vm.hueValue).toBe(240);
        expect(wrapper.vm.saturationValue).toBe(22);
        expect(wrapper.vm.luminanceValue).toBe(32.2);
    });

    it('blue value setter should function properly', () => {
        wrapper.setData({
            redValue: 39,
            greenValue: 123
        });
        wrapper.vm.redValue = 89;

        expect(wrapper.vm.hueValue).toBe(95);
        expect(wrapper.vm.saturationValue).toBe(31.6);
        expect(wrapper.vm.luminanceValue).toBe(36.7);
    });

    it('should be the correct green value', () => {
        wrapper.setData({
            hueValue: 153,
            saturationValue: 78,
            luminanceValue: 57
        });

        expect(wrapper.vm.greenValue).toBe(231);
    });

    it('should be the correct blue value', () => {
        wrapper.setData({
            hueValue: 153,
            saturationValue: 78,
            luminanceValue: 57
        });

        expect(wrapper.vm.blueValue).toBe(154);
    });

    it('should be the correct rgb value', () => {
        wrapper.setData({
            hueValue: 85,
            saturationValue: 80,
            luminanceValue: 55,
            alphaValue: 0.67
        });

        expect(wrapper.vm.rgbValue).toBe('rgba(156, 232, 48, 0.67)');
    });

    it('should set the correct red value', () => {
        wrapper.setProps({
            redValue: 25,
            greenValue: 25,
            blueValue: 25
        });

        wrapper.vm.setSingleRGBValue(80, 'red');

        expect(wrapper.vm.redValue).toBe(80);
    });

    it('should set the correct green value', () => {
        wrapper.setProps({
            redValue: 25,
            greenValue: 25,
            blueValue: 25
        });

        wrapper.vm.setSingleRGBValue(192, 'green');

        expect(wrapper.vm.greenValue).toBe(192);
    });

    it('should set the correct blue value', () => {
        wrapper.setProps({
            redValue: 25,
            greenValue: 25,
            blueValue: 25
        });

        wrapper.vm.setSingleRGBValue(245, 'blue');

        expect(wrapper.vm.blueValue).toBe(245);
    });

    it('should split the rgb string correctly', () => {
        const rgbValues = wrapper.vm.splitRGBValues('rgb(40, 50, 199)');

        expect(rgbValues.red).toBe(40);
        expect(rgbValues.green).toBe(50);
        expect(rgbValues.blue).toBe(199);
    });

    it('should be the correct hsl value', () => {
        wrapper.setData({
            hueValue: 176,
            saturationValue: 66,
            luminanceValue: 40
        });

        expect(wrapper.vm.hslValue).toBe('hsl(176, 66%, 40%)');
    });

    it('should be the correct hsla value', () => {
        wrapper.setData({
            hueValue: 40,
            saturationValue: 33,
            luminanceValue: 13,
            alphaValue: 0.5
        });

        expect(wrapper.vm.hslValue).toBe('hsla(40, 33%, 13%, 0.5)');
    });

    it('should set the correct hsla values', () => {
        wrapper.setData({
            hueValue: 40,
            saturationValue: 50,
            luminanceValue: 87,
            alphaValue: 0.77
        });

        wrapper.vm.setHslaValues(145, 40, 946, 0.74);

        expect(wrapper.vm.hueValue).toBe(145);
        expect(wrapper.vm.saturationValue).toBe(40);
        expect(wrapper.vm.luminanceValue).toBe(946);
        expect(wrapper.vm.alphaValue).toBe(0.74);
    });

    it('should be the correct hex value', () => {
        wrapper.setData({
            hueValue: 341,
            saturationValue: 46,
            luminanceValue: 84
        });

        expect(wrapper.vm.hexValue).toBe('#e9c3cf');
    });

    it('should validate the hex input', () => {
        wrapper.setData({
            hueValue: 275,
            saturationValue: 55,
            luminanceValue: 89,
            hexValue: 'qwertz'
        });

        expect(wrapper.vm.hexValue).toBe('#e6d4f2');
    });

    it('should be the correct hex-alpha value', () => {
        wrapper.setData({
            hueValue: 341,
            saturationValue: 46,
            luminanceValue: 84,
            alphaValue: 0.4
        });

        expect(wrapper.vm.hexValue).toBe('#e9c3cf66');
    });

    it('selector should have the right x co-ordinate', () => {
        wrapper.setData({
            saturationValue: 63
        });

        expect(wrapper.vm.selectorPositionX).toBe('calc(63% - 9px)');
    });

    it('selector should have the right y co-ordinate', () => {
        wrapper.setData({
            luminanceValue: 32
        });

        expect(wrapper.vm.selectorPositionY).toBe('calc(68% - 9px)');
    });

    it('colorValue should be a rgb value', () => {
        wrapper.setProps({
            colorOutput: 'rgb'
        });

        wrapper.setData({
            hueValue: 180,
            saturationValue: 50,
            luminanceValue: 40
        });

        expect(wrapper.vm.colorValue).toBe('rgb(51, 153, 153)');
    });

    it('colorValue should be a hsl value', () => {
        wrapper.setProps({
            colorOutput: 'hsl'
        });

        wrapper.setData({
            hueValue: 0,
            saturationValue: 81,
            luminanceValue: 72
        });

        expect(wrapper.vm.colorValue).toBe('hsl(0, 81%, 72%)');
    });

    it('colorValue should be a hex value', () => {
        wrapper.setProps({
            colorOutput: 'hex'
        });

        wrapper.setData({
            hueValue: 149,
            saturationValue: 55,
            luminanceValue: 63
        });

        expect(wrapper.vm.colorValue).toBe('#6dd59f');
    });

    it('colorValue should be a hex value if colorOutput is `auto` ', () => {
        wrapper.setProps({
            colorOutput: 'auto'
        });

        wrapper.setData({
            hueValue: 149,
            saturationValue: 55,
            luminanceValue: 63
        });

        expect(wrapper.vm.colorValue).toBe('#6dd59f');
    });

    it('colorValue should be a rgba value if colorOutput is `auto` ', () => {
        wrapper.setProps({
            colorOutput: 'auto'
        });

        wrapper.setData({
            hueValue: 149,
            saturationValue: 55,
            luminanceValue: 63,
            alphaValue: 0.87
        });

        expect(wrapper.vm.colorValue).toBe('rgba(109, 213, 159, 0.87)');
    });

    it('should add an event listener on the window', () => {
        wrapper.vm.setOutsideClickEvent();
        expect(eventListener).toHaveProperty('mousedown');

        wrapper.vm.removeOutsideClickEvent();
    });

    it('should remove an event listener on the window', () => {
        wrapper.vm.setOutsideClickEvent();
        wrapper.vm.removeOutsideClickEvent();

        expect(eventListener).not.toHaveProperty('mousedown');
    });

    it('should show only the input field without colorpicker', () => {
        wrapper.setData({ visible: false });
        const colorpicker = wrapper.find('.sw-colorpicker__colorpicker');

        expect(colorpicker.exists()).toBe(false);
    });

    it('should split rgb values correctly', () => {
        const rgbValues = wrapper.vm.splitRGBValues('rgba(40, 242, 74, 0.8)');

        expect(rgbValues.red).toBe(40);
        expect(rgbValues.blue).toBe(74);
        expect(rgbValues.green).toBe(242);
        expect(rgbValues.alpha).toBe(0.8);
    });

    it('should show the colorpicker', () => {
        wrapper.setData({ visible: true });
        const colorpicker = wrapper.find('.sw-colorpicker__colorpicker');

        expect(colorpicker.exists()).toBe(true);
    });

    it('should output in rgb', () => {
        wrapper.setProps({
            value: '#123123',
            colorOutput: 'rgb'
        });

        wrapper.setData({
            visible: true
        });

        expect(wrapper.vm.colorValue).toBe('rgb(18, 49, 35)');
    });

    it('should split hsl values correctly', () => {
        const hslValues = wrapper.vm.splitHSLValues('hsla(67, 43%, 67%, 0.98)');

        expect(hslValues.hue).toBe(67);
        expect(hslValues.saturation).toBe(43);
        expect(hslValues.luminance).toBe(67);
        expect(hslValues.alpha).toBe(0.98);
    });

    it('should convert HSLA to RGBA', () => {
        const hue = 201;
        const saturation = 46;
        const luminance = 51;
        const alpha = 0.27;

        const rgbValue = wrapper.vm.convertHSLtoRGB(
            hue,
            saturation,
            luminance,
            alpha
        );

        expect(rgbValue.red).toBe(73);
        expect(rgbValue.green).toBe(147);
        expect(rgbValue.blue).toBe(188);
        expect(rgbValue.alpha).toBe(0.27);
    });

    it('should convert Hsl to Hex', () => {
        const hexValues = wrapper.vm.convertHSLtoHEX(75, 72, 65, 0.6);

        expect(hexValues).toBe('#c6e66599');
    });

    it('should convert Rgb to Hsl', () => {
        const hslValues = wrapper.vm.convertRGBtoHSL(255, 23, 67, 0.8);

        expect(hslValues.hue).toBe(349);
        expect(hslValues.saturation).toBe(100);
        expect(hslValues.luminance).toBe(54.5);
    });

    it('should convert Hex to Hsl', () => {
        const hslValues = wrapper.vm.convertHEXtoHSL('#24db5b99');

        expect(hslValues.hue).toBe(138);
        expect(hslValues.saturation).toBe(71.8);
        expect(hslValues.luminance).toBe(50);
        expect(hslValues.alpha).toBe(0.6);
    });
});
