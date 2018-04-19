<h1>jquery.event.swipe</h1>

<h2>Dependencies</h2>
<p>jQuery.event.move: <a href="http://stephband.info/jquery.event.move/">stephband.info/jquery.event.move</a></p>

<p>One of swipeleft, swiperight, swipeup or swipedown is triggered on moveend, when the move has covered at least a threshold proportion of the dimension of the target node.</p>

<p>Swipe events are a thin wrapper around the moveend event, a convenience to reveal when a finger has made a swipe gesture. As such they don't bubble &mdash; they are retriggered on each passing of a moveend event. The underlying move events do bubble and delegate. Use them if you need more flexibility.</p>


<h2>CommonJS</h2>

<p>If you're using Browserify, or any other CommonJS-compatible module system, you can require this script by passing it your jQuery reference. For example,<p>

<pre><code class="js">
require('./path/to/jquery.event.move.js')(jQuery);
</code></pre>

<h2>Demo and docs</h2>
<a href="http://stephband.info/jquery.event.swipe">stephband.info/jquery.event.swipe</a>
