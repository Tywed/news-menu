<?php

use Fisharebest\Webtrees\I18N;

$current = isset($_GET['page']) ? (int)$_GET['page'] : ($page ?? 1);
$limit = $limit ?? 5;
$totalArticles = $totalArticles ?? 0;

$totalPages = ceil($totalArticles / $limit);
$prev = max(1, $current - 1);
$next = min($totalPages, $current + 1);

if ($totalPages > 1) {
    echo '<nav aria-label="Page navigation">';
    echo '<ul class="pagination justify-content-center">';

    if ($current > 1) {
        echo '<li class="page-item">';
        echo '<a class="page-link" href="' . route('module', [
            'module' => $module_name,
            'action' => isset($category_id) ? 'Category' : 'Page',
            'tree' => $tree->name(),
            'page' => $prev,
            'category_id' => $category_id ?? null,
        ]) . '">' . I18N::translate('previous') . '</a>';
        echo '</li>';
    }

    for ($i = 1; $i <= $totalPages; $i++) {
        if ($i == $current) {
            echo '<li class="page-item active" aria-current="page">';
            echo '<span class="page-link">' . $i . '</span>';
            echo '</li>';
        } else {
            echo '<li class="page-item">';
            echo '<a class="page-link" href="' . route('module', [
                'module' => $module_name,
                'action' => isset($category_id) ? 'Category' : 'Page',
                'tree' => $tree->name(),
                'page' => $i,
                'category_id' => $category_id ?? null,
            ]) . '">' . $i . '</a>';
            echo '</li>';
        }
    }

    if ($current < $totalPages) {
        echo '<li class="page-item">';
        echo '<a class="page-link" href="' . route('module', [
            'module' => $module_name,
            'action' => isset($category_id) ? 'Category' : 'Page',
            'tree' => $tree->name(),
            'page' => $next,
            'category_id' => $category_id ?? null,
        ]) . '">' . I18N::translate('next') . '</a>';
        echo '</li>';
    }

    echo '</ul>';
    echo '</nav>';
}
