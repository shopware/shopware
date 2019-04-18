[titleEn]: <>(New code style fixer rules)
[__RAW__]: <>(__RAW__)

<p>We added the following new rules to our coding style rule set</p>

<ul>
	<li>NoUselessCommentFixer</li>
	<li>PhpdocNoSuperfluousParamFixer</li>
	<li>NoImportFromGlobalNamespaceFixer</li>
	<li>OperatorLinebreakFixer</li>
	<li>PhpdocNoIncorrectVarAnnotationFixer</li>
	<li>NoUnneededConcatenationFixer</li>
	<li>NullableParamStyleFixer</li>
</ul>

<p>Have a look here <a href="https://github.com/kubawerlos/php-cs-fixer-custom-fixers#fixers">https://github.com/kubawerlos/php-cs-fixer-custom-fixers#fixers </a>what they mean and what they do.</p>

<p>Additionally the option &quot;allow-risky&quot; is now part of the php_cs.dist config. So it is not necessary anymore to call the cs-fixer with the &quot;&ndash;allow-risky&quot; parameter</p>
