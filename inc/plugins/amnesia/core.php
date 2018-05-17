<?php

namespace amnesia;

function getPersonalAccountDataFields(): array
{
    global $plugins;

    $fields = [
        'uid',
        'username',
        'email',
        'postnum',
        'threadnum',
        'avatar',
        'avatardimensions',
        'avatartype',
        'usertitle',
        'regdate',
        'lastactive',
        'lastvisit',
        'lastpost',
        'website',
        'icq',
        'yahoo',
        'skype',
        'google',
        'birthday',
        'birthdayprivacy',
        'signature',
        'hideemail',
        'invisible',
        'timezone',
        'dst',
        'dstcorrection',
        'away',
        'awaydate',
        'returndate',
        'awayreason',
        'notepad',
        'referrer',
        'referrals',
        'reputation',
        'regip',
        'lastip',
        'language',
        'timeonline',
        'totalpms',
        'unreadpms',
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
                'ipadress' => [
                    'type' => 'ip',
                    'anonymizedValue' => '',
                ],
            ],
            'userIdSelector' => 'uid',
        ],
        'threads' => [
            'fields' => [
                'ipadress' => [
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
    $sensitiveFieldDefinitions = $plugins->run_hooks('amnesia_personal_data_sensitive_field_definitions', $fieldDefinitions);

    if ($includeSensitive) {
        $fieldDefinitions = array_merge($fieldDefinitions, $sensitiveFieldDefinitions);
    }

    return $fieldDefinitions;
}

function getRowUpdatesForFields(array $fields): array
{
    $rowUpdates = [];

    foreach ($fields as $fieldName => $field) {
        $rowUpdates[$fieldName] = $field['anonymizedValue'];
    }

    return $rowUpdates;
}
