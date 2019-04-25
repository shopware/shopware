[titleEn]: <>(Updates for upload handling)
[__RAW__]: <>(__RAW__)

<p>In order to react to upload events and errors globally, we made some changes how uploads are stored and run.</p>

<p>Events are now fired directly by the upload store and the &lt;sw-media-upload&gt; component now only handles file and url objects to create upload data. So it is not possible anymore to subscribe to the sw-media-upload-new-uploads-added, sw-media-upload-media-upload-success and sw-media-upload-media-upload-failure events from it.</p>

<p><strong>Handling upload events with vue.js</strong><br />
We added an additional component &lt;sw-upload-store-listener&gt; in order to take over all the listener registration an most of the event handling. The upload handler has only two properties.</p>

<p>uploadTag: String - the upload tag you want to listen to<br />
autoupload: Boolean indicating that the upload added events should be skiped and youre only interested in when the upload was successfull or errored<br />
The component emits vue.js events back to your wrapping component</p>

<p><strong>sw-media-upload-added:</strong> Object { UploadTask[]: data } - this will be skipped if you set autoupload to true<br />
<strong>sw-media-upload-finished:</strong> Object { string: targetId }<br />
<strong>sw-media-upload-failed:</strong> UploadTask<br />
In most cases you will set autoupload to true but it isn&#39;t the default. Sometimes you want to do additional work, before the the real upload process starts (e.g. creating associations). To do so listen to the sw-media-upload-added event use the data array to get the media ids of the entities that are just created for the upload.</p>

<p><strong>Common Example</strong><br />
The following code snippet is simplified from the sw-media-index component</p>

<pre>
// template
&lt;sw-media-upload
&nbsp; &nbsp; variant=&quot;compact&quot;
&nbsp; &nbsp; :targetFolderId=&quot;routeFolderId&quot;
&nbsp; &nbsp; :uploadTag=&quot;uploadTag&quot;&gt;
&lt;/sw-media-upload&gt;
&lt;sw-upload-store-listener
&nbsp; &nbsp; :uploadTag=&quot;uploadTag&quot;
&nbsp; &nbsp; @sw-media-upload-added=&quot;onUploadsAdded&quot;
&nbsp; &nbsp; @sw-media-upload-finished=&quot;onUploadFinished&quot;
&nbsp; &nbsp; @sw-media-upload-failed=&quot;onUploadFailed&quot;&gt;
&lt;/sw-upload-store-listener&gt;

// .js
onUploadsAdded({ data }) {
&nbsp; &nbsp; data.forEach((upload) =&gt; {
&nbsp;&nbsp; &nbsp; &nbsp; &nbsp;// do stuff with each upload that was added
&nbsp; &nbsp; });

&nbsp; &nbsp; // run the actual upload process
&nbsp; &nbsp; this.uploadStore.runUploads(this.uploadTag);
},

onUploadFinished({ targetId }) {
&nbsp; &nbsp; // refresh media entity
&nbsp; &nbsp; this.mediaItemStore.getByIdAsync(targetId).then((updatedItem) =&gt; {
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;// do something with the refreshed entitity;
&nbsp; &nbsp; });
}

// if your are only interested in the target entity&#39;s id
// you can use destructuring
onUploadFailed({ targetId }) {
&nbsp; &nbsp; // tidy up
&nbsp; &nbsp; this.mediaItemStore.getByIdAsync(targetId).then((updatedMedia) =&gt; {
&nbsp;&nbsp; &nbsp; &nbsp; &nbsp;if (!updatedMedia.hasFile) {
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp; &nbsp; &nbsp;updatedMedia.delete(true);
&nbsp;&nbsp; &nbsp; &nbsp; &nbsp;}
&nbsp; &nbsp; });
}</pre>

<p><strong>Subscribe to the store manually</strong><br />
You can also subscribe to the store directly but it is not the preferred way. You can add and remove your listener with the following methods:</p>

<p>addListener(string: uploadTag, function: callback)<br />
removeListener(string: uploadTag, funtion: callback)<br />
addDefaultListener(function: callback)<br />
removeDefaultListenre(function: callback)<br />
The store will pass you a single object back to your callback when an upload event occurs:</p>

<pre>
uploadStore = State.getStore(&#39;upload&#39;);
uploadStore.addListener(&#39;my-upload-tag&#39;, myListener);

function myListener({action, uploadTag, payload }) {...}</pre>

<p>The action and payload is similar to the vue.js event name and $event data described above.</p>
