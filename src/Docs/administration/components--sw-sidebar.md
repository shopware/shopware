# sw-sidebar

The `<sw-sidebar>` component contains the main navigation elements for the administration as well as the user actions.

## General structure

The general structure consists of three main parts: 
- **Header:** Logo, shop title and shop status
- **Body:** Main navigation elements. This element is scrollable because it may contain more navigation entries in the future.
- **Footer:** User actions

```
<aside class="sw-sidebar">
  <div class="sw-sidebar__header">
    <!-- Logo and shop title -->
  </div>
  
  <div class="sw-sidebar__body">
    <!-- Main sidebar content, navigation -->
  </div>
  
  <div class="sw-sidebar__footer">
    <!-- User actions -->
  </div>
</aside>
```

## Navigation and body elements

The `sw-sidebar__body` part can contain multiple navigation elements.

### Navigation example:

```
<nav class="sw-sidebar__navigation">
    <ul class="sw-sidebar__navigation-list">
        <sw-sidebar-item v-for="entry in mainMenuEntries"
                         :entry="entry"
                         :key="entry.path">
        </sw-sidebar-item>
    </ul>
</nav>
```

### Secondary navigation example:

```
<nav class="sw-sidebar__navigation sw-sidebar__navigation--secondary">
    <!-- Navigation -->
</nav>
```
The secondary navigation has slightly different styling.

### Headline example:
```
<div class="sw-sidebar__headline">
    <!-- Headline text -->
    <div class="collapsible-text">Headline text</div>
    
    <!-- Headline action icon (optional) -->
    <button class="sw-sidebar__headline-action">
        <sw-icon class="sw-sidebar__headline-icon" 
                 name="small-default-plus-circle" 
                 small>
        </sw-icon>
    </button>
</div>
```