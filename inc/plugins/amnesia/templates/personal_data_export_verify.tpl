<input type="hidden" name="verification_code" value="{$verificationCode}" />

<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
    <tr>
        <td class="thead">
            <strong>{$lang->amnesia_personal_data_export}</strong>
        </td>
    </tr>
    <tr>
        <td class="trow1">
            {$lang->amnesia_personal_data_export_verify}
        </td>
    </tr>
</table>
<br/>
<div style="text-align: center;">
    <input type="submit" class="button" value="{$lang->amnesia_personal_data_export_download}" />
</div>