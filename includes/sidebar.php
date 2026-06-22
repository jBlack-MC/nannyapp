<?php if (!empty($sidebarItems) && is_array($sidebarItems)): ?>
<aside class="app-sidebar" aria-label="Section navigation">
    <nav class="app-sidebar-nav">
        <?php foreach ($sidebarItems as $item): ?>
            <?php
                $href = (string)($item['href'] ?? '#');
                $label = (string)($item['label'] ?? '');
                $active = !empty($item['active']);
            ?>
            <a class="app-sidebar-link <?= $active ? 'active' : '' ?>" href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</aside>
<?php endif; ?>
