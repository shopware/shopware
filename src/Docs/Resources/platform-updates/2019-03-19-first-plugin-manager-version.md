[titleEn]: <>(First plugin-manager version)
[__RAW__]: <>(__RAW__)

<p>The first Plugin-Manager version is now merged, but it&#39;s behind the <strong>NEXT-1223</strong> feature flag.</p>

<p>Before you can use the Plugin-Manager, you have to set your host in the shopware.yml file. Additionally, you have to change the Framework Version in Framework.php to a version that the SBP knows.</p>

<p>You can now upload zips, install, deinstall, update, activate and deactivate plugins in the Administration instead of using the CLI.</p>

<p>Furthermore, it is possible to download and update plugins directly from the Community Store if you have a license for that plugin in your account and you are logged with your Shopware ID.</p>

<h3>2019-03-19: Salutations</h3>

<p>We changed the salutation property of <strong>Customer</strong>, <strong>CustomerAddress</strong>, <strong>OrderCustomer</strong> and <strong>OrderAddress</strong> from <strong>StringField</strong> to a reference of the <strong>SalutationEntity</strong>. Since this property is now required to all of these entities, you have to provide a salutationId. In some cases these constants can be helpful for that:</p>

<ul>
	<li>Defaults::SALUTATION_ID_MR</li>
	<li>Defaults::SALUTATION_ID_MRS</li>
	<li>Defaults::SALUTATION_ID_MISS</li>
	<li>Defaults::SALUTATION_ID_DIVERSE</li>
	<li>Defaults::SALUTATION_KEY_MR</li>
	<li>Defaults::SALUTATION_KEY_MRS</li>
	<li>Defaults::SALUTATION_KEY_MISS</li>
	<li>Defaults::SALUTATION_KEY_DIVERSE</li>
</ul>

<p>Additionally you can now easily format a full name with salutation, title and name using either the salutation mixin via <strong>salutation(entity, fallbackString) </strong>or the filter in the twig files via e.g. <strong>{{ customer | salutation }}</strong>or <strong>{{ customer | salutation(fallbackString) }}</strong>. The only requirement for that is to use an entity like Customer which contains firstname, lastname, title and/or a salutation.</p>
