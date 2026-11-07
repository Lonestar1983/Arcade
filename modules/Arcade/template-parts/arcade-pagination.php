<?php
/**
 * Browse pagination. @var array $count @var int $limit @var int $page @var int $cid @var string $q @var string $action
 *
 * Theme override: themes/YOUR_THEME/template-parts/arcade/arcade-pagination.php
 * Values are provided through $args and extracted by the CMS loader.
 *
 * @package Arcade
 */

defined( 'NUKE_EVO' ) || exit;

echo get_pagination(
    array(
        'url'                  => arcade_url(
            array(
                'action' => $action,
                'cid'    => $cid,
                'q'      => $q,
                'sort'   => $sort ?? 'default',
            )
        ),
        'total'                => (int) $count['total'],
        'per_page'             => $limit,
        'current'              => $page,
        'container'            => 'nav',
        'container_class'      => 'pagination-wrap',
        'container_aria_label' => arcade_t( 'games' ),
    )
);
