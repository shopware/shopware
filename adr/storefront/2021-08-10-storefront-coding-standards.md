# 2021-08-10 - Storefront coding standars

## Context

* The current coding standards are not put into an ADR yet.
* This ADR is to determine the current standards to have a start from where to enhance the storefront concept further more.

## Decision

### Controller
* Each controller action has to be declared with a @Since tag
* Each controller action requires a @Route annotation
* The name of the route should be starting with "frontend"
* Each route should define the corresponding HTTP Method (GET, POST, DELETE, PATCH)
* Routes which renders pages for the storefront (GET calls) are calling a respective pageloader to get the data it needs. 
* The function name should be concise
* Each function should define a return type hint
* A route should have a single purpose
* Use Symfony flash bags for error reporting
* Each storefront functionality has to be available inside the store-api too
* A storefront controller should never contain business logic
* The controller class requires the annotation: @RouteScope(scopes={"storefront"})
* Depending services has to be injected over the class constructor
* Depending services has to be defined in the DI-Container service definition
* Depending services has to be assigned to a private class property
* A storefront controller has to extend the \Shopware\Storefront\Controller\StorefrontController
* Using LoginRequired annotation to identify whether the Customer is logged in or not.
* Each storefront functionality needs to make use of a store-api route service. to make sure, this functionality is also available via API

### Operations inside Storefront controllers
A storefront controller should never use a repository directly, It should be injected inside a Route.

Routes which should load a full storefront page, should use a PageLoader class to load all corresponding data that returns a Page-Object.

Pages which contains data which are the same for all customers, should have the @HttpCache annotation

#### Write operations inside Storefront controllers
Write operations should create their response with the createActionResponse function to allow different forwards and redirects.
Each write operation has to call a corresponding store-api route.

### Page-/PageletLoader
* A PageLoader is a class which creates a page-object with the data for the called whole page.
* A PageletLoader is a class which creates a pagelet-object with the data for a part of a page. 

The pageLoaders are a specific class to load the data for a given page.
The controller calls the pageloader, which collects the needed data for that page via the Store-api.
The pageloader can call other pageletloaders to get the data for pagelets(subcontent for a page).
The pageloader always returns a page-object.


## Consequences

All dependencies in the controllers for routes which render a page have to be moved to the `Loaders` and if still missing, the `Loader` and `Page` has to be created.
All direct DAL-dependencies inside the storefront have to be moved to Store-Api routes and respective calls.
All other dependencies which are not allowed have to be checked for individual alternatives

