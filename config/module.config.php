<?php declare(strict_types=1);

namespace Statistics;

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
    'view_helpers' => [
        'factories' => [
            'statistic' => Service\ViewHelper\StatisticFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
    ],
    'controllers' => [
        'invokables' => [
            'Statistics\Controller\Browse' => Controller\BrowseController::class ,
            'Statistics\Controller\Summary' => Controller\SummaryController::class,
        ],
        'factories' => [
            'Statistics\Controller\Download' => Service\Controller\DownloadControllerFactory::class,
        ],
    ],
    // TODO Merge bulk navigation and route with module BulkImport (require a main page?).
    'navigation' => [
        'AdminModule' => [
            'statistics' => [
                'label' => 'Statistics', // @translate
                'route' => 'admin/statistics',
                'controller' => 'summary',
                'action' => 'index',
                'resource' => 'Statistics\Controller\Summary',
                'class' => 'o-icon- fa-chart-line',
                'pages' => [
                    [
                        'label' => 'Summary', // @translate
                        'route' => 'admin/statistics/default',
                        'controller' => 'summary',
                        'action' => 'index',
                        'resource' => 'Statistics\Controller\Summary',
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
                    'statistics' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/statistics',
                            'defaults' => [
                                '__NAMESPACE__' => 'Statistics\Controller',
                                '__ADMIN__' => true,
                                'controller' => 'Summary',
                                'action' => 'index',
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
                        '__NAMESPACE__' => 'Statistics\Controller',
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
            'stats' => Shortcode\Stats::class,
            'stats_total' => Shortcode\Stats::class,
            'stats_position' => Shortcode\Stats::class,
            'stats_vieweds' => Shortcode\Stats::class,
        ],
    ],
    'statistics' => [
        'settings' => [
            // Privacy settings.
            'statistics_privacy' => 'anonymous',
            'statistics_include_bots' => false,
            // Display.
            'statistics_default_user_status_admin' => 'hits',
            'statistics_default_user_status_public' => 'anonymous',
            'statistics_per_page_admin' => 100,
            'statistics_per_page_public' => 10,
            // Without roles.
            'statistics_public_allow_summary' => false,
            'statistics_public_allow_browse_pages' => false,
            'statistics_public_allow_browse_resources' => false,
            'statistics_public_allow_browse_downloads' => false,
            'statistics_public_allow_browse_fields' => false,
            // With roles, in particular if Guest is installed.
            /*
            'statistics_roles_summary' => [
                'admin',
            ],
            'statistics_roles_browse_pages' => [
                'admin',
            ],
            'statistics_roles_browse_resources' => [
                'admin',
            ],
            'statistics_roles_browse_downloads' => [
                'admin',
            ],
            'statistics_roles_browse_fields' => [
                'admin',
            ],
            'statistics_roles_browse_item_sets' => [
                'admin',
            ],
            */
            /*
            'statistics_display_by_hooks' => [
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
