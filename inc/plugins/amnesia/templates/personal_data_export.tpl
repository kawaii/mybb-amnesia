<html>
<head>
    <title>{$mybb->settings['bbname']} - {$lang->amnesia_personal_data_export}</title>
    {$headerinclude}
</head>
<body>
{$header}
<form action="usercp.php?action=personal_data_export" method="post">
    <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
    <table width="100%" border="0" align="center">
        <tr>
            {$usercpnav}
            <td valign="top">
                {$errorMessage}
                {$content}
            </td>
        </tr>
    </table>
</form>
{$footer}
</body>
</html>