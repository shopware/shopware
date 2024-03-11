---
title: Storefront coding standards
date: 2021-08-10
area: storefront
tags: [storefront, coding-standards, architecture]
---

## Context

* The current coding standards are not put into an ADR yet.
* This ADR is to determine the current standards to have a start from where to enhance the storefront concept further more.

## Decision

### Routes annotations
Route annotations respect the following schema:
example:
```php
#[Route(path: '/example/endpoint/{id}', name: 'frontend.example.endpoint', options: ['seo' => false], defaults: ['id' => null, 'XmlHttpRequest' => true, '_loginRequired' => true, '_httpCache' => true], methods: ['GET', 'POST', 'DELETE'])]
```
* `path`: The path of the route. Parameters inside the path will be noted in {} brackets.
* `name`: A unique name for the route beginning with `frontend.`
* `options`: Options for the route to be determined for special cases. Currently `seo` = (true|false) is the only option.
* `defaults`: A set of default parameter for the Route. Either preconfigured parameters or any route query/path parameter can be defined as a default here.
  * `id` (any value): Stands as an example for any path parameter. Can be any noted parameter and the value is used, if it is not set by the request
  * `XmlHttpRequest` (true|false): If the route is an XmlHttpRequest which is normally called by a frontend ajax request. These Routes don't return the `renderStorefront` call.
  * `_loginRrequired` (true|false): Is a logged in user is required to call this route? Otherwise a "permission denied" redirect will be returned.
  * `__httpCache` (true|false): Should this route be cached in the httpCache?
* `methods`: One or more of the HTTP methods ('GET', 'POST', 'DELETE')
  * `GET`: A request which returns data or a html page.
  * `POST`: A request which sends data to the server.
  * `DELETE`: A request which deletes data on the server. 

### Controller
* Each controller requires a Route annotation with a path, name and method: #[Route(path: '/xxx', name: 'frontend.xxx.page', methods: ['GET', 'POST'])]
* The name of the route has to be starting with "frontend"
* Each route has to define the corresponding HTTP Method (GET, POST, DELETE, PATCH)
* Routes which renders pages for the storefront (GET calls) are calling a respective pageloader to get the data it needs. 
* The function name should be concise
* Each function has to define a return type hint
* A route only have a single purpose
* Use Symfony flash bags for error reporting to the frontend user
* Each storefront functionality has to be available inside the store-api too
* A storefront controller should never contain business logic
* The controller class requires the annotation: #[Route(defaults: ['_routeScope' => ['storefront']])]
* Depending services has to be injected over the class constructor. The only exceptions are the container and twig, which can be injected with the methods `setContainer` and `setTwig`
* Depending services has to be defined in the DI-Container service definition
* Depending services has to be assigned to a private class property
* Each storefront controller needs to be declared as a public service. (otherwise the routes can be removed from the container)
* A storefront controller has to extend the \Shopware\Storefront\Controller\StorefrontController
* Using _loginRequired=true defaults parameter to identify whether the Customer is logged in or not.
* Each storefront functionality needs to make use of a store-api route service. This is to make sure, this functionality is also available via API

### Operations inside Storefront controllers
A storefront controller should never use a repository directly, It should be injected inside a Route.

Routes which should load a full storefront page, should use a PageLoader class to load all corresponding data that returns a Page-Object.

Pages which contains data which are the same for all customers, should have the _httpCache=true defaults parameter in the Routes annotation.

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
