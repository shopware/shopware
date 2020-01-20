import GeneralPageObject from '../sw-general.page-object';

export default class MenuPageObject extends GeneralPageObject {
    constructor() {
        super();

        this.elements = {
            ...this.elements,
            ...{
                menuToggleAction: '.sw-admin-menu__toggle',
                languageAction: '.sw-admin-menu__change-language-action'
            }
        };
    }
}
