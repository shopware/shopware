[titleEn]: <>(Validation / input validation)
[__RAW__]: <>(__RAW__)

<p><strong>#1 Request / Query data</strong><br />
Request data from either the body (POST) or from the query string (GET) is now wrapped in a <strong>DataBag</strong>. It&#39;s an extension to Symfony&#39;s <strong>ParameterBag</strong> with some sugar for arrays. This allows you to access nested arrays more fluently:</p>

<pre>
// before:
$bag-&gt;get(&#39;billingAddress&#39;)[&#39;firstName&#39;]

</pre>

<p>&nbsp;</p>

<pre>
// after:
$bag-&gt;get(&#39;billingAddress&#39;)-&gt;get(&#39;firstName&#39;)</pre>

<p><br />
To prevent boilerplate code like <strong>new DataBag($request-&gt;request-&gt;all()); </strong>you can type-hint the controller arguments to either <strong>RequestDataBag</strong> and <strong>QueryDataBag</strong> which automatically creates a DataBag with the data from the request.</p>

<p><strong>#2 DataValidation</strong><br />
We leverage Symfony&#39;s validation constraints for our input validation. They already implement a ton of constraints like NotBlank, Length or Iban and they provide a documented way on how to add custom constraints.</p>

<p><strong>#2.1 DATA VALIDATION DEFINITION</strong><br />
We&#39;ve introduced a DataValidationDefinition which contains the validation constraints for a given input.</p>

<p><strong>Example</strong></p>

<pre>
$definition = new DataValidationDefinition(&#39;customer.update&#39;);
$definition-&gt;add(&#39;firstName&#39;, new NotBlank())
    -&gt;add(&#39;email&#39;, new NotBlank(), new Email())
    -&gt;add(&#39;salutationId&#39;, new NotBlank(), new EntityExists(&#39;entity&#39; =&gt; &#39;salutation&#39;, &#39;context&#39; =&gt; $context));
    
// nested validation
$billingValidation = new DataValidationDefinition(&#39;billing.update&#39;);
$billingValidation-&gt;add(&#39;street&#39;, new NotBlank());

$definition-&gt;addSub(&#39;billingAddress&#39;, $billingDefinition);
You can now pass the definition with your data to the DataValidator which does the heavy lifting.

// throws ConstraintViolationException
$this-&gt;dataValidator-&gt;validate($bag-&gt;all(), $definition);

// gets all constraint violations
$this-&gt;dataValidator-&gt;getViolations($bag-&gt;all(), $definition);</pre>

<p><br />
<strong>#2.2 EXTENDING EXISTING/RECURRING VALIDATION DEFINITIONS</strong><br />
If you need the same validation over and over again, you should consider a ValidationService class which implements the ValidationServiceInterface. This interface provides to methods for creating and updating recurring input data, like addresses.</p>

<p>You may decorate the services but we prefer the way using events. So the calling class should throw an BuildValidationEvent which contains the validation definition and the context. As a developer, you can subscribe to framework.validation.VALIDATION_NAME (e.g. framework.validation.address_create) to extend the existing validation.</p>

<p><strong>#2.3 EXTENDING THE DATA MAPPING TO DAL SYNTAX</strong><br />
After validation, your data needs to be mapped to the syntax of the DAL to do a successful write. After the data has been mapped to the DAL syntax, you should throw a DataMappingEvent so that plugin developers can modify the payload to be written.</p>

<p><strong>Example</strong>:</p>

<pre>
$mappingEvent = new DataMappingEvent(CustomerEvents::MAPPING_CUSTOMER_PROFILE_SAVE, $bag, $mapped, $context-&gt;getContext());

$this-&gt;eventDispatcher-&gt;dispatch($mappingEvent-&gt;getName(), $mappingEvent);

$mapped = $mappingEvent-&gt;getOutput();</pre>

<p><br />
The $mapped variable will then be passed to the DAL repository.</p>
