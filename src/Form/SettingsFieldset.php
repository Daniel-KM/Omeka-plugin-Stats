<?php declare(strict_types=1);

namespace Stats\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Stats'; // @translate

    public function init(): void
    {
        $this
            ->add([
                'name' => 'stats_privacy',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Level of privacy for new hits', // @translate
                    'value_options' => [
                        'anonymous' => 'Anonymous', // @translate
                        'hashed' => 'Hashed IP', // @translate
                        'partial_1' => 'Partial IP (first hex)', // @translate
                        'partial_2' => 'Partial IP (first 2 hexs)', // @translate
                        'partial_3' => 'Partial IP (first 3 hexs)', // @translate
                        'clear' => 'Clear IP', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'stats_privacy',
                    'value' => 'anonymous',
                ],
            ])
            ->add([
                'name' => 'stats_include_bots',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Include crawlers/bots', // @translate
                    'info' => 'By checking this box, all hits which user agent contains the term "bot", "crawler", "spider", etc. will be included.', // @translate
                ],
                'attributes' => [
                    'id' => 'stats_include_bots',
                ],
            ])

            ->add([
                'name' => 'stats_default_user_status_admin',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'User status for admin pages', // @translate
                    'value_options' => [
                        'hits' => 'Total hits', // @translate
                        'anonymous' => 'Anonymous', // @translate
                        'identified' => 'Identified users', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'stats_default_user_status_admin',
                ],
            ])
            ->add([
                'name' => 'stats_default_user_status_public',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'User status for public pages', // @translate
                    'value_options' => [
                        'hits' => 'Total hits', // @translate
                        'anonymous' => 'Anonymous', // @translate
                        'identified' => 'Identified users', // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'stats_default_user_status_public',
                ],
            ])
            ->add([
                'name' => 'stats_per_page_admin',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Results per page (admin)', // @translate
                ],
                'attributes' => [
                    'id' => 'stats_per_page_admin',
                    'min' => 0,
                ],
            ])
            ->add([
                'name' => 'stats_per_page_public',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Results per page (public)', // @translate
                ],
                'attributes' => [
                    'id' => 'stats_per_page_public',
                    'min' => 0,
                ],
            ])

            ->add([
                'name' => 'stats_public_allow_summary',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Allow public to access stats summary', // @translate
                ],
                'attributes' => [
                    'id' => 'stats_public_allow_summary',
                ],
            ])
            ->add([
                'name' => 'stats_public_allow_browse_pages',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Allow public to access stats of pages', // @translate
                ],
                'attributes' => [
                    'id' => 'stats_public_allow_browse_pages',
                ],
            ])
            ->add([
                'name' => 'stats_public_allow_browse_resources',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Allow public to access stats of resources', // @translate
                ],
                'attributes' => [
                    'id' => 'stats_public_allow_browse_resources',
                ],
            ])
            ->add([
                'name' => 'stats_public_allow_browse_downloads',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Allow public to access stats of downloads', // @translate
                ],
                'attributes' => [
                    'id' => 'stats_public_allow_browse_downloads',
                ],
            ])
            ->add([
                'name' => 'stats_public_allow_browse_fields',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Allow public to access stats of fields', // @translate
                ],
                'attributes' => [
                    'id' => 'stats_public_allow_browse_fields',
                ],
            ])
        ;
    }
}
