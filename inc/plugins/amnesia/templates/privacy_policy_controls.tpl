<div style="padding: 10px; text-align: center;">
    <form action="misc.php?action=help&hid={$helpdoc['hid']}" method="post">
        <input type="hidden" name="url_before_redirect" value="{$urlBeforeRedirect}" />
        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
        <input type="hidden" name="privacy_policy_load_date" value="{$loadTimeUnix}" />
        <input type="submit" name="accept_privacy_policy" value="{$lang->amnesia_privacy_policy_accept}" class="button" />
    </form>
</div>
{$personalDataLinks}