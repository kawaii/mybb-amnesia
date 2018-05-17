<?php

// core files
require MYBB_ROOT . 'inc/plugins/amnesia/common.php';
require MYBB_ROOT . 'inc/plugins/amnesia/core.php';
require MYBB_ROOT . 'inc/plugins/amnesia/core_consent.php';
require MYBB_ROOT . 'inc/plugins/amnesia/core_erasure.php';
require MYBB_ROOT . 'inc/plugins/amnesia/core_portability.php';

// hook files
require MYBB_ROOT . 'inc/plugins/amnesia/hooks_frontend.php';
require MYBB_ROOT . 'inc/plugins/amnesia/hooks_acp.php';

// init
define('amnesia\DEVELOPMENT_MODE', 0);

// hooks
\amnesia\addHooksNamespace('amnesia\Hooks');

function amnesia_info()
{
    return [
        'name'          => 'Amnesia',
        'description'   => 'A MyBB Native Extension to Support Information Administration.',
        'website'       => '',
        'author'        => '',
        'authorsite'    => '',
        'version'       => 'dev',
        'codename'      => '',
        'compatibility' => '18*',
    ];
}

function amnesia_install()
{
    global $PL;

    // settings
    $PL->settings(
        'amnesia',
        'Amnesia',
        'Settings for the Amnesia extension.',
        [
            'privacy_policy_document_id' => [
                'title'       => 'Privacy policy: Help Document ID',
                'description' => 'Set the Help Document ID where the Privacy Policy is located.',
                'optionscode' => 'numeric',
                'value'       => '0',
            ],
            'privacy_policy_date' => [
                'title'       => 'Privacy policy: date',
                'description' => 'ISO 8601 formatted date of last privacy policy update. Depending on the setting above, users will need to accept the latest version of the document.',
                'optionscode' => 'text',
                'value'       => date('c'),
            ],
            'privacy_policy_require_agreement' => [
                'title'       => 'Privacy policy: require agreement',
                'description' => 'Require users to accept the published privacy policy before using the forums.',
                'optionscode' => 'yesno',
                'value'       => '0',
            ],
            'personal_data_export' => [
                'title'       => 'Personal data export',
                'description' => 'Allow users to export their personal data in the User CP.',
                'optionscode' => 'yesno',
                'value'       => '1',
            ],
            'personal_data_export_include_sensitive' => [
                'title'       => 'Personal data export: Include sensitive fields',
                'description' => 'Choose whether to provide metadata from sensitive sources like the Administrator and Moderator logs.',
                'optionscode' => 'yesno',
                'value'       => '0',
            ],
            'erasure_type' => [
                'title'       => 'Erasure',
                'description' => 'Allow users to anonymize their accounts by removing personal data or completely removing the them, assigning posted content to <i>Guest</i>.',
                'optionscode' => 'select
none=Disabled
anonymization=Anonymization
deletion=Deletion',
                'value'       => 'anonymization',
            ],
            'erasure_content' => [
                'title'       => 'Erasure: Content deletion',
                'description' => 'Allow users to delete posted content along with their accounts.',
                'optionscode' => 'yesno',
                'value'       => '0',
            ],
            'erasure_waiting_period_days' => [
                'title'       => 'Erasure: waiting period (days)',
                'description' => 'Number of days that have to pass after the initial request for data to be erased. Set 0 for no waiting period.',
                'optionscode' => 'numeric',
                'value'       => '7',
            ],
            'erasure_approval' => [
                'title'       => 'Erasure: administrator approval',
                'description' => 'Choose whether erasure requests should be approved by forum administrators before actual operation.',
                'optionscode' => 'yesno',
                'value'       => '1',
            ],
            'erasure_group' => [
                'title'       => 'Erasure: User group',
                'description' => 'Select which user group accounts with erased data will be added to (if applicable).',
                'optionscode' => 'groupselectsingle',
                'value'       => '',
            ],
            'erasure_include_sensitive' => [
                'title'       => 'Erasure: Include sensitive fields',
                'description' => 'Choose whether to erase sensitive information like the Administrator and Moderator logs (if applicable).',
                'optionscode' => 'yesno',
                'value'       => '0',
            ],
        ]
    );
}

function amnesia_uninstall()
{
    global $PL;

    // settings
    $PL->settings_delete('amnesia', true);
}

function amnesia_is_installed()
{
}

function amnesia_activate()
{
}

function amnesia_deactivate()
{
}

// helpers
function amnesia_admin_load_pluginlibrary()
{
    global $lang, $PL;

    if (!defined('PLUGINLIBRARY')) {
        define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');
    }

    if (!file_exists(PLUGINLIBRARY)) {
        flash_message($lang->amnesia_admin_pluginlibrary_missing, 'error');

        admin_redirect('index.php?module=config-plugins');
    } elseif (!$PL) {
        require_once PLUGINLIBRARY;
    }
}
