/**
 * @package admin
 */

import { mount } from '@vue/test-utils';

async function createWrapper(additionalProps = {}) {
    const wrapper = mount(await wrapTestComponent('sw-colorpicker-deprecated', { sync: true }), {
        global: {
            stubs: {
                'sw-contextual-field': await wrapTestComponent('sw-contextual-field'),
                'sw-block-field': await wrapTestComponent('sw-block-field'),
                'sw-base-field': await wrapTestComponent('sw-base-field'),
                'sw-field-error': await wrapTestComponent('sw-field-error'),
                'sw-popover': await wrapTestComponent('sw-popover'),
                'sw-popover-deprecated': await wrapTestComponent('sw-popover-deprecated', { sync: true }),
                'sw-help-text': true,
                'sw-ai-copilot-badge': true,
                'sw-inheritance-switch': true,
                'sw-icon': true,
                'mt-floating-ui': true,
            },
        },
        props: {
            value: null,
        },
        ...additionalProps,
    });

    await flushPromises();

    return wrapper;
}

describe('components/form/sw-colorpicker', () => {
    let wrapper;
    const eventListener = {};

    beforeAll(() => {
        window.addEventListener = jest.fn((event, cb) => {
            eventListener[event] = cb;
        });

        window.removeEventListener = jest.fn((event) => {
            delete eventListener[event];
        });
    });

    beforeEach(async () => {
        wrapper = await createWrapper();
    });

    afterEach(() => {
        if (!wrapper) return;

        wrapper.unmount();
        wrapper = null;
    });

    it('the watcher value updates the colorValue', async () => {
        await wrapper.setProps({
            value: '#123123',
        });

        expect(wrapper.vm.colorValue).toBe('#123123');
    });

    it('should be a number multiplied by 100', async () => {
        await wrapper.setData({
            alphaValue: 0.5,
        });

        expect(wrapper.vm.integerAlpha).toBe(50);
    });

    it('should compute the correct alpha slider background', async () => {
        await wrapper.setData({
            hueValue: 50,
            saturationValue: 30,
            luminanceValue: 80,
        });

        expect(wrapper.vm.sliderBackground).toBe(
            // eslint-disable-next-line max-len
            "linear-gradient(90deg, hsla(50, 30%, 80%, 0), hsl(50, 30%, 80%)), url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' width='100%25' height='100%25'%3E%3Crect width='10' height='10' x='00' y='00' fill='%23cdd5db' /%3E%3Crect width='10' height='10' x='10' y='10' fill='%23cdd5db' /%3E%3C/svg%3E\")",
        );
    });

    it('colorValid should be true when using hex', async () => {
        wrapper.vm.colorValue = '#fff';
        await flushPromises();

        expect(wrapper.vm.isColorValid).toBe(true);
    });

    it('colorValid should be true when using rgb', async () => {
        wrapper.vm.colorValue = 'rgb(50, 40, 200)';
        await flushPromises();

        expect(wrapper.vm.isColorValid).toBe(true);
    });

    it('colorValid should be true when using hsl', async () => {
        wrapper.vm.colorValue = 'hsl(40, 50%, 60%)';
        await flushPromises();

        expect(wrapper.vm.isColorValid).toBe(true);
    });

    it('colorValid should not be true when using invalid colorValue', async () => {
        wrapper.vm.colorValue = 'super random color';
        await flushPromises();

        expect(wrapper.vm.isColorValid).toBe(false);
    });

    it('should preview the prop value', async () => {
        await wrapper.setProps({
            value: '#123123',
        });

        const previewColorStyleAttribute = wrapper.find('.sw-colorpicker__previewColor').attributes('style');

        expect(previewColorStyleAttribute).toBe('background: rgb(18, 49, 35);');
    });

    it('should be the correct selector background', async () => {
        await wrapper.setData({
            hueValue: 154,
        });

        expect(wrapper.vm.selectorBackground).toBe('hsl(154, 100%, 50%)');
    });

    it('should be the correct red value', async () => {
        await wrapper.setData({
            hueValue: 153,
            saturationValue: 78,
            luminanceValue: 57,
        });

        expect(wrapper.vm.redValue).toBe(60);
    });

    it('red value setter should function properly', async () => {
        wrapper.vm.blueValue = 45;
        wrapper.vm.greenValue = 0;
        wrapper.vm.redValue = 25;

        expect(wrapper.vm.hueValue).toBe(273.33);
        expect(wrapper.vm.saturationValue).toBe(100);
        expect(wrapper.vm.luminanceValue).toBe(8.8);
    });

    it('green value setter should function properly', async () => {
        wrapper.vm.blueValue = 100;
        wrapper.vm.redValue = 64;

        expect(wrapper.vm.hueValue).toBe(240);
        expect(wrapper.vm.saturationValue).toBe(22);
        expect(wrapper.vm.luminanceValue).toBe(32.2);
    });

    it('blue value setter should function properly', async () => {
        wrapper.vm.greenValue = 123;
        wrapper.vm.redValue = 89;

        expect(wrapper.vm.hueValue).toBe(94.58);
        expect(wrapper.vm.saturationValue).toBe(31.6);
        expect(wrapper.vm.luminanceValue).toBe(36.7);
    });

    it('should be the correct green value', async () => {
        await wrapper.setData({
            hueValue: 153,
            saturationValue: 78,
            luminanceValue: 57,
        });

        expect(wrapper.vm.greenValue).toBe(231);
    });

    it('should be the correct blue value', async () => {
        await wrapper.setData({
            hueValue: 153,
            saturationValue: 78,
            luminanceValue: 57,
        });

        expect(wrapper.vm.blueValue).toBe(154);
    });

    it('should be the correct rgb value', async () => {
        await wrapper.setData({
            hueValue: 85,
            saturationValue: 80,
            luminanceValue: 55,
            alphaValue: 0.67,
        });

        expect(wrapper.vm.rgbValue).toBe('rgba(156, 232, 48, 0.67)');
    });

    it('should set the correct red value', async () => {
        await wrapper.setProps({
            redValue: 25,
            greenValue: 25,
            blueValue: 25,
        });

        wrapper.vm.setSingleRGBValue(80, 'red');

        expect(wrapper.vm.redValue).toBe(80);
    });

    it('should set the correct green value', async () => {
        await wrapper.setProps({
            redValue: 25,
            greenValue: 25,
            blueValue: 25,
        });

        wrapper.vm.setSingleRGBValue(192, 'green');

        expect(wrapper.vm.greenValue).toBe(192);
    });

    it('should set the correct blue value', async () => {
        await wrapper.setProps({
            redValue: 25,
            greenValue: 25,
            blueValue: 25,
        });

        wrapper.vm.setSingleRGBValue(245, 'blue');

        expect(wrapper.vm.blueValue).toBe(245);
    });

    it('should split the rgb string correctly', async () => {
        const rgbValues = wrapper.vm.splitRGBValues('rgb(40, 50, 199)');

        expect(rgbValues.red).toBe(40);
        expect(rgbValues.green).toBe(50);
        expect(rgbValues.blue).toBe(199);
    });

    it('should be the correct hsl value', async () => {
        await wrapper.setData({
            hueValue: 176,
            saturationValue: 66,
            luminanceValue: 40,
        });

        expect(wrapper.vm.hslValue).toBe('hsl(176, 66%, 40%)');
    });

    it('should be the correct hsla value', async () => {
        await wrapper.setData({
            hueValue: 40,
            saturationValue: 33,
            luminanceValue: 13,
            alphaValue: 0.5,
        });

        expect(wrapper.vm.hslValue).toBe('hsla(40, 33%, 13%, 0.5)');
    });

    it('should set the correct hsla values', async () => {
        await wrapper.setData({
            hueValue: 40,
            saturationValue: 50,
            luminanceValue: 87,
            alphaValue: 0.77,
        });

        wrapper.vm.setHslaValues(145, 40, 946, 0.74);

        expect(wrapper.vm.hueValue).toBe(145);
        expect(wrapper.vm.saturationValue).toBe(40);
        expect(wrapper.vm.luminanceValue).toBe(946);
        expect(wrapper.vm.alphaValue).toBe(0.74);
    });

    it('should be the correct hex value', async () => {
        await wrapper.setData({
            hueValue: 341,
            saturationValue: 46,
            luminanceValue: 84,
        });

        expect(wrapper.vm.hexValue).toBe('#e9c3cf');
    });

    it('should validate the hex input', async () => {
        await wrapper.setData({
            hueValue: 275,
            saturationValue: 55,
            luminanceValue: 89,
        });
        wrapper.vm.hexValue = 'qwertz';
        await flushPromises();

        expect(wrapper.vm.hexValue).toBe('#e6d4f2');
    });

    it('should be the correct hex-alpha value', async () => {
        await wrapper.setData({
            hueValue: 341,
            saturationValue: 46,
            luminanceValue: 84,
            alphaValue: 0.4,
        });

        expect(wrapper.vm.hexValue).toBe('#e9c3cf66');
    });

    it('selector should have the right x co-ordinate', async () => {
        await wrapper.setData({
            saturationValue: 63,
        });

        expect(wrapper.vm.selectorPositionX).toBe('calc(63% - 9px)');
    });

    it('selector should have the right y co-ordinate', async () => {
        await wrapper.setData({
            luminanceValue: 32,
        });

        expect(wrapper.vm.selectorPositionY).toBe('calc(68% - 9px)');
    });

    it('colorValue should be a rgb value', async () => {
        await wrapper.setProps({
            colorOutput: 'rgb',
        });

        await wrapper.setData({
            hueValue: 180,
            saturationValue: 50,
            luminanceValue: 40,
        });

        expect(wrapper.vm.colorValue).toBe('rgb(51, 153, 153)');
    });

    it('colorValue should be a hsl value', async () => {
        await wrapper.setProps({
            colorOutput: 'hsl',
        });

        await wrapper.setData({
            hueValue: 0,
            saturationValue: 81,
            luminanceValue: 72,
        });

        expect(wrapper.vm.colorValue).toBe('hsl(0, 81%, 72%)');
    });

    it('colorValue should be a hex value', async () => {
        await wrapper.setProps({
            colorOutput: 'hex',
        });

        await wrapper.setData({
            hueValue: 149,
            saturationValue: 55,
            luminanceValue: 63,
        });

        expect(wrapper.vm.colorValue).toBe('#6dd59f');
    });

    it('colorValue should be a hex value if colorOutput is `auto`', async () => {
        await wrapper.setProps({
            colorOutput: 'auto',
        });

        await wrapper.setData({
            hueValue: 149,
            saturationValue: 55,
            luminanceValue: 63,
        });

        expect(wrapper.vm.colorValue).toBe('#6dd59f');
    });

    it('colorValue should be a rgba value if colorOutput is `auto`', async () => {
        await wrapper.setProps({
            colorOutput: 'auto',
        });

        await wrapper.setData({
            hueValue: 149,
            saturationValue: 55,
            luminanceValue: 63,
            alphaValue: 0.87,
        });

        expect(wrapper.vm.colorValue).toBe('rgba(109, 213, 159, 0.87)');
    });

    it('should add an event listener on the window', async () => {
        wrapper.vm.setOutsideClickEvent();
        expect(eventListener).toHaveProperty('mousedown');

        wrapper.vm.removeOutsideClickEvent();
    });

    it('should remove an event listener on the window', async () => {
        wrapper.vm.setOutsideClickEvent();
        wrapper.vm.removeOutsideClickEvent();

        expect(eventListener).not.toHaveProperty('mousedown');
    });

    it('should show only the input field without colorpicker', async () => {
        await wrapper.setData({ visible: false });
        const colorpicker = wrapper.find('.sw-colorpicker__colorpicker');

        expect(colorpicker.exists()).toBe(false);
    });

    it('should split rgb values correctly', async () => {
        const rgbValues = wrapper.vm.splitRGBValues('rgba(40, 242, 74, 0.8)');

        expect(rgbValues.red).toBe(40);
        expect(rgbValues.blue).toBe(74);
        expect(rgbValues.green).toBe(242);
        expect(rgbValues.alpha).toBe(0.8);
    });

    it('should show the color picker', async () => {
        await wrapper.find('.sw-colorpicker__previewWrapper').trigger('click');
        await flushPromises();

        const colorPicker = wrapper.find('.sw-colorpicker__colorpicker');

        expect(colorPicker.exists()).toBe(true);
    });

    it('should output in rgb', async () => {
        await wrapper.setProps({
            value: '#123123',
            colorOutput: 'rgb',
        });

        await wrapper.setData({
            visible: true,
        });

        expect(wrapper.vm.colorValue).toBe('rgb(18, 49, 35)');
    });

    it('should split hsl values correctly', async () => {
        const hslValues = wrapper.vm.splitHSLValues('hsla(67, 43%, 67%, 0.98)');

        expect(hslValues.hue).toBe(67);
        expect(hslValues.saturation).toBe(43);
        expect(hslValues.luminance).toBe(67);
        expect(hslValues.alpha).toBe(0.98);
    });

    it('should convert HSLA to RGBA', async () => {
        const hue = 201;
        const saturation = 46;
        const luminance = 51;
        const alpha = 0.27;

        const rgbValue = wrapper.vm.convertHSLtoRGB(hue, saturation, luminance, alpha);

        expect(rgbValue.red).toBe(73);
        expect(rgbValue.green).toBe(147);
        expect(rgbValue.blue).toBe(188);
        expect(rgbValue.alpha).toBe(0.27);
    });

    it('should convert Hsl to Hex', async () => {
        const hexValues = wrapper.vm.convertHSLtoHEX(75, 72, 65, 0.6);

        expect(hexValues).toBe('#c6e66599');
    });

    it('should convert Rgb to Hsl', async () => {
        const hslValues = wrapper.vm.convertRGBtoHSL(255, 23, 67, 0.8);

        expect(hslValues.hue).toBe(348.62);
        expect(hslValues.saturation).toBe(100);
        expect(hslValues.luminance).toBe(54.5);
    });

    it('should convert non-alpha Hex to Hsl', async () => {
        const hslValues = wrapper.vm.convertHEXtoHSL('#94c11f');

        expect(hslValues.hue).toBe(76.67);
        expect(hslValues.saturation).toBe(72.3);
        expect(hslValues.luminance).toBe(43.9);
        expect(hslValues.alpha).toBeNaN();

        const hslValues2 = wrapper.vm.convertHEXtoHSL('#95c11f');

        expect(hslValues2.hue).toBe(76.3);
        expect(hslValues2.saturation).toBe(72.3);
        expect(hslValues2.luminance).toBe(43.9);
        expect(hslValues2.alpha).toBeNaN();
    });

    it('should convert alpha Hex to Hsl', async () => {
        const hslValues = wrapper.vm.convertHEXtoHSL('#24db5b99');

        expect(hslValues.hue).toBe(138.03);
        expect(hslValues.saturation).toBe(71.8);
        expect(hslValues.luminance).toBe(50);
        expect(hslValues.alpha).toBe(0.6);
    });

    it('should call debounceEmitColorValue when props value changed', async () => {
        wrapper = await createWrapper({
            props: {
                value: '#94c11f',
            },
        });
        const spyDebounceMethod = jest.spyOn(wrapper.vm, 'debounceEmitColorValue');

        await wrapper.setProps({
            value: '#95c11f',
        });

        expect(spyDebounceMethod).toHaveBeenCalledTimes(1);
    });

    it('should rounding float correctly', async () => {
        const result1 = wrapper.vm.roundingFloat(1.23456789);

        expect(result1).toBe(1.23);

        const result2 = wrapper.vm.roundingFloat('1.23456789');

        expect(result2).toBe(1.23);

        const result3 = wrapper.vm.roundingFloat('1.23656789');

        expect(result3).toBe(1.24);

        const result4 = wrapper.vm.roundingFloat('1.26656789', 1);

        expect(result4).toBe(1.3);
    });

    it('should show the label from the property', async () => {
        wrapper = await createWrapper({
            props: {
                label: 'Label from prop',
            },
        });

        expect(wrapper.find('label').text()).toBe('Label from prop');
    });

    it('should show the value from the label slot', async () => {
        wrapper = await createWrapper({
            props: {
                label: 'Label from prop',
            },
            slots: {
                label: '<template>Label from slot</template>',
            },
        });

        expect(wrapper.find('label').text()).toBe('Label from slot');
    });

    it('should call moveSelector on dragging colorPicker', async () => {
        wrapper = await createWrapper();
        const moveSelectorSpy = jest.spyOn(wrapper.vm, 'moveSelector');

        await wrapper.setData({
            visible: true,
        });
        await flushPromises();

        const colorPicker = wrapper.find('.sw-colorpicker__colorpicker-selection');

        await colorPicker.trigger('mousedown');

        expect(moveSelectorSpy).toHaveBeenCalledTimes(1);
    });

    it('should call removeDragging on mouseup', async () => {
        wrapper = await createWrapper();

        const removeDragging = jest.spyOn(wrapper.vm, 'removeDragging');

        await wrapper.setData({
            visible: true,
        });
        await flushPromises();

        const colorPicker = wrapper.find('.sw-colorpicker__colorpicker-selection');
        await colorPicker.trigger('mousedown');

        expect(eventListener.mousemove).toBeDefined();
        expect(eventListener.mouseup).toBeDefined();

        eventListener.mouseup();
        expect(removeDragging).toHaveBeenCalledTimes(1);
    });

    it('should toggleColorPicker on clicking input', async () => {
        wrapper = await createWrapper();

        const toggleColorPicker = jest.spyOn(wrapper.vm, 'toggleColorPicker');

        await wrapper.setProps({
            readonly: false,
        });

        const colorInput = wrapper.find('.sw-colorpicker__input');

        expect(colorInput.exists()).toBeTruthy();

        await colorInput.trigger('click');

        expect(toggleColorPicker).toHaveBeenCalledTimes(0);

        await wrapper.setProps({
            readonly: true,
        });

        await colorInput.trigger('click');

        expect(toggleColorPicker).toHaveBeenCalledTimes(1);
    });

    const moveSelectorDataSet = [
        [
            150.55,
            150.7,
            150.4,
            150.2,
            0.08,
            99.5,
        ],
        [
            153,
            300,
            151.5,
            150,
            0.75,
            0,
        ],
        [
            154,
            -400,
            152,
            -150,
            1,
            100,
        ],
        [
            400,
            154,
            150,
            150,
            100,
            96,
        ],
        [
            400,
            300,
            150,
            150,
            100,
            0,
        ],
        [
            400,
            -400,
            150,
            -150,
            100,
            100,
        ],
        [
            -150,
            156.3,
            150,
            151.5,
            0,
            95.2,
        ],
        [
            -150,
            300,
            150,
            150,
            0,
            0,
        ],
        [
            -150,
            -400,
            150,
            -150,
            0,
            100,
        ],
    ];

    it.each(moveSelectorDataSet)(
        'should calculate luminanceValue and saturationValue correctly when moveSelector',
        async (clientX, clientY, left, top, expectedSaturationValue, expectedLuminanceValue) => {
            wrapper = await createWrapper();

            await wrapper.setData({
                visible: true,
                isDragging: true,
            });
            await flushPromises();

            const event = {
                clientX,
                clientY,
                preventDefault: jest.fn(),
            };

            jest.spyOn(wrapper.vm.$refs.colorPicker, 'getBoundingClientRect').mockImplementation(() => {
                return {
                    left,
                    top,
                    width: 200,
                    height: 100,
                };
            });

            wrapper.vm.moveSelector(event);

            expect(wrapper.vm.saturationValue).toEqual(expectedSaturationValue);
            expect(wrapper.vm.luminanceValue).toEqual(expectedLuminanceValue);
        },
    );

    it('should prevent default behavior on moveSelector event argument', async () => {
        wrapper = await createWrapper();

        const mockEvent = {
            preventDefault: jest.fn(),
        };

        expect(mockEvent.preventDefault).toHaveBeenCalledTimes(0);

        wrapper.vm.moveSelector(mockEvent);

        expect(mockEvent.preventDefault).toHaveBeenCalledTimes(1);
    });
});
