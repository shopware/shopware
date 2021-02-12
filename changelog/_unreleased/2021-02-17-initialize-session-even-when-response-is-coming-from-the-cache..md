---
title: Initialize session even when response is coming from the cache.
issue: NEXT-13808
author: Tommy Quissens
author_email: tommy.quissens@gmail.com 
author_github: Tommy Quissens
___
# Storefront
*  Added `startSession` before sending the response, to make sure there is a proper session 
___
