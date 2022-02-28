<?php declare(strict_types=1);

namespace Stats;

return [
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'hits' => Api\Adapter\HitAdapter::class,
            'stats' => Api\Adapter\StatAdapter::class,
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            'Stats\Controller\Browse' => Controller\BrowseController::class ,
            'Stats\Controller\Download' => Controller\DownloadController::class,
            'Stats\Controller\Summary' => Controller\SummaryController::class,
        ],
    ],
    // TODO Merge bulk navigation and route with module BulkImport (require a main page?).
    'navigation' => [
        'AdminModule' => [
            'stats' => [
                'label' => 'Stats', // @translate
                'route' => 'admin/stat/default',
                'controller' => 'summary',
                'resource' => 'Stats\Controller\Summary',
                'class' => 'settings o-icon-settings o-icon-chart-line fas fa-chart-line',
                'pages' => [
                    [
                        'label' => 'Summary', // @translate
                        'route' => 'admin/stats/default',
                        'controller' => 'summary',
                        'resource' => 'Stats\Controller\Summary',
                        'pages' => [
                            [
                                'route' => 'admin/stat',
                                'visible' => false,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'stats' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/stat',
                            'defaults' => [
                                '__NAMESPACE__' => 'Stats\Controller',
                                '__ADMIN__' => true,
                                'controller' => 'Summary',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                    ],
                                    'defaults' => [
                                        'controller' => 'Browse',
                                    ],
                                ],
                            ],
                            'd' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:id[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        'id' => '\d+',
                                    ],
                                    'defaults' => [
                                        'controller' => 'Browse',
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'download' => [
                'type' => \Laminas\Router\Http\Segment::class,
                'options' => [
                    'route' => '/download/files/:type/:filename',
                    'constraints' => [
                        'type' => '[^/]+',
                        // Manage module Archive repertory, that can use real names and subdirectories.
                        'filename' => '.+',
                    ],
                    'defaults' => [
                        '__NAMESPACE__' => 'Stats\Controller',
                        'controller' => 'Download',
                        'action' => 'files',
                    ],
                ],
            ],
        ],
    ],
    'translator' => [
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'shortcodes' => [
        'invokables' => [
            'stats_total' => Shortcode\Stats::class,
            'stats_position' => Shortcode\Stats::class,
            'stats_viewed' => Shortcode\Stats::class,
        ],
    ],
    'stats' => [
        'settings' => [
            // Privacy settings.
            'stats_privacy' => 'anonymous',
            'stats_include_bots' => false,
            // Display.
            'stats_default_user_status_admin' => 'hits',
            'stats_default_user_status_public' => 'anonymous',
            'stats_per_page_admin' => 100,
            'stats_per_page_public' => 10,
            // Without roles.
            'stats_public_allow_summary' => false,
            'stats_public_allow_browse_pages' => false,
            'stats_public_allow_browse_resources' => false,
            'stats_public_allow_browse_downloads' => false,
            'stats_public_allow_browse_fields' => false,
            // With roles, in particular if Guest is installed.
            /*
            'stats_roles_summary' => [
                'admin',
            ],
            'stats_roles_browse_pages' => [
                'admin',
            ],
            'stats_roles_browse_resources' => [
                'admin',
            ],
            'stats_roles_browse_downloads' => [
                'admin',
            ],
            'stats_roles_browse_fields' => [
                'admin',
            ],
            'stats_roles_browse_item_sets' => [
                'admin',
            ],
            */
            /*
            'stats_display_by_hooks' => [
                'admin_dashboard',
                'admin_item_show_sidebar',
                'admin_item_set_show_sidebar',
                'admin_media_show_sidebar',
                // Some filters don't exist in Omeka S or are available through BlocksDisposition.
                // 'admin_item_browse_simple_each',
                // 'admin_item_browse_detailed_each',
                // 'public_item_show',
                // 'public_item_browse_each',
                // 'public_item_set_show',
                // 'public_item_set_browse_each',
            ],
            */
        ],
    ],
];
