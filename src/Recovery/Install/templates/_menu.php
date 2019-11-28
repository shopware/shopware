<!-- Navigation list -->
<nav class="navigation--main">
    <ul class="navigation--list">
        <?php foreach ($entries as $entry): ?>
            <li class="navigation--entry <?= ($entry['active']) ? 'is--active' : ''; ?> <?= ($entry['complete']) ? 'is--complete' : ''; ?>">
                <span class="navigation--link"><?= $entry['label']; ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</nav>
