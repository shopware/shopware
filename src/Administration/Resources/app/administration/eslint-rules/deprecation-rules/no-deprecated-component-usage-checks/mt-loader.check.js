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
     * The Meteor component has identical functionality to the sw-loader component and
     * therefore no migration is needed.
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
     * The Meteor component has identical functionality to the sw-loader component and
     * therefore no migration is needed.
     **/
];

module.exports = {
    mtLoaderValidChecks,
    mtLoaderInvalidChecks,
    handleMtLoader
};
