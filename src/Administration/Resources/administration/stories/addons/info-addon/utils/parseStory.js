import findSlots from './findSlots';
/**
 * Parses the storybook story and strips out additional information about the component.
 *
 * @param {Object} story
 * @returns {Array}
 */
function parseStory(story) {
    if (!story.template) {
        throw new Error('A component needs a template to generate the "Usage"');
    }
    const localComponents = story.components;

    let componentsCollection = Object.keys(localComponents).reduce((components, componentName) => {
        const component = localComponents[componentName];

        // Name doesn't matches the component name
        if (component.name === componentName) {
            return components;
        }

        const options = component.options;

        components.push({
            name: options.name,
            props: options.props,
            description: options.description || story.description,
            slots: findSlots(options.template),
            dependencies: options.inject
        });

        return components;
    }, []);

    // When we're just dealing with one component, flatten the array.
    if (componentsCollection.length >= 1) {
        componentsCollection = componentsCollection[0];
    }

    return componentsCollection;
}

export default parseStory;
