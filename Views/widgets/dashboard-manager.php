<?php
/**
 * Widget « Notes d'équipe » du tableau de bord gérant (NotebookBundle::dashboardWidgets(), contrat 1.4).
 *
 * @var list<array<string, mixed>> $notebook_entries Voir TeamNotesWidget::entries()
 */
?>
<?php if ($notebookCss = bundle_asset('notebook', 'css/notebook.css')): ?>
<link rel="stylesheet" href="<?= $notebookCss ?>">
<?php endif; ?>
<!-- Carnet de notes d'équipe -->
<div class="card card--mt">
    <div class="card-header">
        <span><?= __('widget_team_notes') ?></span>
        <a href="<?= route_url('notebook.index') ?>" class="card-header-link"><?= __('view_all') ?></a>
    </div>
    <?php if (empty($notebook_entries)): ?>
        <div class="empty-state"><?= __('no_notebook_entries') ?></div>
    <?php else: ?>
        <ul class="notebook-widget-list">
            <?php foreach ($notebook_entries as $entry): ?>
                <li class="notebook-widget-item<?= !empty($entry['pinned']) ? ' notebook-widget-item--pinned' : '' ?>">
                    <?php if (!empty($entry['pinned'])): ?>
                        <span class="notebook-widget-item__pin" title="<?= htmlspecialchars(__('notebook_pinned')) ?>">📌</span>
                    <?php endif; ?>
                    <p class="notebook-widget-item__content"><?= nl2br(htmlspecialchars($entry['content'] ?? '')) ?></p>
                    <div class="notebook-widget-item__meta">
                        <span><?= htmlspecialchars($entry['author_name'] ?? __('notebook_anonymous_author')) ?></span>
                        <span>·</span>
                        <span><?= htmlspecialchars($entry['store_name'] ?? __('notebook_org_wide')) ?></span>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
