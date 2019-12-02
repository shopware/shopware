<?php declare(strict_types=1);
echo $renderer->fetch('_header.php', ['tab' => 'done']); ?>

<div class="card__body">
    <div class="alert-hero success">
        <div class="alert-hero-icon">
            <i class="icon-checkmark"></i>
        </div>
        <h3 class="alert-hero-title"><?= $language['done_title']; ?></h3>
        <div class="alert-hero-text"><?= $language['done_info']; ?></div>
    </div>
</div>

<div class="card__footer flex-container">
    <a class="btn btn-primary flex-item flex-right" href="<?= $router->pathFor('finish'); ?>" ><?= $language['finish_update']; ?></a>
</div>

<?php echo $renderer->fetch('_footer.php'); ?>
