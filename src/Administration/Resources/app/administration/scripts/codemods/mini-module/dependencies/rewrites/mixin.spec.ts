import { routeForSpec } from '../fake-index';
import { rewriteMixin } from './mixin';

describe('rewriteMixin', () => {
    it('imports each mixin from its file and keeps the order of mixins', () => {
        // ARRANGE
        const source = `import template from './sw-x-page.html.twig';

const { Mixin } = Shopware;

export default {
    template,
    mixins: [Mixin.getByName('placeholder'), Shopware.Mixin.getByName('notification')],
};
`;

        // ACT
        const { code, dependencies } = routeForSpec(source, { use: [rewriteMixin] });

        // ASSERT
        expect(code).toBe(`import template from './sw-x-page.html.twig';
import { notificationMixin, placeholderMixin } from '../dependencies';

export default {
    template,
    mixins: [placeholderMixin, notificationMixin],
};
`);
        expect(dependencies).toBe(`/** @private */
export { default as notificationMixin } from 'src/app/mixin/notification.mixin';
/** @private */
export { default as placeholderMixin } from 'src/app/mixin/placeholder.mixin';
`);
    });

    it('leaves a mixin looked up by a computed name or one without a file, and keeps Mixin for them', () => {
        // ARRANGE
        const source = `const { Mixin } = Shopware;

export default {
    mixins: [Mixin.getByName(name), Mixin.getByName('unknown'), Mixin.getByName('notification')],
};
`;

        // ACT
        const { code } = routeForSpec(source, { use: [rewriteMixin] });

        // ASSERT
        expect(code).toBe(`import { notificationMixin } from '../dependencies';

const { Mixin } = Shopware;

export default {
    mixins: [Mixin.getByName(name), Mixin.getByName('unknown'), notificationMixin],
};
`);
    });

    it('imports a private mixin module through its private.ts, on a line that says why', () => {
        // ARRANGE
        const source = `export default {
    mixins: [Shopware.Mixin.getByName('sw-settings-list')],
};
`;

        // ACT
        const { code, dependencies } = routeForSpec(source, { use: [rewriteMixin] });

        // ASSERT
        expect(code).toContain('mixins: [swSettingsListMixin],');
        expect(dependencies).toBe(`/** @private */
export { default as swSettingsListMixin } from 'src/module/sw-settings/mixin/sw-settings-list/private'; // eslint-disable-line sw-core-rules/mini-module-boundaries -- the mixin is private, extensions reach it with Mixin.getByName()
`);
    });
});
