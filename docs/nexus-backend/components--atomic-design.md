# Shopware Backend Components

## Introduction
The Shopware backend view is structured by several smaller pieces. Every part is build from different reusable elements. Starting by simple elements like a button composing up to larger modules like the product editing. So basically everything in the Shopware backend is a component existing out of smaller components. There is a common and very popular design pattern out there which describes this architecture very precise - the "Atomic Design" by Brad Frost. If you want to learn more about this design principle you can visit [atomicdesign.bradfrost.com](http://atomicdesign.bradfrost.com/) for more information.

## Atomic Design
All the components of the Shopware backend are sorted into the different levels of the atomic design, describing its functionality for the whole application.

### Atoms
The atoms are the smallest type of component. They fulfill a single function in a larger layout. Mostly they exist of just one or two HTML elements displaying some information or providing a single action for the user. These elements are the basic material of what larger components are build of. They form the basic toolkit for building your application.

**Example atoms:**
- buttons
- labels
- simple form elements

### Molecules
Sometimes you need elements which are a combination of smaller parts. These components are called molecules. They combine single atoms to create more complex components which have to achieve a very specific functionality. 

**Example molecules:**
- advanced form elements
- toolbars

### Organisms
When you build up your layout out of different atoms and molecules they form a bigger component providing a larger set of features. Often these components could be treated as a whole application because they could exist nearly by itself. But these components are still reusable and could also be used in different contexts. These are called organisms.

**Example organisms:**
- forms
- grid and data views
- sidebar

### What about templates and pages?
So when you already know a little bit about the atomic design principle you might wonder which components form the last two types of patterns? For the Shopware backend we decided that the segmentation into atoms, molecules and organisms is the perfect amount of categorization for our component set. We wanted the architecture to be as simple but also as structured as possible. You as a developer should not spent so much time about where your components have to be stored, rather then having easy access to a powerful toolkit. So these three categories should do all what we want. But of course, as every part of the backend is a component the principle does not stop here. As you go along you will use these components to build up a complete module for the backend. And also these modules which form at least the different parts of the backend are treated as components. So following the atomic design pattern the modules could be seen as our templates. And as several templates form a page you could see the whole backend as our main page.

## Creating components
Before you create your own component first you should think about in which type the component should be categorized. The previous description should help you with the decision. Every component consists of up to three files. The component, its template and its styling. The files should be created in a single directory named by your component.

**Example component structure:**
```
- atom
-- component-name
--- index.js
--- component-name.html.twig
--- component-name.less
```

### The component
For creating a component you will register it at the main component factory of the shopware application. This factory will collect all the components and will pass them to the specific view adapter. In the Shopware standard the view layer is rendered by the Vue.js framework, so at least your component is a Vue component and you can use all native features the framework provides with additional features by Shopware. You can visit [vuejs.org](https://vuejs.org/) for more information and a complete documentation of the framework.

When you register your component you have to define a unique name for it. This name defines a custom HTML tag which can be used in other layouts to use this component. The name should be as short and precise as possible and should only use a `-` for delimiters. All Shopware core components will use the `sw-` name prefix as a kind of namespace.

Every component needs an `index.js` file as the main file. In this file you will also import the template and styling of the component. The complete file for a component could look something like this:

```
import './my-component.less';
import template from './my-component.html.twig'; 

export default Shopware.ComponentFactory.register('my-component', {

    // Your component logic

    template
});
```

You see that we use the EcmaScript 6 language set with all the advantages it offers. The files will later be compiled by the webpack build process.

### The template
Your template file is used as the template for the Vue component, so you can use all features the framework offers. But there are also some conventions for building the template. The first and most important thing is, that every component must start with a single main element containing the rest of the template. This element should also have a at least one specific CSS selector which is the name of the component.

As you might noticed the template file is a `.twig` file and can contain some twig template code. This is because we are using the block system technique of the framework for the template inheritance. In Shopware it is still very important that every part of the backend can be changed by other modules and third party plugins. As these changes can be done in several inheritance layers the block system is the most capable solution for this. Other than the frontend templates these Twig files are not rendered on the server side. We are using the JavaScript implementation for this so the templates are rendered dynamically on the client side. We are only using the block system syntax for the inheritance. All other features of Twig cannot be used. The data binding etc. will be done by the Vue framework. As the Twig and the Vue framework share some of the syntax we had to change the `{parent()}` method of Twig to another expression. You can print out the content of a parent block with `{% parent %}`.

So when you create a core component don't forget to add Twig blocks to your code to make the template of your component extendable.

### The styling
We decided to also use the same technology from the frontend styling for the backend components. Therefore we use LESS as a preprocessor. When you import your lESS file in the main file of your component it will automatically be compiled by webpack to the global styling of the backend. As a component present a single encapsulated element the styling of it should also just contain the styling for itself and the child elements in it. Your component should never change the state of the global styles. To do so you will add the main CSS selector you hopefully added to your main HTML element as a parent selector for all other style definitions.

```
.my-component {
    // Style definitions for the main element
    
    .some-child-element {
        // Style definitions for child elements
    }
}
```