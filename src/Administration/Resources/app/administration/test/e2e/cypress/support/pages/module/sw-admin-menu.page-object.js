import elements from '../sw-general.page-object';

export default class MenuPageObject {
    constructor() {
        this.elements = {
            ...elements,
            ...{
                menuToggleAction: '.sw-admin-menu__toggle',
                languageAction: '.sw-admin-menu__change-language-action'
            }
        };
    }
}
