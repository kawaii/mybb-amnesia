<?php

namespace amnesia\Hooks;

function admin_load()
{
    global $mybb, $db, $lang, $run_module, $action_file, $page, $sub_tabs;

    $module = 'user';
    $actionFile = 'data_erasure_requests';
    $pageUrl = 'index.php?module=' . $module . '-' . $actionFile;

    if ($run_module == $module && $action_file == $actionFile) {
        $lang->load('amnesia');

        $page->add_breadcrumb_item($lang->amnesia_admin_personal_data_erasure_requests, $pageUrl);

        $sub_tabs['unapproved'] = [
            'link'        => $pageUrl . '&action=unapproved',
            'title'       => $lang->amnesia_admin_personal_data_erasure_requests_unapproved,
            'description' => $lang->amnesia_admin_personal_data_erasure_requests_unapproved_description,
        ];
        $sub_tabs['completed'] = [
            'link'        => $pageUrl . '&action=completed',
            'title'       => $lang->amnesia_admin_personal_data_erasure_requests_completed,
            'description' => $lang->amnesia_admin_personal_data_erasure_requests_completed_description,
        ];

        if ($mybb->input['action'] == 'unapproved' || empty($mybb->input['action'])) {
            if ($mybb->get_input('approve')) {
                $request = \amnesia\getErasureRequest($mybb->get_input('approve', \MyBB::INPUT_INT));

                if ($request) {
                    if ($mybb->request_method == 'post') {
                        if ($mybb->get_input('no')) {
                            \admin_redirect($pageUrl);
                        } else {
                            \amnesia\approveErasureRequest($request);
                            \flash_message($lang->amnesia_admin_personal_data_erasure_request_approved, 'success');
                        }
                    } else {
                        $page->output_confirm_action(
                            $pageUrl . '&amp;approve=' . (int)$request['id'],
                            $lang->amnesia_admin_personal_data_erasure_approve_confirm_message,
                            $lang->amnesia_admin_personal_data_erasure_approve_confirm_title
                        );
                    }
                }
            } elseif ($mybb->get_input('cancel')) {
                $request = \amnesia\getErasureRequest($mybb->get_input('cancel', \MyBB::INPUT_INT));

                if ($request) {
                    if ($mybb->request_method == 'post') {
                        if ($mybb->get_input('no')) {
                            \admin_redirect($pageUrl);
                        } else {
                            \amnesia\cancelErasureRequest($request);
                            \flash_message($lang->amnesia_admin_personal_data_erasure_request_canceled, 'success');
                        }
                    } else {
                        $page->output_confirm_action(
                            $pageUrl . '&amp;cancel=' . (int)$request['id'],
                            $lang->amnesia_admin_personal_data_erasure_cancel_confirm_message,
                            $lang->amnesia_admin_personal_data_erasure_cancel_confirm_title
                        );
                    }
                }
            }

            $page->output_header($lang->amnesia_admin_personal_data_erasure_requests);
            $page->output_nav_tabs($sub_tabs, 'unapproved');

            $query = $db->query("
                SELECT
                    r.*,
                    u.username
                FROM
                    " . $db->table_prefix . "erasure_requests r
                    LEFT JOIN " . $db->table_prefix . "users u ON u.uid = r.user_id
                WHERE
                    completed = 0 AND
                    verified = 1 AND
                    approved = 0
            ");

            $table = new \Table;
            $table->construct_header($lang->amnesia_admin_user, ['width' => '20%', 'class' => 'align_center']);
            $table->construct_header($lang->amnesia_admin_date, ['width' => '20%', 'class' => 'align_center']);
            $table->construct_header($lang->amnesia_admin_scheduled_date, ['width' => '20%', 'class' => 'align_center']);
            $table->construct_header($lang->amnesia_admin_with_content, ['width' => '10%', 'class' => 'align_center']);
            $table->construct_header($lang->amnesia_admin_comment, ['width' => '20%', 'class' => 'align_center']);
            $table->construct_header($lang->options, ['width' => '10%', 'class' => 'align_center']);

            if ($db->num_rows($query)) {
                while ($row = $db->fetch_array($query)) {
                    $profileLink = '<a href="index.php?module=user-users&amp;action=edit&amp;uid=' . $row['user_id'] . '">' . \htmlspecialchars_uni($row['username']) . '</a>';
                    $withContent = $row['with_content'] ? $lang->yes : $lang->no;

                    $popup = new \PopupMenu('controls', $lang->options);
                    $popup->add_item($lang->amnesia_admin_approve, $pageUrl . '&amp;approve=' . $row['id']);
                    $popup->add_item($lang->amnesia_admin_cancel, $pageUrl . '&amp;cancel=' . $row['id']);
                    $controls = $popup->fetch();

                    $table->construct_cell($profileLink, ['class' => 'align_center']);
                    $table->construct_cell(\my_date('relative', $row['date']), ['class' => 'align_center']);
                    $table->construct_cell(\my_date('relative', $row['scheduled_date']), ['class' => 'align_center']);
                    $table->construct_cell($withContent, ['class' => 'align_center']);
                    $table->construct_cell(\htmlspecialchars_uni($row['comment']), ['class' => 'align_center']);
                    $table->construct_cell($controls, ['class' => 'align_center']);
                    $table->construct_row();
                }
            } else {
                $table->construct_cell($lang->amnesia_admin_personal_data_erasure_requests_unapproved_empty, ['colspan' => '6', 'class' =>  'align_center']);
                $table->construct_row();
            }

            $table->output($lang->amnesia_admin_personal_data_erasure_requests);
        } elseif ($mybb->input['action'] == 'completed') {
            $page->output_header($lang->amnesia_admin_personal_data_erasure_requests);
            $page->output_nav_tabs($sub_tabs, 'completed');

            $itemsNum = $db->fetch_field(
                $db->query("
                    SELECT
                        COUNT(id) AS n
                    FROM
                        " . $db->table_prefix . "erasure_requests
                    WHERE
                        completed = 1            
                "),
                'n'
            );

            $listManager = new \amnesia\ListManager([
                'mybb'          => $mybb,
                'baseurl'       => $pageUrl . '&amp;action=completed',
                'order_columns' => ['id', 'username', 'date', 'scheduled_date', 'action_date', 'with_content', 'comment'],
                'order_dir'     => 'desc',
                'items_num'     => $itemsNum,
                'per_page'      => 20,
            ]);

            $table = new \Table;
            $table->construct_header($listManager->link('username', $lang->amnesia_admin_user), ['width' => '15%', 'class' => 'align_center']);
            $table->construct_header($listManager->link('date', $lang->amnesia_admin_date), ['width' => '20%', 'class' => 'align_center']);
            $table->construct_header($listManager->link('scheduled_date', $lang->amnesia_admin_scheduled_date), ['width' => '20%', 'class' => 'align_center']);
            $table->construct_header($listManager->link('action_date', $lang->amnesia_admin_action_date), ['width' => '20%', 'class' => 'align_center']);
            $table->construct_header($listManager->link('with_content', $lang->amnesia_admin_with_content), ['width' => '5%', 'class' => 'align_center']);
            $table->construct_header($listManager->link('comment', $lang->amnesia_admin_comment), ['width' => '20%', 'class' => 'align_center']);

            if ($itemsNum > 0) {
                $query = $db->query("
                    SELECT
                        r.*,
                        u.username
                    FROM
                        " . $db->table_prefix . "erasure_requests r
                        LEFT JOIN " . $db->table_prefix . "users u ON u.uid = r.user_id
                    WHERE
                        completed = 1
                    {$listManager->sql()}
                ");

                while ($row = $db->fetch_array($query)) {
                    if ($row['username'] === null) {
                        $user = $lang->sprintf($lang->amnesia_admin_user_id, $row['user_id']);
                    } else {
                        $user = '<a href="index.php?module=user-users&amp;action=edit&amp;uid=' . $row['user_id'] . '">' . \htmlspecialchars_uni($row['username']) . '</a>';
                    }

                    $withContent = $row['with_content'] ? $lang->yes : $lang->no;

                    $table->construct_cell($user, ['class' => 'align_center']);
                    $table->construct_cell(\my_date('relative', $row['date']), ['class' => 'align_center']);
                    $table->construct_cell(\my_date('relative', $row['scheduled_date']), ['class' => 'align_center']);
                    $table->construct_cell(\my_date('relative', $row['action_date']), ['class' => 'align_center']);
                    $table->construct_cell($withContent, ['class' => 'align_center']);
                    $table->construct_cell(\htmlspecialchars_uni($row['comment']), ['class' => 'align_center']);
                    $table->construct_row();
                }
            } else {
                $table->construct_cell($lang->amnesia_admin_personal_data_erasure_requests_completed_empty, ['colspan' => '6', 'class' =>  'align_center']);
                $table->construct_row();
            }

            $table->output($lang->amnesia_admin_personal_data_erasure_requests_completed);

            echo $listManager->pagination();
        }

        $page->output_footer();
    }
}

function admin_config_settings_change_commit(): void
{
    global $mybb;

    if (isset($mybb->input['upsetting']['amnesia_privacy_policy_date'])) {
        \amnesia\reloadPrivacyPolicyDateFromSettings();
    }
}

function admin_user_action_handler(array &$actions): void
{
    $actions['data_erasure_requests'] = [
        'active' => 'data_erasure_requests',
        'file' => 'data_erasure_requests',
    ];
}

function admin_user_menu(array &$sub_menu): void
{
    global $lang;

    $lang->load('amnesia');

    $sub_menu[] = [
        'id' => 'data_erasure_requests',
        'title' => $lang->amnesia_admin_personal_data_erasure_requests,
        'link' => 'index.php?module=user-data_erasure_requests',
    ];
}
