---
title: Implement sales channel context token requirement for store-api and sales-channel-api
date: 2020-07-02
area: core
tags: [context, token, sales-channel, store-api, sales-channel-api, api]
---

## Context
Some routes for the sales-channel-api and the store-api depend on a sales-channel-context-token to identify the correct context.
To ensure these routes cannot be called accidentally or intentionally without a token, a route parameter is in need to distinguish open routes and those that need a token.

## Decision
Every route that depends on a sales-channel-token will only be callable with such a token provided. 
To decide whether a route depends on a token or not the following questions should help:  

* Will the automatic generation of the token be a security Issue?
* Will the automatic generation of the token lead to an abandoned entity? (e.g. the cart)
* Can every possible caller create or know the needed token beforehand? (e.g. the asynchronous payment provider cannot) 


## Consequences
From now on, every sales-channel-api and store-api route need to be checked for above question and set the `ContextTokenRequired` annotation (`Shopware\Core\Framework\Routing\Annotation\ContextTokenRequired`). 

## Counter decisions
Another decision could be to just leave the routes open. There is currently no security issue associated with context-less calls.
When a call is made without a sales-channel-token, one will be generated with the default sales-channel-context.
The least thing that could happen, is that someone adds an entity (e.g. a cart or a customer) accidentally to the default sales-channel-context instead of a desired custom sales-channel-context.
