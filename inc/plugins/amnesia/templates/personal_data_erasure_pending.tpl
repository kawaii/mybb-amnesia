<head>
    <title>{$mybb->settings['bbname']}</title>
    {$headerinclude}
</head>
<body>
{$header}
<br />
<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
    <tr>
        <td class="thead"><span class="smalltext"><strong>{$lang->amnesia_personal_data_erasure_pending}</strong></span></td>
    </tr>
    <tr>
        <td class="trow1">{$message}</td>
    </tr>
</table>
<div style="padding: 10px; text-align: center;">
    <form action="usercp.php?action=personal_data_erasure" method="post">
        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
        <input type="submit" name="cancel_personal_data_erasure" value="{$lang->amnesia_personal_data_erasure_cancel}" class="button" />
    </form>
</div>
{$footer}
</body>
</html>