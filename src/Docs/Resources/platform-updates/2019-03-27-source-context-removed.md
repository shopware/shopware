[titleEn]: <>(BC: SourceContext removed)
[__RAW__]: <>(__RAW__)

<p>We&#39;ve removed the <strong>SourceContext</strong> as it was global mutable State.</p>

<p>Now the <strong>Context</strong> has a <strong>Source</strong>, that is either a <strong>SystemSource</strong>, <strong>AdminApiSource</strong> or <strong>SalesChannelSource</strong>.</p>

<p>If you want to get the <strong>SalesChannel</strong> or user from the <strong>Context</strong> you have to explicitly check the <strong>Source</strong> as these things aren&#39;t always set.</p>

<p>Don&#39;t use the shortcut function to get the <strong>SalesChannelId</strong> od <strong>userId</strong> directly on the Context-Object, as these will be removed soon.</p>
