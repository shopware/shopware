[titleEn]: <>(LESS has been removed)
[__RAW__]: <>(__RAW__)

<ul>
	<li>LESS has been removed from the administration source code</li>
	<li>The duplicated LESS base variables are no longer available. Components LESS which uses base variables will not be functional.</li>
	<li>Please do not use LESS inside core components any longer because it is also no longer supported by the component library.</li>
	<li>However the package.json dependency has not been completely removed. External plugins should still have the posibility to use LESS. But we will recommend SCSS in our documentation.</li>
	<li>Some documentation markdown files may still include LESS examples. Those will be edited by the documentation squad soon.</li>
</ul>
