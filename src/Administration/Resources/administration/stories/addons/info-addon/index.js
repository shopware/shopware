import InfoViewComponent from './components/InfoView';
import parseTemplateWithPrism from './utils/parseTemplateWithPrism';
import getPropsList from './utils/getPropsList';
import parseStory from './utils/parseStory';
import validationService from '../../../src/core/service/validation.service';

/**
 * Info panel decorator which wraps around the storybook output. It provides additional information
 * about the component including the title, property list, a usage template and a component description.
 *
 * @param {Function} storyFn
 * @returns {{Function}}
 * @constructor
 */
const SwagVueInfoAddon = (storyFn) => {
    const story = storyFn();

    const component = parseStory(story);

    return {
        provide: {
            validationService: validationService
        },
        render: (h) => {
            return h(InfoViewComponent, {
                props: {
                    title: component.name,
                    propsList: getPropsList(component.props || null),
                    template: parseTemplateWithPrism(story.template),
                    description: component.description,
                    lessDescription: parseTemplateWithPrism(component.lessDescription || '', 'less'),
                    slots: component.slots
                },
                scopedSlots: {
                    default() {
                        return h(story);
                    }
                }
            });
        }
    };
};

export default SwagVueInfoAddon;
