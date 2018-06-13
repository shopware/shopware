{if $exception}
ERROR:
{$exception->getMessage()} in {$error_file} on line {$exception->getLine()}

TRACE:
{$error_trace|escape}
{else}
    {se name="InformText"}Wir wurden bereits über das Problem informiert und arbeiten an einer Lösung, bitte versuchen Sie es in Kürze erneut.{/se}
{/if}