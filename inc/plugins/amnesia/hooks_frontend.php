<?php

namespace amnesia\Hooks;

function global_start(): void
{
    global $mybb;

    switch (\THIS_SCRIPT) {
        case 'member.php':
            if ($mybb->get_input('action') == 'register') {
                \amnesia\loadTemplates([
                    'privacy_policy_signup_statement',
                ], 'amnesia_');
            }

            break;
        case 'misc.php':
            if ($mybb->get_input('action') == 'help') {
                \amnesia\loadTemplates([
                    'privacy_policy_date',
                    'privacy_policy_controls',
                ], 'amnesia_');
            }

            break;
        case 'usercp.php':
            if ($mybb->get_input('action') == 'personal_data_erasure') {
                \amnesia\loadTemplates([
                    'personal_data_erasure',
                    'personal_data_erasure_content',
                    'personal_data_erasure_initiate',
                ], 'amnesia_');
            } elseif ($mybb->get_input('action') == 'personal_data_export') {
                \amnesia\loadTemplates([
                    'personal_data_export',
                    'personal_data_export_verify',
                ], 'amnesia_');
            } else {
                \amnesia\loadTemplates([
                    'personal_data_erasure_link',
                    'personal_data_export_link',
                ], 'amnesia_');
            }

            break;
    }
}

function global_intermediate()
{
    global $mybb, $lang, $templates, $config, $awaitingPersonalDataErasureRequests;

    $awaitingPersonalDataErasureRequests = '';

    if ($mybb->usergroup['cancp'] == 1) {
        $count = \amnesia\getUnapprovedPendingErasureRequestsCount();

        if ($count) {
            $lang->load('amnesia');

            if ($count == 1) {
                $awaiting_message = $lang->amnesia_personal_data_erasure_awaiting_approval_single;
            } else {
                $awaiting_message = $lang->sprintf($lang->amnesia_personal_data_erasure_awaiting_approval_plural, \my_number_format($count));
            }

            if ($mybb->config['hide_admin_links'] != 1) {
                $url = $mybb->settings['bburl'] . '/' . $config['admin_dir'] . '/index.php?module=user-data_erasure_requests';
                $awaiting_message .= $lang->sprintf($lang->amnesia_personal_data_erasure_awaiting_approval_link, $url);
            }

            eval('$awaitingPersonalDataErasureRequests = "' . $templates->get('global_awaiting_activation') . '";');
        }
    }
}

function global_end(): void
{
    global $mybb, $lang,
    $headerinclude, $header, $theme, $footer;

    if (!defined('THIS_SCRIPT') || !\amnesia\pageAlwaysAccessible(\THIS_SCRIPT, $mybb->get_input('action'))) {
        if (\amnesia\personalDataErasurePendingForCurrentUser()) {
            $request = \amnesia\getPendingUserErasureRequest($mybb->user['uid']);

            $lang->load('amnesia');

            $message = $lang->amnesia_personal_data_erasure_pending_message;

            if ($request) {
                if ($request['scheduled_date'] > \TIME_NOW) {
                    $date = \my_date($mybb->settings['dateformat'], $request['scheduled_date']);
                    $message .= ' ' . $lang->sprintf($lang->amnesia_personal_data_erasure_scheduled_for, $date);
                }

                if (\amnesia\getSettingValue('personal_data_erasure_approval')) {
                    $message .= ' ' . $lang->amnesia_personal_data_erasure_approval;
                }
            }

            eval('$page = "' . \amnesia\tpl('personal_data_erasure_pending') . '";');

            \output_page($page);
            exit;
        } elseif (\amnesia\privacyPolicyAgreementRequiredForCurrentUser()) {
            $lang->load('amnesia');
            \redirect(\amnesia\getPrivacyPolicyUrl(), $lang->amnesia_privacy_policy_agreement_required);
        }
    }
}

function usercp_start()
{
    global $mybb, $lang, $personalDataLinks,
    $headerinclude, $header, $theme, $usercpnav, $footer;

    $lang->load('amnesia');

    if ($mybb->get_input('action') == 'personal_data_erasure') {
        if (\amnesia\getSettingValue('personal_data_erasure_type') != 'none') {
            \add_breadcrumb($lang->amnesia_personal_data_erasure);

            $errors = [];

            if ($mybb->get_input('cancel_personal_data_erasure')) {
                if (\verify_post_check($mybb->get_input('my_post_key'))) {
                    $request = \amnesia\getPendingUserErasureRequest($mybb->user['uid']);

                    if ($request) {
                        \amnesia\cancelErasureRequest($request);
                        \redirect($mybb->settings['bburl']);
                    }
                }
            } elseif ($mybb->get_input('password')) {
                if (\verify_post_check($mybb->get_input('my_post_key'))) {
                    if (
                        $mybb->get_input('with_content') != '1' ||
                        (
                            \amnesia\getSettingValue('personal_data_erasure_content') &&
                            \amnesia\getSettingValue('personal_data_erasure_type') == 'deletion'
                        )
                    ) {
                        if (\validate_password_from_uid($mybb->user['uid'], $mybb->get_input('password'))) {
                            if (!\is_super_admin($mybb->user['uid'])) {
                                \amnesia\createErasureRequest($mybb->user, (bool)$mybb->get_input('with_content'), $mybb->get_input('comment'));
                                \redirect('usercp.php', $lang->amnesia_personal_data_erasure_requested);
                            } else {
                                $errors[] = $lang->amnesia_personal_data_erasure_no_super_admin;
                            }
                        } else {
                            $errors[] = $lang->error_invalidpassword;
                        }
                    }
                }
            } elseif ($mybb->get_input('verification_code')) {
                $request = \amnesia\getErasureRequestByUserIdAndVerificationCode($mybb->user['uid'], $mybb->get_input('verification_code'));

                if ($request) {
                    if (\amnesia\erasureRequestVerifiable($request)) {
                        \amnesia\verifyErasureRequest($request);
                        \amnesia\scheduleErasureRequest($request);
                        \redirect($mybb->settings['bburl']);
                    } else {
                        \amnesia\cancelErasureRequest($request);
                        $errors[] = $lang->amnesia_verification_code_invalid;
                    }
                } else {
                    $errors[] = $lang->amnesia_verification_code_invalid;
                }
            }

            if (!isset($content)) {
                if ($errors) {
                    $errorMessage = \inline_error($errors);
                } else {
                    $errorMessage = '';
                }

                $description = $lang->amnesia_personal_data_erasure_description;

                if (\amnesia\getSettingValue('personal_data_erasure_waiting_period_days') != 0) {
                    $description .= ' ' . $lang->sprintf($lang->amnesia_erasure_waiting_period_days, \amnesia\getSettingValue('personal_data_erasure_waiting_period_days'));
                }

                if (\amnesia\getSettingValue('personal_data_erasure_approval')) {
                    $description .= ' ' . $lang->amnesia_personal_data_erasure_approval;
                }

                if (\amnesia\getSettingValue('personal_data_erasure_content') && \amnesia\getSettingValue('personal_data_erasure_type') == 'deletion') {
                    eval('$withContent = "' . \amnesia\tpl('personal_data_erasure_content') . '";');
                } else {
                    $withContent = '';
                }

                eval('$content = "' . \amnesia\tpl('personal_data_erasure_initiate') . '";');
            }

            eval('$page = "' . \amnesia\tpl('personal_data_erasure') . '";');

            \output_page($page);
        }
    } elseif ($mybb->get_input('action') == 'personal_data_export') {
        if (\amnesia\getSettingValue('personal_data_export')) {
            \add_breadcrumb($lang->amnesia_personal_data_export);

            $errors = [];

            if ($mybb->get_input('password')) {
                if (\verify_post_check($mybb->get_input('my_post_key'))) {
                    if (\validate_password_from_uid($mybb->user['uid'], $mybb->get_input('password'))) {
                        $requestId = \amnesia\createExportRequest($mybb->user);
                        $request = \amnesia\getExportRequest($requestId);

                        \amnesia\setSessionTokenForExportRequest($request);

                        \redirect('usercp.php', $lang->amnesia_personal_data_export_requested);
                    } else {
                        $errors[] = $lang->error_invalidpassword;
                    }
                }
            } elseif ($mybb->get_input('verification_code')) {
                $request = \amnesia\getExportRequestByUserIdAndVerificationCode($mybb->user['uid'], $mybb->get_input('verification_code'));

                if ($request) {
                    if (\amnesia\exportRequestActive($request)) {
                        if (isset($mybb->input['my_post_key'])) {
                            if (\verify_post_check($mybb->get_input('my_post_key'))) {
                                \amnesia\verifyExportRequest($request);

                                if (\amnesia\currentSessionTokenForExportRequestValid($request)) {
                                    \amnesia\completeExportRequest($request);
                                    \amnesia\dropSessionToken();
                                    \amnesia\outputUserPersonalDataXml($mybb->user['uid'], \amnesia\getSettingValue('personal_data_export_include_sensitive'));
                                    exit;
                                } else {
                                    \amnesia\invalidateExportRequest($request);
                                    $errors[] = $lang->amnesia_personal_data_export_key_invalid;
                                }
                            }
                        } else {
                            $verificationCode = \htmlspecialchars_uni($mybb->get_input('verification_code'));

                            eval('$content = "' . \amnesia\tpl('personal_data_export_verify') . '";');
                        }
                    } else {
                        \amnesia\invalidateExportRequest($request);
                        $errors[] = $lang->amnesia_verification_code_invalid;
                    }
                } else {
                    $errors[] = $lang->amnesia_verification_code_invalid;
                }
            }

            if (!isset($content)) {
                if ($errors) {
                    $errorMessage = \inline_error($errors);
                } else {
                    $errorMessage = '';
                }

                eval('$content = "' . \amnesia\tpl('personal_data_export_initiate') . '";');
            }

            eval('$page = "' . \amnesia\tpl('personal_data_export') . '";');

            \output_page($page);
        }
    } else {
        $personalDataLinks = '';

        if (\amnesia\getSettingValue('personal_data_export')) {
            $url = 'usercp.php?action=personal_data_export';
            eval('$personalDataLinks .= "' . \amnesia\tpl('personal_data_export_link') . '";');
        }

        if (\amnesia\getSettingValue('personal_data_erasure_type') != 'none') {
            $url = 'usercp.php?action=personal_data_erasure';
            eval('$personalDataLinks .= "' . \amnesia\tpl('personal_data_erasure_link') . '";');
        }
    }
}

function misc_clearcookies(): void
{
    global $remove_cookies;

    $remove_cookies[] = 'personal_data_export_session_token';
}

function misc_help_helpdoc_end(): void
{
    global $mybb, $lang, $helpdoc, $footer;

    if ($helpdoc['hid'] == \amnesia\getSettingValue('privacy_policy_document_id')) {
        $lang->load('amnesia');

        $privacyPolicyDate = \amnesia\getCacheValue('privacy_policy_date');

        // controls
        if ($mybb->user['uid'] != 0 && $mybb->user['privacy_policy_last_read'] <= $privacyPolicyDate) {
            if ($mybb->request_method == 'post' && $mybb->get_input('accept_privacy_policy')) {
                if (\verify_post_check($mybb->get_input('my_post_key'))) {
                    $result = \amnesia\setPrivacyPolicyLastReadForUser($mybb->user['uid'], $mybb->get_input('privacy_policy_load_date', \MyBB::INPUT_INT));

                    if ($result) {
                        if (strpos($mybb->get_input('url_before_redirect'), $mybb->settings['bburl'] . '/') === 0) {
                            $redirectUrl = $mybb->get_input('url_before_redirect');
                        } else {
                            $redirectUrl = $mybb->settings['bburl'];
                        }

                        \redirect($redirectUrl, $lang->amnesia_privacy_policy_accepted);
                    }
                } else {
                    \error($lang->invalid_post_code);
                }
            }

            $loadTimeUnix = \TIME_NOW;
            $urlBeforeRedirect = \htmlspecialchars_uni($_SERVER['HTTP_REFERER']);

            $personalDataLinks = '';

            if (\amnesia\getSettingValue('personal_data_export')) {
                $url = 'usercp.php?action=personal_data_export';
                eval('$personalDataLinks .= "' . \amnesia\tpl('personal_data_export_link') . '";');
            }

            if (\amnesia\getSettingValue('personal_data_erasure_type') != 'none') {
                $url = 'usercp.php?action=personal_data_erasure';
                eval('$personalDataLinks .= "' . \amnesia\tpl('personal_data_erasure_link') . '";');
            }

            eval('$footer = "' . \amnesia\tpl('privacy_policy_controls') . '" . $footer;');
        }

        // date
        $privacyPolicyDateFormatted = \my_date('relative', $privacyPolicyDate);

        if ($privacyPolicyDate <= \TIME_NOW) {
            $privacyPolicyDateUpdated = $lang->sprintf($lang->amnesia_privacy_policy_date, $privacyPolicyDateFormatted);
        } else {
            $privacyPolicyDateUpdated = '';
        }

        eval('$footer = "' . \amnesia\tpl('privacy_policy_date') . '" . $footer;');
    }
}

function member_register_agreement(): void
{
    global $lang, $privacyPolicyAgreement;

    if (\amnesia\getSettingValue('privacy_policy_require_agreement')) {
        $lang->load('amnesia');

        $url = \amnesia\getPrivacyPolicyUrl();

        $statement = $lang->sprintf($lang->amnesia_privacy_policy_signup_statement, $url);
        $privacyPolicyDate = \TIME_NOW;

        eval('$privacyPolicyAgreement = "' . \amnesia\tpl('privacy_policy_signup_statement') . '";');
    } else {
        $privacyPolicyAgreement = '';
    }
}

function member_register_end(): void
{
    global $mybb, $customfields;

    $customfields .= '<input type="hidden" name="privacy_policy_date" value="' . $mybb->get_input('privacy_policy_date', \MyBB::INPUT_INT) . '" />';
}

function member_do_register_end(): void
{
    global $mybb, $user_info;

    $date = $mybb->get_input('privacy_policy_date', \MyBB::INPUT_INT);

    if ($date) {
        \amnesia\setPrivacyPolicyLastReadForUser($user_info['uid'], $date);
    }
}
