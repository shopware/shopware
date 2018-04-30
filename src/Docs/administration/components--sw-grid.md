# sw-grid

The `<sw-grid>` and it's siblings are representation a multi purpose grid component which is used throughout the administration.
It features built-in column sorting, inline editing and pagination.

## General structure 
The `sw-grid` is separated in multiple components e.g. `sw-grid` itself, `sw-grid-row` (private component) and `sw-grid-column`.

The `sw-grid` component takes care of creating. It fires all the necessary events to interact with the grid and provides.

```
<sw-grid
   :items="<array-of-items>"
   :pagination="<true | false>"
   :sortBy="<data-index>"
   :sortDirection="<ASC | DESC>"
   :sidebar="<true | false>">
</sw-grid>
```

## Defining columns
We're using [scoped slots](https://vuejs.org/v2/guide/components-slots.html#Scoped-Slots) to define the columns using the `sw-grid-column` component. The slot provides you with the currently item in the loop.

```
<template slot="columns" slot-scope="{ item }">
	<sw-grid-column label="name">
		{{ item.name }}
	</sw-grid-column>
</template>
```

## Aligning column content
The default of the `sw-grid-column` component is "left", therefore the content will be aligned to the left in the column. You can change this behaviour using the `align` property:

```
<sw-grid-column label="Name" align="left">
	{{ item.name }}
</sw-grid-column>

<sw-grid-column label="Price" align="right">
	{{ item.price.gross }}
</sw-grid-column>

<sw-grid-column label="Active" align="center">
	<sw-icon name="small-default-checkmark-line-medium">
</sw-grid-column>
```

## Column sizing
The sizing of the `sw-grid-column` component can be customized to your needs. To do so, the component provides you with the property called `flex`. You can either set a relative value or a static pixel value.

```
<sw-grid-column label="Name" flex="2">
	{{ item.name }}
</sw-grid-column>

<sw-grid-column label="Price" flex="1">
	{{ item.price.gross }}
</sw-grid-column>

<sw-grid-column label="Active" flex="50px">
	<sw-icon name="small-default-checkmark-line-medium">
</sw-grid-column>
```

## Enable a pagination
We're having a special component called `sw-pagination` which can be slotted into the grid component to enable a pagination for the grid. Just make sure you're setting the attribute `:pagination` on the `sw-grid` component to `true`:

```
<sw-grid
   :items="items
   :pagination="true">
   
   <template slot="pagination">
		<sw-pagination :offset="offset"
		   :limit="limit"
		   :total="totalProducts"
		   :total-visible="7"
		   @page-change="pageChange">
		</sw-pagination>
   </template>
</sw-grid>
```
The `sw-pagination` component will fire the `page-change` when the page or the items per page was changed by the user.

## Enable sorting
The grid can be sorted as well. To enable it you just have to set the properties `sortBy` and `sortDirection` on the `sw-grid` component. These properties defining the default sorting as well as the default sorting direction.

In your column defining you can configure on each `sw-grid-column` which column should be sortable and which data index should be used for the sorting on the server side.

```
<sw-grid
   :items="items
   :sortBy="sortBy"
   :sortDirection="sortDirection"
   @sort-column="onSortColumn">
   ...
</sw-grid>
```

```
<sw-grid-column label="Name" sortable dataIndex="name">
	{{ item.name }}
</sw-grid-column>

<sw-grid-column label="Manufacturer" dataIndex="manufacturer.name" sortable>
	{{ item.manufacturer.name }}
</sw-grid-column>
```

The grid component will fire an event called `sort-column` when the user clicks on a column header to re-sort the grid.

## Enable inline editing
The inline editing feature can be enabled right now the `sw-grid-column` component. The private `sw-grid-row` component takes care of enabling / disabling the inline editing per row. The editor (e.g. the field) can be configured using a slot called `inline-edit

```
<sw-grid-column label="name" editable>
	{{ item.name }}
	
	<sw-field type="text" slot="inline-edit" v-model="item.name">
</sw-grid-column>
```

The `sw-grid` component will fire the event `inline-edit-finish` when the user presses the `Save changes` button.


## Full example

```
<sw-grid
   :items="items
   :pagination="true"
   :sortBy="sortBy"
   :sortDirection="sortDirection"
   @sort-column="onSortColumn"
   @inline-edit-finish="onInlineEditSave">
   
   <template slot="columns" slot-scope="{ item }">
		<sw-grid-column label="Name" editable sortable dataIndex="name" flex="2">
			{{ item.name }}
			
			<sw-field type="text" slot="inline-edit" v-model="item.name">
		</sw-grid-column>
		
		<sw-grid-column label="Manufacturer" editable sortable dataIndex="manufacturer.name" flex="1">
			{{ item.manufacturer.name }}
			
			<sw-field type="text" slot="inline-edit" v-model="item.manufacturer.name">
		</sw-grid-column>
	</template>
   
   <template slot="pagination">
		<sw-pagination :offset="offset"
		   :limit="limit"
		   :total="totalProducts"
		   :total-visible="7"
		   @page-change="pageChange">
		</sw-pagination>
   </template>
</sw-grid>
```
