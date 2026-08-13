import template from './alias-shadow-blocker-component.html.twig';

const { Criteria } = Shopware.Data;

/**
 * The props default binds `Criteria` as a named function expression. JavaScript
 * reads that name as the function, so the alias must not be substituted — but
 * Vue's compiler-macro scope tracker does not count the self-binding and reads
 * it as the module-local, which `defineProps()` rejects. The component is
 * therefore blocked rather than migrated; the acceptance spec proves the build
 * transform is never handed the file.
 */
Shopware.Component.register('sw-alias-shadow-blocker', {
    template,

    props: {
        criteria: {
            type: Object,
            required: false,
            default: function Criteria() {
                return Criteria;
            },
        },
    },
});
