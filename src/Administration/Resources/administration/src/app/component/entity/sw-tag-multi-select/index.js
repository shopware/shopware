import { Component } from 'src/core/shopware';
import './sw-tag-multi-select.scss';
import utils from 'src/core/service/util.service';
import Criteria from 'src/core/data-new/criteria.data';
import template from './sw-tag-multi-select.html.twig';

Component.register('sw-tag-multi-select', {
    template,

    inject: ['repositoryFactory', 'context'],

    props: {
        entity: {
            type: Object,
            required: true
        },

        disabled: {
            type: Boolean,
            required: false,
            default: false
        },

        label: {
            type: Boolean,
            required: false,
            default: true
        },

        size: {
            type: String,
            required: false,
            default: 'default',
            validValues: ['default', 'small'],
            validator(value) {
                if (!value.length) {
                    return true;
                }
                return ['default', 'small'].includes(value);
            }
        }
    },

    computed: {
        classes() {
            return {
                'is--disabled': this.disabled,
                'is--expanded': this.isExpanded
            };
        }


    },

    data() {
        return {
            isLoading: false,
            isExpanded: false,
            tags: [],
            selectedTags: [],
            searchTerm: '',
            page: 1,
            limit: 25,
            total: 0,
            selectedLimit: 10,
            selectedTotal: 0,
            currentSelectedIndex: 0,
            activeOptionIndex: 0,
            selectedTagIds: {},
            showLoadMoreSelectedItems: true,
            selectedNextStep: 0,
            tagExists: true,
            recentlyAdded: false
        };
    },

    created() {
        this.createRepositories();
        this.loadTagList();
        this.loadSelectedTags(true);
        this.addEventListeners();
    },

    destroyed() {
        this.removeEventListeners();
    },

    methods: {
        addEventListeners() {
            this.$on('sw-multi-select-option-clicked', this.onOptionClick);
            this.$on('sw-multi-select-option-mouse-over', this.setActiveOption);
            document.addEventListener('click', this.onBlurField);
            document.addEventListener('keyup', this.onBlurField);
        },

        removeEventListeners() {
            document.removeEventListener('click', this.onBlurField);
            document.removeEventListener('keyup', this.onBlurField);
        },

        createRepositories() {
            this.tagRepository = this.repositoryFactory.create('tag');

            this.selectedRepository = this.repositoryFactory.create(
                this.entity.tags.entity,
                this.entity.tags.source
            );
        },

        loadTagList(clearList = false) {
            this.isLoading = true;

            const criteria = new Criteria(this.page, this.limit);
            if (this.searchTerm.trim().length) {
                criteria.addFilter(Criteria.contains('name', this.searchTerm.trim()));
            }

            this.tagRepository.search(criteria, this.context).then((searchResult) => {
                this.total = searchResult.total;
                const items = Object.values(searchResult.items);

                items.forEach((item) => {
                    item.name = this.highlight(item.name);
                });

                if (clearList) {
                    this.tags = [];
                }

                this.tags = [...this.tags, ...items];

                if (items.length === 0) {
                    this.isLoading = false;
                    return;
                }

                const selectedCriteria = new Criteria(1, this.limit);
                selectedCriteria.addFilter(Criteria.equalsAny('id', items.map((x) => { return x.id; })));

                this.selectedRepository.search(selectedCriteria, this.context).then((selectedSearchResult) => {
                    Object.values(selectedSearchResult.items).forEach((tag) => {
                        this.selectedTagIds[tag.id] = true;
                    });

                    this.isLoading = false;
                });
            });
        },

        loadSelectedTags(isInitial = false) {
            const criteria = new Criteria(1, this.selectedLimit);
            const currentSelectedIds = this.selectedTags.map((x) => { return x.id; });

            if (currentSelectedIds.length) {
                criteria.addFilter(Criteria.not('and', [Criteria.equalsAny('id', currentSelectedIds)]));
            }

            this.selectedRepository.search(criteria, this.context).then((searchResult) => {
                this.selectedTags = [...this.selectedTags, ...Object.values(searchResult.items)];
                if (isInitial) {
                    this.selectedTotal = searchResult.total;
                }

                this.calculateSelectedNextStep();
            });
        },

        addSelectedTag(tag) {
            tag.name = tag.name.replace(/<[^>]+>/g, '');
            this.selectedTags.push(tag);
            this.selectedTagIds[tag.id] = true;

            this.isLoading = true;
            this.selectedRepository.assign(tag.id, this.context).then(() => {
                this.isLoading = false;
            });

            this.emitAddSelection(tag);
        },

        checkTagExists() {
            if (this.searchTerm.trim().length === 0) {
                this.tagExists = true;
                return;
            }

            this.isLoading = true;

            const criteria = new Criteria(1, 1);
            criteria.addFilter(Criteria.equals('name', this.searchTerm.trim()));

            this.tagRepository.search(criteria, this.context).then((searchResult) => {
                this.isLoading = false;
                this.tagExists = searchResult.total > 0;
            });
        },

        getDistFromBottom(element) {
            return element.scrollHeight - element.clientHeight - element.scrollTop;
        },

        setActiveOption({ index }) {
            this.activeOptionIndex = index;
            this.emitActiveOption();
        },

        deleteSelectedTag(tag) {
            this.isLoading = true;

            delete this.selectedTagIds[tag.id];
            this.selectedTags = this.selectedTags.filter((x) => { return x.id !== tag.id; });
            this.selectedRepository.delete(tag.id, this.context).then(() => {
                this.isLoading = false;
                this.selectedTotal -= 1;
            });

            this.emitRemoveSelection(tag);
        },

        isSelected() {
            return false;
        },

        loadMoreSelected() {
            this.loadSelectedTags();
        },

        createNewTag() {
            const newTag = this.tagRepository.create(this.context);
            newTag.name = this.searchTerm.trim();

            this.tagRepository.save(newTag, this.context).then(() => {
                this.addSelectedTag(newTag);
                this.searchTerm = '';
                this.tagExists = true;
                this.loadTagList(true);
            });
        },

        highlight(text) {
            if (this.searchTerm.trim().length <= 0) {
                return text;
            }

            return text.replace(
                new RegExp(this.searchTerm.trim(), 'gi'),
                `<span class='is--highlighted'>${this.searchTerm.trim()}</span>`
            );
        },

        scrollToTop() {
            this.setActiveOption({ index: 0 });
            if (!this.$refs.swEntityTagSelect.querySelector('.sw-tag-multi-select__results')) {
                return;
            }

            this.$refs.swEntityTagSelect.querySelector('.sw-tag-multi-select__results').scrollTop = 0;
        },

        onSearchTermChange: utils.debounce(function checkTagExistsDebounced() {
            this.recentlyAdded = false;
            this.tagExists = true;
            this.page = 1;

            this.checkTagExists();
            this.loadTagList(true);
            this.scrollToTop();
        }, 400),

        onFocus() {
            this.$refs.swEntityTagSelectInput.focus();
        },

        openList() {
            if (this.isExpanded === false) {
                this.scrollToTop();
            }

            this.isExpanded = true;
        },

        onOptionClick(event) {
            if (event.item.index === -1) {
                this.recentlyAdded = true;
                this.createNewTag();
                return;
            }

            if (this.selectedTagIds[event.item.id]) {
                this.deleteSelectedTag(event.item);
                return;
            }

            this.addSelectedTag(event.item);
        },

        onScroll(event) {
            if (this.getDistFromBottom(event.target) !== 0) {
                return;
            }

            this.page += 1;
            this.loadTagList();
        },

        onBlurField(event) {
            if (event.type === 'keyup' &&
                event.key &&
                event.key.toLowerCase() !== 'tab' &&
                event.key.toLowerCase() !== 'escape') {
                return;
            }

            const target = event.target;

            if (target.closest('.sw-tag-multi-select') !== this.$refs.swEntityTagSelect) {
                this.isExpanded = false;
                this.activeResultPosition = 0;
                this.$refs.swEntityTagSelectInput.blur();
            }
        },

        onKeyEnter() {
            const selectedOption = this.$refs[`swSelectOption${this.activeOptionIndex}`];

            if (selectedOption && selectedOption.length === 0) {
                return;
            }

            if (selectedOption && selectedOption.length) {
                this.onOptionClick(selectedOption[0]);
                return;
            }

            this.onOptionClick({ item: { index: this.activeOptionIndex } });
        },

        onNavigateDown() {
            if (this.total === 0) {
                this.setActiveOption({ index: -1 });
                return;
            }

            if (this.activeOptionIndex >= this.total - 1) {
                return;
            }

            this.setActiveOption({ index: this.activeOptionIndex + 1 });

            const swSelectEl = this.$refs.swEntityTagSelect;
            const activeItem = swSelectEl.querySelector('.is--active');
            const itemHeight = swSelectEl.querySelector('.sw-multi-select-option').offsetHeight;

            if (!activeItem) {
                return;
            }

            const activeItemPosition = activeItem ? activeItem.offsetTop + itemHeight : 0;
            const resultContainer = swSelectEl.querySelector('.sw-tag-multi-select__results');
            let resultContainerHeight = resultContainer.offsetHeight;

            resultContainerHeight -= itemHeight;

            if (activeItemPosition > resultContainerHeight) {
                resultContainer.scrollTop += itemHeight;
            }
        },

        onNavigateUp() {
            if (this.total === 0) {
                this.setActiveOption({ index: -1 });
                return;
            }

            if (this.activeOptionIndex <= -1 || (this.activeOptionIndex === 0 && this.tagExists)) {
                return;
            }

            this.setActiveOption({ index: this.activeOptionIndex - 1 });

            const swSelectEl = this.$refs.swEntityTagSelect;
            const resultItem = swSelectEl.querySelector('.sw-multi-select-option');
            const resultContainer = swSelectEl.querySelector('.sw-tag-multi-select__results');

            if (!resultItem) {
                return;
            }

            resultContainer.scrollTop -= resultItem.offsetHeight;
        },

        onDelete() {
            if (this.searchTerm.trim().length > 0) {
                return;
            }

            if (!this.selectedTags.length) {
                return;
            }

            const lastSelection = this.selectedTags[this.selectedTags.length - 1];

            this.deleteSelectedTag(lastSelection);
        },

        calculateSelectedNextStep() {
            const nextStep = this.selectedTotal - this.selectedTags.length;

            this.selectedNextStep = nextStep > this.selectedLimit ? this.selectedLimit : nextStep;
        },

        emitActiveOption() {
            this.$emit('sw-multi-select-active-item-index', this.activeOptionIndex);
        },

        emitAddSelection(tag) {
            this.$emit('sw-tag-multi-select-add-selection', tag);
        },

        emitRemoveSelection(tag) {
            this.$emit('sw-tag-multi-select-remove-selection', tag);
        }
    }
});
