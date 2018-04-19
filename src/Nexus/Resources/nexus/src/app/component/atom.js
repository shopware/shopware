import swGrid from 'src/app/component/atom/grid/sw-grid';
import swGridBody from 'src/app/component/atom/grid/sw-grid-body';
import swGridCol from 'src/app/component/atom/grid/sw-grid-col';
import swGridHeader from 'src/app/component/atom/grid/sw-grid-header';
import swGridRow from 'src/app/component/atom/grid/sw-grid-row';
import swPagination from 'src/app/component/atom/grid/sw-pagination';

import swForm from 'src/app/component/atom/form/sw-form';
import swFormField from 'src/app/component/atom/form/sw-form-field';
import swFormRow from 'src/app/component/atom/form/sw-form-row';

import swLoader from 'src/app/component/atom/utils/sw-loader';

export default {
    // Grid components
    'sw-grid-body': swGridBody,
    'sw-grid-col': swGridCol,
    'sw-grid-header': swGridHeader,
    'sw-grid-row': swGridRow,
    'sw-pagination': swPagination,
    'sw-grid': swGrid,

    // Form components
    'sw-form-field': swFormField,
    'sw-form-row': swFormRow,
    'sw-form': swForm,

    // Utils components
    'sw-loader': swLoader
};
