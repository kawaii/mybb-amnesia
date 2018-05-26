<?php

namespace amnesia;

function getPersonalAccountDataFields(): array
{
    global $plugins;

    $fields = [
        'username',
        'email',
        'avatar',
        'usertitle',
        'website',
        'icq',
        'yahoo',
        'skype',
        'google',
        'birthday',
        'signature',
        'timezone',
        'dst',
        'dstcorrection',
        'awayreason',
        'notepad',
        'regip',
        'lastip',
        'language',
        'coppauser',
        'usernotes',
    ];

    $fields = $plugins->run_hooks('amnesia_personal_account_data_fields', $fields);

    return $fields;
}

function getPersonalDataFieldDefinitions(bool $includeSensitive = false): array
{
    global $plugins;

    $fieldDefinitions = [
        'pollvotes' => [
            'fields' => [
                'ipaddress' => [
                    'type' => 'ip',
                    'anonymizedValue' => '',
                ],
            ],
            'userIdSelector' => 'uid',
        ],
        'posts' => [
            'fields' => [
                'ipaddress' => [
                    'type' => 'ip',
                    'anonymizedValue' => '',
                ],
            ],
            'userIdSelector' => 'uid',
        ],
        'privatemessages' => [
            'fields' => [
                'ipaddress' => [
                    'type' => 'ip',
                    'anonymizedValue' => '',
                ],
            ],
            'userIdSelector' => 'uid',
        ],
        'searchlog' => [
            'fields' => [
                'ipaddress' => [
                    'type' => 'ip',
                    'anonymizedValue' => '',
                ],
            ],
            'userIdSelector' => 'uid',
        ],
        'sessions' => [
            'fields' => [
                'ip' => [
                    'type' => 'ip',
                    'anonymizedValue' => '',
                ],
                'useragent' => [
                    'type' => 'useragent',
                    'anonymizedValue' => '',
                ],
            ],
            'userIdSelector' => 'uid',
        ],
        'threadratings' => [
            'fields' => [
                'ipaddress' => [
                    'type' => 'ip',
                    'anonymizedValue' => '',
                ],
            ],
            'userIdSelector' => 'uid',
        ],
    ];

    $sensitiveFieldDefinitions = [
        'adminlog' => [
            'fields' => [
                'ipaddress' => [
                    'type' => 'ip',
                    'anonymizedValue' => '',
                ],
            ],
            'userIdSelector' => 'uid',
        ],
        'adminsessions' => [
            'fields' => [
                'ip' => [
                    'type' => 'ip',
                    'anonymizedValue' => '',
                ],
                'useragent' => [
                    'type' => 'useragent',
                    'anonymizedValue' => '',
                ],
            ],
            'userIdSelector' => 'uid',
        ],
        'moderatorlog' => [
            'fields' => [
                'ipaddress' => [
                    'type' => 'ip',
                    'anonymizedValue' => '',
                ],
            ],
            'userIdSelector' => 'uid',
        ],
    ];

    $fieldDefinitions = $plugins->run_hooks('amnesia_personal_data_field_definitions', $fieldDefinitions);
    $sensitiveFieldDefinitions = $plugins->run_hooks('amnesia_personal_data_sensitive_field_definitions', $sensitiveFieldDefinitions);

    if ($includeSensitive) {
        $fieldDefinitions = array_merge($fieldDefinitions, $sensitiveFieldDefinitions);
    }

    return $fieldDefinitions;
}

function formatUserAccountDataForOutput(array $user): array
{
    global $db, $plugins;

    if (isset($user['regip'])) {
        $user['regip'] = \my_inet_ntop($db->unescape_binary($user['regip']));
    }

    if (isset($user['lastip'])) {
        $user['lastip'] = \my_inet_ntop($db->unescape_binary($user['lastip']));
    }

    $plugins->run_hooks('amnesia_format_user_account_data_for_output', $user);

    return $user;
}

function formatDatabaseValueForOutput(string $value, string $type): string
{
    global $db, $plugins;

    if ($type == 'ip') {
        $value = \my_inet_ntop($db->unescape_binary($value));
    }

    $arguments = [
        'value' => $value,
        'type' => $type,
    ];

    $plugins->run_hooks('amnesia_format_database_value_for_output', $arguments);

    $value = $arguments['value'];

    return $value;
}

function pageAlwaysAccessible(string $filename, string $action): bool
{
    $actions = [
        'contact.php' => true,
        'member.php' => [
            'logout',
        ],
        'misc.php' => [
            'helpresults',
            'do_helpsearch',
            'help',
            'clearcookies',
        ],
        'usercp.php' => [
            'password',
            'do_password',
            'email',
            'do_email',
            'personal_data_erasure',
            'personal_data_export',
        ],
    ];

    return (
        in_array($filename, array_keys($actions)) &&
        (
            $actions[$filename] === true ||
            in_array($action, $actions[$filename])
        )
    );
}
