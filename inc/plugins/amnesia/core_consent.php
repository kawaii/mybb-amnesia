<?php

namespace amnesia;

// actions
function setPrivacyPolicyLastReadForUser(int $userId, int $time): bool
{
    global $db, $plugins;

    if (
        $time <= \TIME_NOW &&
        $time >= \amnesia\getCacheValue('privacy_policy_date')
    ) {
        $result = (bool)$db->update_query('users', [
            'privacy_policy_last_read' => (int)$time,
        ], "uid=" . (int)$userId);

        $args = [
            'userId' => $userId,
            'time' => $time,
            'result' => $result,
        ];
        $plugins->run_hooks('amnesia_set_privacy_policy_last_read_for_user', $args);

        return $result;
    } else {
        return false;
    }
}

// logic
function privacyPolicyAgreementRequiredForCurrentUser(): bool
{
    global $mybb;

    if ($mybb->user['uid'] !== 0) {
        return (
            \amnesia\getSettingValue('privacy_policy_require_agreement') &&
            $mybb->user['privacy_policy_last_read'] < \amnesia\getCacheValue('privacy_policy_date')
        );
    } else {
        return false;
    }
}

function getPrivacyPolicyUrl(): string
{
    global $mybb;

    return $mybb->settings['bburl'] . '/misc.php?action=help&hid=' . (int)\amnesia\getSettingValue('privacy_policy_document_id');
}

// internal
function reloadPrivacyPolicyDateFromSettings(): void
{
    global $plugins;

    try {
        $time = strtotime(\amnesia\getSettingValue('privacy_policy_date'));

        if ($time === false) {
            $time = 0;
        }
    } catch (\Exception $e) {
        $time = 0;
    }

    \amnesia\updateCache([
        'privacy_policy_date' => $time,
    ]);

    $plugins->run_hooks('amnesia_reload_privacy_policy_date', $time);
}
