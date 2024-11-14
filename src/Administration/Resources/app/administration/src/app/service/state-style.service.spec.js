/**
 * @package admin
 */

import StateStyleService from 'src/app/service/state-style.service';

describe('src/app/service/state-style.service.ts', () => {
    it('should be a function', async () => {
        expect(typeof StateStyleService).toBe('function');
    });

    it('should return a getPlaceholder function', async () => {
        const stateStyleService = new StateStyleService();

        expect(typeof stateStyleService.getPlaceholder).toBe('function');
    });

    it('should return a addStyle function', async () => {
        const stateStyleService = new StateStyleService();

        expect(typeof stateStyleService.addStyle).toBe('function');
    });

    it('should return a getStyle function', async () => {
        const stateStyleService = new StateStyleService();

        expect(typeof stateStyleService.getStyle).toBe('function');
    });

    it('should return placeholder', async () => {
        const stateStyleService = new StateStyleService();
        const placeholder = stateStyleService.getPlaceholder();

        expect(typeof placeholder).toBe('object');
        expect(placeholder.hasOwnProperty('icon')).toBe(true);
        expect(placeholder.icon).toBe('regular-chevron-down-xxs');
        expect(placeholder.hasOwnProperty('iconStyle')).toBe(true);
        expect(placeholder.iconStyle).toBe('sw-order-state__bg-neutral-icon');
        expect(placeholder.hasOwnProperty('iconBackgroundStyle')).toBe(true);
        expect(placeholder.iconBackgroundStyle).toBe('sw-order-state__bg-neutral-icon-bg');
        expect(placeholder.hasOwnProperty('selectBackgroundStyle')).toBe(true);
        expect(placeholder.selectBackgroundStyle).toBe('sw-order-state__bg-neutral-select');
        expect(placeholder.hasOwnProperty('variant')).toBe(true);
        expect(placeholder.variant).toBe('neutral');
        expect(placeholder.hasOwnProperty('colorCode')).toBe(true);
        expect(placeholder.colorCode).toBe('#94a6b8');
    });

    it('should return placeholder for non existing state', async () => {
        const stateStyleService = new StateStyleService();
        const stateMachineForTesting = 'test-state-machine';

        stateStyleService.addStyle(stateMachineForTesting, 'foo', {
            icon: 'danger',
            color: 'danger',
            variant: 'danger',
        });

        const style = stateStyleService.getStyle(stateMachineForTesting, 'bar');

        expect(typeof style).toBe('object');
        expect(style.hasOwnProperty('variant')).toBe(true);
        expect(style.variant).toBe('neutral');
    });

    it('should return placeholder for non existing statemachine', async () => {
        const stateStyleService = new StateStyleService();
        const style = stateStyleService.getStyle('none-existing-statemachine', 'bar');

        expect(typeof style).toBe('object');
        expect(style.hasOwnProperty('variant')).toBe(true);
        expect(style.variant).toBe('neutral');
    });

    it('should return desired style', async () => {
        const stateStyleService = new StateStyleService();
        const stateMachineForTesting = 'test-state-machine';
        const colorCodeMapping = {
            neutral: '#94a6b8',
            progress: '#189eff',
            done: '#37d046',
            warning: '#ffab22',
            danger: '#de294c',
        };

        const colorMapping = {
            neutral: 'neutral',
            progress: 'progress',
            done: 'success',
            warning: 'warning',
            danger: 'danger',
        };

        const iconMapping = {
            neutral: 'regular-chevron-down-xxs',
            progress: 'regular-circle-xxs',
            done: 'regular-checkmark-xxs',
            warning: 'regular-exclamation-s',
            danger: 'regular-times-xs',
        };

        const variantMapping = {
            neutral: 'neutral',
            progress: 'info',
            done: 'success',
            warning: 'warning',
            danger: 'danger',
        };

        Object.keys(variantMapping).forEach((key) => {
            stateStyleService.addStyle(stateMachineForTesting, key, {
                icon: key,
                color: key,
                variant: key,
            });

            const style = stateStyleService.getStyle(stateMachineForTesting, key);

            expect(typeof style).toBe('object');
            expect(style.hasOwnProperty('icon')).toBe(true);
            expect(style.icon).toEqual(iconMapping[key]);
            expect(style.hasOwnProperty('iconStyle')).toBe(true);
            expect(style.iconStyle).toBe(`sw-order-state__${colorMapping[key]}-icon`);
            expect(style.hasOwnProperty('iconBackgroundStyle')).toBe(true);
            expect(style.iconBackgroundStyle).toBe(`sw-order-state__${colorMapping[key]}-icon-bg`);
            expect(style.hasOwnProperty('iconBackgroundStyle')).toBe(true);
            expect(style.iconBackgroundStyle).toBe(`sw-order-state__${colorMapping[key]}-icon-bg`);
            expect(style.hasOwnProperty('selectBackgroundStyle')).toBe(true);
            expect(style.selectBackgroundStyle).toBe(`sw-order-state__${colorMapping[key]}-select`);
            expect(style.hasOwnProperty('variant')).toBe(true);
            expect(style.variant).toEqual(variantMapping[key]);
            expect(style.hasOwnProperty('colorCode')).toBe(true);
            expect(style.colorCode).toEqual(colorCodeMapping[key]);
        });
    });
});
