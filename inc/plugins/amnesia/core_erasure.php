<?php

namespace amnesia;

// actions
function createErasureRequest(array $user, bool $withContent = false, string $comment = ''): bool
{
    global $db, $plugins;

    $verificationCode = \random_str(40);

    $db->replace_query('erasure_requests', [
        'user_id' => (int)$user['uid'],
        'with_content' => (int)$withContent,
        'comment' => $db->escape_string($comment),
        'date' => \TIME_NOW,
        'scheduled_date' => 0,
        'action_date' => 0,
        'verification_code' => $db->escape_string($verificationCode),
        'verified' => 0,
        'approved' => 0,
        'completed' => 0,
    ]);

    if ($db->type == 'pgsql') {
        $result = $db->fetch_field(
            $db->query('SELECT lastval() AS i'),
            'i'
        );
    } else {
        $result = $db->insert_id();
    }

    \amnesia\sendErasureRequestVerificationCode($user, $verificationCode);

    $plugins->run_hooks('amnesia_create_erasure_request', $result);

    return $result;
}

function getErasureRequest(int $id): ?array
{
    global $db;

    $query = $db->simple_select('erasure_requests', '*', 'id=' . (int)$id);

    if ($db->num_rows($query)) {
        return $db->fetch_array($query);
    } else {
        return null;
    }
}

function getErasureRequestByUserIdAndVerificationCode(int $userId, string $verificationCode): ?array
{
    global $db;

    $query = $db->simple_select('erasure_requests', '*', "
        verification_code='" . $db->escape_string($verificationCode) . "' AND
        user_id=" . (int)$userId . "
    ");

    if ($db->num_rows($query)) {
        return $db->fetch_array($query);
    } else {
        return null;
    }
}

function getPendingUserErasureRequest(int $userId): ?array
{
    global $db;

    $query = $db->simple_select('erasure_requests', '*', "
        verified = 1 AND
        scheduled_date != 0 AND
        completed != 1 AND
        user_id=" . (int)$userId . "
    ");

    if ($db->num_rows($query)) {
        return $db->fetch_array($query);
    } else {
        return null;
    }
}

function getUnapprovedPendingErasureRequestsCount(bool $cached = true): int
{
    global $db;

    if (\amnesia\getSettingValue('personal_data_erasure_approval')) {
        if ($cached) {
            return (int)\amnesia\getCacheValue('unapproved_personal_data_erasure_requests');
        } else {
            return (int)$db->fetch_field(
                $db->simple_select('erasure_requests', 'COUNT(id) AS n', '
                completed = 0 AND
                verified = 1 AND
                approved = 0
            '),
                'n'
            );
        }
    } else {
        return 0;
    }
}

function recountCachedUnapprovedPendingErasureRequests(): void
{
    \amnesia\updateCache([
        'unapproved_personal_data_erasure_requests' => \amnesia\getUnapprovedPendingErasureRequestsCount(false),
    ]);
}

function verifyErasureRequest(array $request): bool
{
    global $db, $plugins;

    if ($request) {
        $result = (bool)$db->update_query('erasure_requests', [
            'verified' => 1,
        ], 'id=' . (int)$request['id']);

        if ($result) {
            $plugins->run_hooks('amnesia_verify_erasure_request', $request['id']);
        }

        return $result;
    } else {
        return false;
    }
}

function approveErasureRequest(array $request): bool
{
    global $db, $plugins;

    if ($request) {
        $result = (bool)$db->update_query('erasure_requests', [
            'approved' => 1,
        ], 'id=' . (int)$request['id']);

        if ($result) {
            \amnesia\recountCachedUnapprovedPendingErasureRequests();

            $plugins->run_hooks('amnesia_approve_erasure_request', $request['id']);
        }

        return (bool)$result;
    } else {
        return false;
    }
}

function scheduleErasureRequest(array $request): bool
{
    global $db, $plugins;

    if ($request) {
        $waitingPeriod = (int)\amnesia\getSettingValue('personal_data_erasure_waiting_period_days') * 86400;

        $result = (bool)$db->update_query('erasure_requests', [
            'scheduled_date' => \TIME_NOW + $waitingPeriod,
        ], 'id=' . (int)$request['id']);

        $result &= (bool)$db->update_query('users', [
            'personal_data_erasure_pending' => 1,
        ], 'uid=' . (int)$request['user_id']);

        \amnesia\recountCachedUnapprovedPendingErasureRequests();

        if ($result) {
            $plugins->run_hooks('amnesia_schedule_erasure_request', $request['id']);
        }

        return $result;
    } else {
        return false;
    }
}

function completeErasureRequest(array $request): bool
{
    global $db, $plugins;

    if ($request) {
        if (
            $request['verified'] == 1 &&
            (
                $request['approved'] == 1 ||
                !\amnesia\getSettingValue('personal_data_erasure_approval')
            ) &&
            $request['scheduled_date'] <= \TIME_NOW &&
            !\is_super_admin($request['uid'])
        ) {
            switch (\amnesia\getSettingValue('personal_data_erasure_type')) {
                case 'anonymization':
                    $result = \amnesia\anonymizeUser($request['user_id']);
                    break;
                case 'deletion':
                    $result = \amnesia\deleteUserAndContentMetadata($request['user_id'], $request['with_content']);
                    break;
                default:
                    $result = false;
                    break;
            }

            if ($result) {
                $result &= (bool)$db->update_query('erasure_requests', [
                    'completed' => 1,
                    'action_date' => \TIME_NOW,
                ], 'id=' . (int)$request['id']);

                $result &= (bool)$db->update_query('users', [
                    'personal_data_erasure_pending' => 0,
                ], 'uid=' . (int)$request['user_id']);

                \amnesia\recountCachedUnapprovedPendingErasureRequests();

                $plugins->run_hooks('amnesia_complete_erasure_request', $request['id']);
            }

            return $result;
        } else {
            return false;
        }
    } else {
        return false;
    }
}

function completePendingErasureRequests(): bool
{
    global $db;

    $result = true;

    if (\amnesia\getSettingValue('personal_data_erasure_approval')) {
        $approved = 'AND approved = 1';
    } else {
        $approved = '';
    }

    $query = $db->simple_select('erasure_requests', '*', '
        completed = 0
        AND verified = 1
        ' . $approved . '
        AND scheduled_date <= ' . TIME_NOW . '
    ');

    while ($request = $db->fetch_array($query)) {
        $result &= \amnesia\completeErasureRequest($request);
    }

    return $result;
}

function cancelErasureRequest(array $request): bool
{
    global $db, $plugins;

    if ($request) {
        $result = $db->delete_query('erasure_requests', 'id=' . (int)$request['id']);

        $result &= (bool)$db->update_query('users', [
            'personal_data_erasure_pending' => 0,
        ], 'uid=' . (int)$request['user_id']);

        \amnesia\recountCachedUnapprovedPendingErasureRequests();

        $plugins->run_hooks('amnesia_cancel_erasure_request', $request['id']);

        return (bool)$result;
    } else {
        return false;
    }
}

// logic
function personalDataErasurePendingForCurrentUser(): bool
{
    global $mybb;

    return $mybb->user['uid'] != 0 && $mybb->user['personal_data_erasure_pending'] == 1;
}

function erasureRequestVerifiable(array $request): bool
{
    return (
        $request['completed'] != 1 &&
        $request['date'] > (\TIME_NOW - 86400)
    );
}

function anonymizeUser(int $userId): bool
{
    global $plugins;

    $plugins->run_hooks('amnesia_anonymize_user_start', $userId);

    $result = \amnesia\anonymizeUserProfile($userId);
    $result &= \amnesia\disableUserAccount($userId);
    $result &= \amnesia\deleteUserContentMetadata($userId);

    $plugins->run_hooks('amnesia_anonymize_user_end', $userId);

    return $result;
}

function deleteUserAndContentMetadata(int $userId, bool $withContent = false): bool
{
    $result = \amnesia\deleteUserContentMetadata($userId);
    $result &= \amnesia\deleteUser($userId, $withContent);

    return $result;
}

function disableUserAccount(int $userId): bool
{
    global $db;

    return (bool)$db->update_query('users', [
        'password' => '',
        'loginkey' => '',
    ], 'uid=' . (int)$userId);
}

function anonymizeUserProfile(int $userId): bool
{
    $fields = \amnesia\getPersonalAccountDataFields();
    $userUpdates = array_fill_keys($fields, '');

    require_once MYBB_ROOT . 'inc/datahandlers/user.php';

    $userhandler = new \UserDataHandler('update');

    $userUpdates['uid'] = $userId;

    $usernameCandidate = \amnesia\getAnonymizedUsername($userId);

    if (!is_null($usernameCandidate)) {
        $userUpdates['username'] = $usernameCandidate;

        $userhandler->set_data($userUpdates);
        $userhandler->set_validated(true);

        $result = $userhandler->update_user();

        $userhandler->clear_profile($userId, (int)\amnesia\getSettingValue('personal_data_erasure_group'));

        return $result;
    } else {
        return false;
    }
}

function deleteUser(int $userId, bool $withContent = false): bool
{
    require_once MYBB_ROOT . 'inc/datahandlers/user.php';
    $userhandler = new \UserDataHandler('delete');

    $deleteUserResult = $userhandler->delete_user([$userId], $withContent);

    $result = $deleteUserResult['deleted_users'] > 0;

    return $result;
}

function deleteUserContentMetadata(int $userId): bool
{
    global $db;

    $result = true;

    $fieldDefinitions = \amnesia\getPersonalDataFieldDefinitions();

    foreach ($fieldDefinitions as $tableName => $table) {
        if (!empty($table['fields'])) {
            $rowUpdates = \amnesia\getErasureRowUpdatesForFields($table['fields']);

            $result &= $db->update_query($tableName, $rowUpdates, 'uid=' . (int)$userId);
        }
    }

    return $result;
}

function sendErasureRequestVerificationCode(array $user, string $verificationCode): bool
{
    global $mybb, $lang;

    $lang->load('amnesia', true);

    $subject = $lang->amnesia_personal_data_erasure_request_verification_subject;

    $url = $mybb->settings['bburl'] . '/usercp.php?action=personal_data_erasure&verification_code=' . \htmlspecialchars_uni($verificationCode);

    $message = $lang->sprintf(
        $lang->amnesia_personal_data_erasure_request_verification_message,
        \htmlspecialchars_uni($user['username']),
        $mybb->settings['bbname'],
        $url
    );

    return \my_mail($user['email'], $subject, $message);
}

// internal
function getAnonymizedUsername(int $userId): ?string
{
    global $lang;

    $lang->load('amnesia', true);

    $candidate = $lang->amnesia_anonymized_username_prefix . (int)$userId;

    if (!\amnesia\usernameExists($candidate)) {
        return $candidate;
    } else {
        for ($i = 1; $i <= 100; $i++) {
            $length = 3 + floor($i / 2);
            $randomId = strtoupper(\random_str($length));
            $candidate = $lang->amnesia_anonymized_username_prefix . 'X' . $randomId;

            if (!\amnesia\usernameExists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}

function usernameExists(string $username): bool
{
    return (bool)\get_user_by_username($username, [
        'exists' => true,
    ]);
}

function getErasureRowUpdatesForFields(array $fields): array
{
    $rowUpdates = [];

    foreach ($fields as $fieldName => $field) {
        $rowUpdates[$fieldName] = $field['anonymizedValue'];
    }

    return $rowUpdates;
}
