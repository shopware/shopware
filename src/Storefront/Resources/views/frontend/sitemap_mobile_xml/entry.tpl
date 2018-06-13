    <url>
        <loc>{url params = $urlParams}</loc>
        <mobile:mobile/>
        {if $lastmod}
            <lastmod>{date_format($lastmod, 'Y-m-d')}</lastmod>
        {/if}
        <changefreq>weekly</changefreq>
        <priority>0.5</priority>
    </url>
