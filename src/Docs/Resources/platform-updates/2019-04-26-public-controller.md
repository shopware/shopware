[titleEn]: <>(Public controllers)

In the past you had to extend the whitelist in the SalesChannelApi-/ApiAuthenticationLister 
if you wanted to create a controller which doesn't required a logged in user. 
Since it was almost impossible for third party developers to extend this list,
the behavior has changed. 
If you now want to create a public route you can do this with an annotation. Example:

```* @Route("/api/v{version}/_action/user/my-unprotected-route", defaults={"auth_required"=false})```