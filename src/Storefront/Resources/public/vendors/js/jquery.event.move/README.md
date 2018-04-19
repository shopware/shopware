<h1>jquery.event.move</h1>

<p>Move events provide an easy way to set up press-move-release interactions on mouse and touch devices.</p>


<h2>Demo and docs</h2>

<p><a href="http://stephband.info/jquery.event.move/">stephband.info/jquery.event.move/</a></p>

<h2>Move events</h2>

<dl>
	<dt>movestart</dt>
	<dd>Fired following mousedown or touchstart, when the pointer crosses a threshold distance from the position of the mousedown or touchstart.</dd>
	
	<dt>move</dt>
	<dd>Fired on every animation frame where a mousemove or touchmove has changed the cursor position.</dd>
	
	<dt>moveend</dt>
	<dd>Fired following mouseup or touchend, after the last move event, and in the case of touch events when the finger that started the move has been lifted.</dd>
</dl>

<p>Move event objects are augmented with the properties:</p>

<dl>
  <dt>e.pageX<br/>e.pageY</dt>
  <dd>Current page coordinates of pointer.</dd>
  
  <dt>e.startX<br/>e.startY</dt>
  <dd>Page coordinates the pointer had at movestart.</dd>
  
  <dt>e.deltaX<br/>e.deltaY</dt>
  <dd>Distance the pointer has moved since movestart.</dd>

  <dt>e.velocityX<br/>e.velocityY</dt>
  <dd>Velocity in pixels/ms, averaged over the last few events.</dd>
</dl>

<p>Use them in the same way as you normally bind to events in jQuery:</p>

<pre><code class="js">
jQuery('.mydiv')
.bind('movestart', function(e) {
	// move starts.

})
.bind('move', function(e) {
	// move .mydiv horizontally
	jQuery(this).css({ left: e.startX + e.deltaX });

}).bind('moveend', function() {
	// move is complete!

});
</code></pre>

<p>To see an example of what could be done with it, <a href="http://stephband.info/jquery.event.move/">stephband.info/jquery.event.move/</a></p>

<h2>Tweet me</h2>

<p>If you use move events on something interesting, tweet me <a href="http://twitter.com/stephband">@stephband</a>!</p>