<?php

// core files
require MYBB_ROOT . 'inc/plugins/amnesia/common.php';
require MYBB_ROOT . 'inc/plugins/amnesia/core.php';
require MYBB_ROOT . 'inc/plugins/amnesia/core_consent.php';
require MYBB_ROOT . 'inc/plugins/amnesia/core_erasure.php';
require MYBB_ROOT . 'inc/plugins/amnesia/core_portability.php';
require MYBB_ROOT . 'inc/plugins/amnesia/list_manager.php';

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
        'author'        => 'MyBB Community',
        'authorsite'    => '',
        'version'       => 'dev',
        'codename'      => '',
        'compatibility' => '18*',
    ];
}

function amnesia_install()
{
    global $PL, $db, $cache;

    amnesia_admin_load_pluginlibrary();

    // database
    if (!$db->field_exists('privacy_policy_last_read', 'users')) {
        switch ($db->type) {
            case 'pgsql':
                $db->add_column('users', 'privacy_policy_last_read', "integer NOT NULL DEFAULT 0");
                break;
            case 'sqlite':
                $db->add_column('users', 'privacy_policy_last_read', "integer NOT NULL DEFAULT 0");
                break;
            default:
                $db->add_column('users', 'privacy_policy_last_read', "int(11) NOT NULL DEFAULT 0");
                break;
        }
    }

    if (!$db->field_exists('personal_data_erasure_pending', 'users')) {
        switch ($db->type) {
            case 'pgsql':
                $db->add_column('users', 'personal_data_erasure_pending', "integer NOT NULL DEFAULT 0");
                break;
            case 'sqlite':
                $db->add_column('users', 'personal_data_erasure_pending', "integer NOT NULL DEFAULT 0");
                break;
            default:
                $db->add_column('users', 'personal_data_erasure_pending', "int(1) NOT NULL DEFAULT 0");
                break;
        }
    }

    switch ($db->type) {
        case 'pgsql':
            $db->write_query("
                CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "erasure_requests (
                    id serial,
                    user_id integer NOT NULL,
                    with_content integer NOT NULL,
                    comment text NOT NULL,
                    date integer NOT NULL,
                    scheduled_date integer NOT NULL,
                    action_date integer NOT NULL,
                    verification_code text NOT NULL,
                    verified integer NOT NULL,
                    approved integer NOT NULL,
                    completed integer NOT NULL,
                    PRIMARY KEY (id),
                    UNIQUE(user_id, verification_code)
                )
            ");
            $db->write_query("
                CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "export_requests (
                    id serial,
                    user_id integer NOT NULL,
                    date integer NOT NULL,
                    action_date integer NOT NULL,
                    session_token text NOT NULL,
                    verification_code text NOT NULL,
                    verified integer NOT NULL,
                    active integer NOT NULL,
                    UNIQUE(user_id, verification_code)
                )
            ");
            break;
        case 'sqlite':
            $db->write_query("
                CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "erasure_requests (
                    id integer primary key,
                    user_id integer NOT NULL,
                    with_content integer NOT NULL,
                    comment text NOT NULL,
                    date integer NOT NULL,
                    scheduled_date integer NOT NULL,
                    action_date integer NOT NULL,
                    verification_code text NOT NULL,
                    verified integer NOT NULL,
                    approved integer NOT NULL,
                    completed integer NOT NULL,
                    UNIQUE(user_id, verification_code)
                )
            ");
            $db->write_query("
                CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX . "export_requests (
                    id integer primary key,
                    user_id integer NOT NULL,
                    date integer NOT NULL,
                    action_date integer NOT NULL,
                    session_token text NOT NULL,
                    verification_code text NOT NULL,
                    verified integer NOT NULL,
                    active integer NOT NULL,
                    UNIQUE(user_id, verification_code)
                )
            ");
            break;
        default:
            $query = $db->query("SELECT SUPPORT FROM INFORMATION_SCHEMA.ENGINES WHERE ENGINE = 'InnoDB'");
            $innodbSupport = $db->num_rows($query) && in_array($db->fetch_field($query, 'SUPPORT'), ['DEFAULT', 'YES']);

            $db->write_query("
                CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "erasure_requests` (
                    `id` int(11) NOT NULL auto_increment,
                    `user_id` int(11) NOT NULL,
                    `with_content` int(1) NOT NULL,
                    `comment` text NOT NULL,
                    `date` int(11) NOT NULL,
                    `scheduled_date` int(11) NOT NULL,
                    `action_date` int(11) NOT NULL,
                    `verification_code` varchar(100) NOT NULL,
                    `verified` int(1) NOT NULL,
                    `approved` int(1) NOT NULL,
                    `completed` int(1) NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY (`user_id`, `verification_code`)
                ) " . ($innodbSupport ? "ENGINE=InnoDB" : null) . " " . $db->build_create_table_collation() . "
            ");
            $db->write_query("
                CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "export_requests` (
                    `id` int(11) NOT NULL auto_increment,
                    `user_id` int(11) NOT NULL,
                    `date` int(11) NOT NULL,
                    `action_date` int(11) NOT NULL,
                    `session_token` varchar(100) NOT NULL,
                    `verification_code` varchar(100) NOT NULL,
                    `verified` int(1) NOT NULL,
                    `active` int(1) NOT NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY (`user_id`, `verification_code`)
                ) " . ($innodbSupport ? "ENGINE=InnoDB" : null) . " " . $db->build_create_table_collation() . "
            ");
            break;
    }

    // settings
    $PL->settings(
        'amnesia',
        'Amnesia',
        'Settings for the Amnesia extension (Privacy Policy, Personal Data Export and Erasure).',
        [
            'privacy_policy_require_agreement' => [
                'title'       => 'Privacy Policy: Require Agreement',
                'description' => 'Require users to accept the published privacy policy before using the forums.',
                'optionscode' => 'yesno',
                'value'       => '0',
            ],
            'privacy_policy_document_id' => [
                'title'       => 'Privacy Policy: Help Document ID',
                'description' => 'Set the Help Document ID where the Privacy Policy is located.',
                'optionscode' => 'numeric',
                'value'       => '0',
            ],
            'privacy_policy_date' => [
                'title'       => 'Privacy Policy: Date',
                'description' => 'ISO 8601 formatted date of last privacy policy update. Depending on the setting above, users will need to accept the latest version of the document.',
                'optionscode' => 'text',
                'value'       => date('c'),
            ],
            'personal_data_export' => [
                'title'       => 'Personal Data Export',
                'description' => 'Allow users to export their personal data in the User CP.',
                'optionscode' => 'yesno',
                'value'       => '1',
            ],
            'personal_data_export_include_sensitive' => [
                'title'       => 'Personal Data Export: Include Sensitive Fields',
                'description' => 'Choose whether to provide metadata from sensitive sources like the Administrator and Moderator logs.',
                'optionscode' => 'yesno',
                'value'       => '0',
            ],
            'personal_data_erasure_type' => [
                'title'       => 'Erasure',
                'description' => 'Allow users to anonymize their accounts by removing personal data or completely deleting them, assigning posted content to <i>Guest</i>.',
                'optionscode' => 'select
none=Disabled
anonymization=Anonymization
deletion=Deletion',
                'value'       => 'anonymization',
            ],
            'personal_data_erasure_content' => [
                'title'       => 'Erasure: Content Deletion',
                'description' => 'Allow users to delete posted content along with their accounts (works only with <i>Deletion</i> selected above).',
                'optionscode' => 'yesno',
                'value'       => '0',
            ],
            'personal_data_erasure_waiting_period_days' => [
                'title'       => 'Erasure: Waiting Period (days)',
                'description' => 'Number of days that have to pass after the initial request for data to be erased. Set 0 for no waiting period.',
                'optionscode' => 'numeric',
                'value'       => '7',
            ],
            'personal_data_erasure_approval' => [
                'title'       => 'Erasure: Administrator Approval',
                'description' => 'Choose whether erasure requests should be approved by forum administrators before actual operation.',
                'optionscode' => 'yesno',
                'value'       => '1',
            ],
            'personal_data_erasure_group' => [
                'title'       => 'Erasure: User Group',
                'description' => 'Select which user group accounts with erased data will be added to (if applicable).',
                'optionscode' => 'groupselectsingle',
                'value'       => '',
            ],
            'personal_data_erasure_include_sensitive' => [
                'title'       => 'Erasure: Include Sensitive Fields',
                'description' => 'Choose whether to erase sensitive information like the Administrator and Moderator logs (if applicable).',
                'optionscode' => 'yesno',
                'value'       => '0',
            ],
        ]
    );

    // datacache
    $cache->update('amnesia', [
        'privacy_policy_date' => null,
        'version' => amnesia_info()['version'],
        'unapproved_personal_data_erasure_requests' => 0,
    ]);

    // tasks
    $new_task = [
        'title'       => 'Amnesia (erasure)',
        'description' => 'Performs scheduled operations for the Amnesia extension.',
        'file'        => 'amnesia_erasure',
        'minute'      => '0',
        'hour'        => '0',
        'day'         => '*',
        'month'       => '*',
        'weekday'     => '*',
        'enabled'     => '1',
        'logging'     => '1',
    ];

    require_once MYBB_ROOT . '/inc/functions_task.php';
    $new_task['nextrun'] = fetch_next_run($new_task);
    $db->insert_query('tasks', $new_task);
    $cache->update_tasks();
}

function amnesia_uninstall()
{
    global $PL, $db, $cache;

    amnesia_admin_load_pluginlibrary();

    // database
    if ($db->type == 'sqlite') {
        $db->close_cursors();
    }

    if ($db->field_exists('privacy_policy_last_read', 'users')) {
        $db->drop_column('users', 'privacy_policy_last_read');
    }

    if ($db->field_exists('personal_data_erasure_pending', 'users')) {
        $db->drop_column('users', 'personal_data_erasure_pending');
    }

    if ($db->table_exists('erasure_requests')) {
        $db->drop_table('erasure_requests');
    }

    if ($db->table_exists('export_requests')) {
        $db->drop_table('export_requests');
    }

    // settings
    $PL->settings_delete('amnesia', true);

    // datacache
    $cache->delete('amnesia');

    // tasks
    $db->delete_query('tasks', "file='amnesia_erasure'");
    $cache->update_tasks();
}

function amnesia_is_installed()
{
    global $db;

    // manual check to avoid caching issues
    $query = $db->simple_select('settinggroups', 'gid', "name='amnesia'");

    return (bool)$db->num_rows($query);
}

function amnesia_activate()
{
    global $PL;

    amnesia_admin_load_pluginlibrary();

    // templates
    $templates = [];

    $directory = new DirectoryIterator(MYBB_ROOT . 'inc/plugins/amnesia/templates');

    foreach ($directory as $file) {
        if (!$file->isDot() && !$file->isDir()) {
            $templateName = $file->getPathname();
            $templateName = basename($templateName, '.tpl');
            $templates[$templateName] = file_get_contents($file->getPathname());
        }
    }

    $PL->templates('amnesia', 'Amnesia', $templates);

    \amnesia\replaceInTemplate('header', '{$awaitingusers}', '{$awaitingusers}
{$awaitingPersonalDataErasureRequests}');

    \amnesia\replaceInTemplate('member_register_agreement', '<p><strong>{$lang->agreement_5}</strong></p>', '<p><strong>{$lang->agreement_5}</strong></p>
{$privacyPolicyAgreement}');

    \amnesia\replaceInTemplate('usercp', '{$referral_info}', '{$referral_info}
{$personalDataLinks}');
}

function amnesia_deactivate()
{
    global $PL;

    amnesia_admin_load_pluginlibrary();

    // templates
    $PL->templates_delete('amnesia', true);

    \amnesia\replaceInTemplate('header', '
{$awaitingPersonalDataErasureRequests}', '');

    \amnesia\replaceInTemplate('member_register_agreement', '
{$privacyPolicyAgreement}', '');

    \amnesia\replaceInTemplate('usercp', '
{$personalDataLinks}', '');
}

// helpers
function amnesia_admin_load_pluginlibrary()
{
    global $lang, $PL;

    $lang->load('amnesia');

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
