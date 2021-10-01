import StateStyleService from 'src/app/service/state-style.service';

describe('src/app/service/state-style.service.js', () => {
    it('should be a function', () => {
        expect(typeof StateStyleService).toEqual('function');
    });

    it('should return a getPlaceholder function', () => {
        const stateStyleService = StateStyleService();
        expect(stateStyleService.hasOwnProperty('getPlaceholder')).toBe(true);
        expect(typeof stateStyleService.getPlaceholder).toEqual('function');
    });

    it('should return a addStyle function', () => {
        const stateStyleService = StateStyleService();
        expect(stateStyleService.hasOwnProperty('addStyle')).toBe(true);
        expect(typeof stateStyleService.addStyle).toEqual('function');
    });

    it('should return a getStyle function', () => {
        const stateStyleService = StateStyleService();
        expect(stateStyleService.hasOwnProperty('getStyle')).toBe(true);
        expect(typeof stateStyleService.getStyle).toEqual('function');
    });

    it('should return placeholder', () => {
        const stateStyleService = StateStyleService();
        const placeholder = stateStyleService.getPlaceholder();

        expect(typeof placeholder).toEqual('object');
        expect(placeholder.hasOwnProperty('icon')).toBe(true);
        expect(placeholder.icon).toEqual('small-arrow-small-down');
        expect(placeholder.hasOwnProperty('iconStyle')).toBe(true);
        expect(placeholder.iconStyle).toEqual('sw-order-state__bg-neutral-icon');
        expect(placeholder.hasOwnProperty('iconBackgroundStyle')).toBe(true);
        expect(placeholder.iconBackgroundStyle).toEqual('sw-order-state__bg-neutral-icon-bg');
        expect(placeholder.hasOwnProperty('selectBackgroundStyle')).toBe(true);
        expect(placeholder.selectBackgroundStyle).toEqual('sw-order-state__bg-neutral-select');
        expect(placeholder.hasOwnProperty('variant')).toBe(true);
        expect(placeholder.variant).toEqual('neutral');
        expect(placeholder.hasOwnProperty('colorCode')).toBe(true);
        expect(placeholder.colorCode).toEqual('#94a6b8');
    });

    it('should return placeholder for non existing state', () => {
        const stateStyleService = StateStyleService();
        const stateMachineForTesting = 'test-state-machine';

        stateStyleService.addStyle(
            stateMachineForTesting,
            'foo',
            {
                icon: 'danger',
                color: 'danger',
                variant: 'danger'
            }
        );

        const style = stateStyleService.getStyle(
            stateMachineForTesting,
            'bar'
        );

        expect(typeof style).toEqual('object');
        expect(style.hasOwnProperty('variant')).toBe(true);
        expect(style.variant).toEqual('neutral');
    });

    it('should return placeholder for non existing statemachine', () => {
        const stateStyleService = StateStyleService();
        const style = stateStyleService.getStyle(
            'none-existing-statemachine',
            'bar'
        );

        expect(typeof style).toEqual('object');
        expect(style.hasOwnProperty('variant')).toBe(true);
        expect(style.variant).toEqual('neutral');
    });

    it('should return desired style', () => {
        const stateStyleService = StateStyleService();
        const stateMachineForTesting = 'test-state-machine';
        const colorCodeMapping = {
            neutral: '#94a6b8',
            progress: '#189eff',
            done: '#37d046',
            warning: '#ffab22',
            danger: '#de294c'
        };

        const colorMapping = {
            neutral: 'neutral',
            progress: 'progress',
            done: 'success',
            warning: 'warning',
            danger: 'danger'
        };

        const iconMapping = {
            neutral: 'small-arrow-small-down',
            progress: 'small-default-circle-small',
            done: 'small-default-checkmark-line-small',
            warning: 'small-exclamationmark',
            danger: 'small-default-x-line-small'
        };

        const variantMapping = {
            neutral: 'neutral',
            progress: 'info',
            done: 'success',
            warning: 'warning',
            danger: 'danger'
        };

        Object.keys(variantMapping).forEach((key) => {
            stateStyleService.addStyle(
                stateMachineForTesting,
                key,
                {
                    icon: key,
                    color: key,
                    variant: key
                }
            );

            const style = stateStyleService.getStyle(stateMachineForTesting, key);

            expect(typeof style).toEqual('object');
            expect(style.hasOwnProperty('icon')).toBe(true);
            expect(style.icon).toEqual(iconMapping[key]);
            expect(style.hasOwnProperty('iconStyle')).toBe(true);
            expect(style.iconStyle).toEqual(`sw-order-state__${colorMapping[key]}-icon`);
            expect(style.hasOwnProperty('iconBackgroundStyle')).toBe(true);
            expect(style.iconBackgroundStyle).toEqual(`sw-order-state__${colorMapping[key]}-icon-bg`);
            expect(style.hasOwnProperty('iconBackgroundStyle')).toBe(true);
            expect(style.iconBackgroundStyle).toEqual(`sw-order-state__${colorMapping[key]}-icon-bg`);
            expect(style.hasOwnProperty('selectBackgroundStyle')).toBe(true);
            expect(style.selectBackgroundStyle).toEqual(`sw-order-state__${colorMapping[key]}-select`);
            expect(style.hasOwnProperty('variant')).toBe(true);
            expect(style.variant).toEqual(variantMapping[key]);
            expect(style.hasOwnProperty('colorCode')).toBe(true);
            expect(style.colorCode).toEqual(colorCodeMapping[key]);
        });
    });
});
