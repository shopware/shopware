/** @param {RuleContext} context
 *  @param {VElement} node
 */
const handleMtLoader = (context, node) => {
    const mtLoaderComponentName = 'mt-loader';

    // Refactor the old usage of sw-loader-field to mt-loader after the migration to the new component
    if (node.name !== mtLoaderComponentName) {
        return;
    }

    /**
     * The props of the Meteor component match the ones of the sw-loader component.
     * Only the backdrop differs, which the rule "no-sw-loader-usage" turns off while it migrates sw-loader.
     **/
}

const mtLoaderValidChecks = [
    {
        name: '"sw-loader" usage is allowed',
        filename: 'test.html.twig',
        code: `
            <template>
                <sw-loader></sw-loader>
            </template>
        `,
    }
]
const mtLoaderInvalidChecks = [
    /**
     * The props of the Meteor component match the ones of the sw-loader component.
     * Only the backdrop differs, which the rule "no-sw-loader-usage" turns off while it migrates sw-loader.
     **/
];

module.exports = {
    mtLoaderValidChecks,
    mtLoaderInvalidChecks,
    handleMtLoader
};
