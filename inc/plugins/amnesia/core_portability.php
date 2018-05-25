<?php

namespace amnesia;

// actions
function createExportRequest(array $user): int
{
    global $db, $plugins;

    \amnesia\invalidateUserExportRequests($user['uid']);

    $sessionToken = \random_str(40);
    $verificationCode = \random_str(40);

    $db->insert_query('export_requests', [
        'user_id' => (int)$user['uid'],
        'date' => \TIME_NOW,
        'action_date' => 0,
        'session_token' => $db->escape_string($sessionToken),
        'verification_code' => $db->escape_string($verificationCode),
        'verified' => 0,
        'active' => 1,
    ]);

    if ($db->type == 'pgsql') {
        $result = $db->fetch_field(
            $db->query('SELECT lastval() AS i'),
            'i'
        );
    } else {
        $result = $db->insert_id();
    }

    \amnesia\sendExportRequestVerificationCode($user, $verificationCode);

    $plugins->run_hooks('amnesia_create_export_request', $result);

    return $result;
}

function getExportRequest(int $id): ?array
{
    global $db;

    $query = $db->simple_select('export_requests', '*', 'id=' . (int)$id);

    if ($db->num_rows($query)) {
        return $db->fetch_array($query);
    } else {
        return null;
    }
}


function getExportRequestByUserIdAndVerificationCode(int $userId, string $verificationCode): ?array
{
    global $db;

    $query = $db->simple_select('export_requests', '*', "
        verification_code='" . $db->escape_string($verificationCode) . "' AND
        user_id=" . (int)$userId . "
    ");

    if ($db->num_rows($query)) {
        return $db->fetch_array($query);
    } else {
        return null;
    }
}

function verifyExportRequest(array $request): bool
{
    global $db, $plugins;

    if ($request) {
        $result = (bool)$db->update_query('export_requests', [
            'verified' => 1,
        ], 'id=' . (int)$request['id']);

        if ($result) {
            $plugins->run_hooks('amnesia_verify_export_request', $request['id']);
        }

        return $result;
    } else {
        return false;
    }
}

function completeExportRequest(array $request): bool
{
    global $db, $plugins;

    if ($request) {
        $result = (bool)$db->update_query('export_requests', [
            'action_date' => \TIME_NOW,
            'active' => 0,
        ], 'id=' . (int)$request['id']);

        if ($result) {
            $plugins->run_hooks('amnesia_complete_export_request', $request['id']);
        }

        return $result;
    } else {
        return false;
    }
}

function invalidateExportRequest(array $request): bool
{
    global $db, $plugins;

    if ($request) {
        $result = (bool)$db->update_query('export_requests', [
            'active' => 0,
        ], 'id=' . (int)$request['id']);

        $plugins->run_hooks('amnesia_invalidate_export_request', $request['id']);

        return $result;
    } else {
        return false;
    }
}

function invalidateUserExportRequests(int $userId): void
{
    global $db, $plugins;

    $db->update_query('export_requests', [
        'active' => 0,
    ], 'user_id=' . (int)$userId);

    $plugins->run_hooks('amnesia_invalidate_user_export_requests', $userId);
}

function setSessionTokenForExportRequest(array $request): bool
{
    global $mybb;

    if ($request) {
        if (
            $mybb->user['uid'] != 0 &&
            (int)$request['user_id'] === (int)$mybb->user['uid'] &&
            $request['action_date'] == 0
        ) {
            \my_setcookie('personal_data_export_session_token', $request['session_token'], 86400, true, 'strict');

            return true;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function dropSessionToken(): bool
{
    \my_setcookie('personal_data_export_session_token', '', 1, true, 'strict');

    return true;
}

// logic
function exportRequestActive(array $request): bool
{
    return (
        $request['active'] == 1 &&
        $request['date'] > (\TIME_NOW - 86400)
    );
}

function currentSessionTokenForExportRequestValid(array $request): bool
{
    global $mybb;

    $result = (
        isset($mybb->cookies['personal_data_export_session_token']) &&
        $request['session_token'] === $mybb->cookies['personal_data_export_session_token']
    );

    return $result;
}

function outputUserPersonalDataXml(int $userId, bool $includeSensitive = false): void
{
    $xml = new \DOMDocument('1.0', 'utf-8');
    $root = $xml->createElement('personalData');
    $xml->appendChild($root);

    $userData = \amnesia\getUserPersonalData($userId, $includeSensitive);

    \amnesia\appendArrayToXmlElement([
        'account' => $userData['account'],
    ], $xml, $root, 'field', true);

    \amnesia\appendArrayToXmlElement([
        'metadata' => $userData['metadata'],
    ], $xml, $root);

    \amnesia\outputXml($xml);
}

function getUserPersonalData(int $userId, bool $includeSensitive = false): array
{
    return [
        'account' => \amnesia\getUserAccountData($userId),
        'metadata' => \amnesia\getUserContentMetadata($userId, $includeSensitive),
    ];
}

function getUserAccountData(int $userId): array
{
    global $mybb, $db;

    $fields = \amnesia\getPersonalAccountDataFields();

    if ($userId != 0) {
        if ($userId == $mybb->user['uid']) {
            $row = array_intersect_key($mybb->user, array_flip($fields));
        } else {
            if (!empty($fields)) {
                $row = $db->fetch_array(
                    $db->simple_select('users', implode(',', $fields), 'uid=' . (int)$userId)
                );
            } else {
                $row = [];
            }
        }

        $row = \amnesia\formatUserAccountDataForOutput($row);

        $customProfileFields = \amnesia\getUserCustomProfileFields($userId);
        $row = array_merge($row, $customProfileFields);

        return $row;
    } else {
        return [];
    }
}

function getUserCustomProfileFields(int $userId): array
{
    global $db, $cache;

    $fields = [];

    $userfields = $db->fetch_array(
        $db->simple_select('userfields', '*', 'ufid=' . (int)$userId)
    );

    $pfcache = $cache->read('profilefields');

    if (!empty($userfields) && is_array($pfcache)) {
        foreach ($pfcache as $customfield) {
            if (\is_member($customfield['viewableby'], $userId)) {
                $userTableFieldName = 'fid' . $customfield['fid'];
                $fields[ $customfield['name'] ] = $userfields[$userTableFieldName];
            }
        }
    }

    return $fields;
}

function getUserContentMetadata(int $userId, bool $includeSensitive = false, bool $uniqueValues = true): array
{
    global $db;

    $data = [];

    $fieldDefinitions = \amnesia\getPersonalDataFieldDefinitions($includeSensitive);

    foreach ($fieldDefinitions as $tableName => $table) {
        if (!empty($table['fields'])) {
            foreach ($table['fields'] as $fieldName => $field) {
                $query = $db->simple_select($tableName, $fieldName, $table['userIdSelector'] . '=' . (int)$userId);

                while ($row = $db->fetch_array($query)) {
                    $value = \amnesia\formatDatabaseValueForOutput($row[$fieldName], $field['type']);

                    $data[ $field['type'] ][] = $value;
                }
            }
        }
    }

    if ($uniqueValues) {
        foreach ($data as &$values) {
            $values = array_unique($values);
        }
    }

    return $data;
}

// internal
function appendArrayToXmlElement(array $data, \DOMDocument $xml, \DOMElement $parent, string $name = null, bool $nameRecursive = false): void
{
    foreach ($data as $key => $value) {
        if (gettype($key) == 'integer') {
            $key = 'value';
        }

        if ($name) {
            $element = $xml->createElement($name);
            $attribute = $xml->createAttribute('name');
            $attribute->value = $key;
            $element->appendChild($attribute);
        } else {
            $element = $xml->createElement($key);
        }

        $parent->appendChild($element);

        if (is_array($value)) {
            appendArrayToXmlElement($value, $xml, $element, $nameRecursive ? $name : null, $nameRecursive);
        } else {
            $element->nodeValue = $value;
        }
    }
}

function sendExportRequestVerificationCode(array $user, string $verificationCode): bool
{
    global $mybb, $lang;

    $lang->load('amnesia');

    $subject = $lang->amnesia_personal_data_export_request_verification_subject;

    $url = $mybb->settings['bburl'] . '/usercp.php?action=personal_data_export&verification_code=' . \htmlspecialchars_uni($verificationCode);

    $message = $lang->sprintf(
        $lang->amnesia_personal_data_export_request_verification_message,
        \htmlspecialchars_uni($user['username']),
        $mybb->settings['bbname'],
        $url
    );

    return \my_mail($user['email'], $subject, $message);
}

function outputXml(\DOMDocument $xml): void
{
    header('Content-Type: application/octet-stream');
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename=personalData.xml');

    $xml->formatOutput = true;

    echo $xml->saveXML();
}
