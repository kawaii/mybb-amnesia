<?php

function task_amnesia_erasure(array $task): void
{
    global $lang;

    $lang->load('amnesia');

    if (function_exists(('\amnesia\completePendingErasureRequests'))) {
        \amnesia\completePendingErasureRequests();

        add_task_log($task, $lang->amnesia_erasure_task_ran);
    }
}
