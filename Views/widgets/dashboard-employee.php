<?php
/**
 * Widget « Notes d'équipe » du tableau de bord employé (NotebookBundle::dashboardWidgets(), contrat 1.4).
 *
 * @var list<array<string, mixed>> $notebook_entries Voir TeamNotesWidget::entries()
 */

use kintai\Contract\View\Components\Button;
use kintai\Contract\View\Components\Card;
?>
<?php if ($notebookCss = bundle_asset('notebook', 'css/notebook.css')): ?>
<link rel="stylesheet" href="<?= $notebookCss ?>">
<?php endif; ?>
<?php
ob_start();
if (empty($notebook_entries)):
    echo '<div class="empty-state">' . __('no_notebook_entries') . '</div>';
else:
    foreach ($notebook_entries as $entry):
        $pinned = !empty($entry['pinned']);
        ?>
        <div class="notebook-widget-item<?= $pinned ? ' notebook-widget-item--pinned' : '' ?>">
            <?php if ($pinned): ?>
                <span class="notebook-widget-item__pin" title="<?= htmlspecialchars(__('notebook_pinned')) ?>">📌</span>
            <?php endif; ?>
            <p class="notebook-widget-item__content"><?= nl2br(htmlspecialchars($entry['content'] ?? '')) ?></p>
            <div class="notebook-widget-item__meta">
                <span><?= htmlspecialchars($entry['author_name'] ?? __('notebook_anonymous_author')) ?></span>
                <span>·</span>
                <span><?= htmlspecialchars($entry['store_name'] ?? __('notebook_org_wide')) ?></span>
            </div>
        </div>
        <?php
    endforeach;
endif;
echo Card::make()
    ->header('<span>' . __('my_team_notes') . '</span> ' . Button::make(__('view_all'))->ghost()->sm()->link(route_url('notebook.index'))->render())
    ->body(ob_get_clean())
    ->render();
