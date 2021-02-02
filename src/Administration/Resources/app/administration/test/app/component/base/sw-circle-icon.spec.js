import { mount } from '@vue/test-utils';
import 'src/app/component/base/sw-circle-icon';
import 'src/app/component/base/sw-label';

describe('components/base/sw-circle-icon', () => {
    let wrapper;

    afterEach(() => {
        if (wrapper) {
            wrapper.destroy();
        }
    });

    const stubs = {
        'sw-label': Shopware.Component.build('sw-label'),
        'sw-icon': true
    };

    function createWrapper(propsData) {
        return mount(Shopware.Component.build('sw-circle-icon'), {
            propsData,
            stubs
        });
    }

    it('passes default values', () => {
        wrapper = createWrapper({
            iconName: 'default-basic-checkmark-line'
        });

        const swLabel = wrapper.findComponent(stubs['sw-label']);

        expect(swLabel.props('variant')).toBe('');
        expect(swLabel.props('appearance')).toBe('circle');
        expect(swLabel.props('dismissable')).toBe(false);

        const { width, height } = wrapper.element.style;

        expect(width).toBe('50px');
        expect(height).toBe('50px');

        expect(wrapper.get('sw-icon-stub').attributes('name')).toBe('default-basic-checkmark-line');
        expect(wrapper.get('sw-icon-stub').attributes('size')).toBe('25px');
    });

    it('it passes variant correctly', async () => {
        wrapper = createWrapper({
            iconName: 'default-basic-checkmark-line',
            variant: 'danger'
        });

        const swLabel = wrapper.findComponent(stubs['sw-label']);

        expect(swLabel.props('variant')).toBe('danger');
    });

    it('passes size correctly', () => {
        const size = 72;

        wrapper = createWrapper({
            iconName: 'default-basic-checkmark-line',
            size
        });

        const { width, height } = wrapper.element.style;

        expect(width).toBe(`${size}px`);
        expect(height).toBe(`${size}px`);

        expect(wrapper.get('sw-icon-stub').attributes('size')).toBe(`${size / 2}px`);
    });
});
