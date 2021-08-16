        </section>
    </div>
</div>

<div class="footer-main">
    <?php foreach ($languages as $language): ?>
        <a href="<?= $menuHelper->getCurrentUrl([], ['language' => mb_strtolower($language)]); ?>"
           class="language-item <?= ($selectedLanguage === $language) ? 'is--active' : ''; ?>">
            <?= mb_strtoupper($language); ?>
        </a>
    <?php endforeach; ?>
</div>
<script type="text/javascript" src="<?= $baseUrl; ?>../assets/common/javascript/legacy-browser.js?v=<?= $version; ?>"></script>
<script type="text/javascript" src="<?= $baseUrl; ?>../assets/common/javascript/jquery-3.4.1.min.js?v=<?= $version; ?>"></script>
<script type="text/javascript" src="<?= $baseUrl; ?>../assets/install/javascript/jquery.installer.js?v=<?= $version; ?>"></script>
</body>
</html>
