const { describeWithSelectedDeprecationIds } = require('../test-utils/selected-deprecation-ids');
const { rule, tester } = require('./tester');

describeWithSelectedDeprecationIds('component.sw-alert', () => {
    tester.run('no-deprecated-components with an unrelated deprecation id selected', rule, {
        valid: [
            {
                name: '"sw-button" is not reported when only another migration is selected',
                filename: 'test.html.twig',
                code: '<template><sw-button /></template>',
            },
        ],
        invalid: [],
    });
});
