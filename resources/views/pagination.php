<?php

use Fisharebest\Webtrees\I18N;

$totalPages = ceil($totalArticles / $limit);
$prev = $current - 1;
$next = $current + 1;

echo '<nav aria-label="Page navigation">';
echo '<ul class="pagination justify-content-center">';

if ($current > 1) {
    echo '<li class="page-item"><a class="page-link" href="?page=' . $prev . '&limit=' . $limit . '">' . I18N::translate('previous') . '</a></li>';
}
for ($i = 1; $i <= $totalPages; $i++) {
    echo '<li class="page-item' . ($current == $i ? ' active' : '') . '"><a class="page-link" href="?page=' . $i . '&limit=' . $limit . '">' . $i . '</a></li>';
}
if ($current < $totalPages) {
    echo '<li class="page-item"><a class="page-link" href="?page=' . $next . '&limit=' . $limit . '">' . I18N::translate('next') . '</a></li>';
}

echo '</ul>';
echo '</nav>';
