<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
    <tr>
        <td class="thead" colspan="2"><strong>{$lang->amnesia_personal_data_export}</strong></td>
    </tr>
    <tr>
        <td class="trow1" colspan="2">
            {$lang->amnesia_personal_data_export_description}
        </td>
    </tr>
    <tr>
        <td class="tcat" colspan="2">
            <strong>{$lang->password_confirmation}</strong>
        </td>
    </tr>
    <tr>
        <td class="trow1" width="40%">
            <strong>{$lang->current_password}</strong>
        </td>
        <td class="trow1" width="60%">
            <input type="password" class="textbox" name="password" size="25" />
        </td>
    </tr>
</table>
<br/>
<div align="center">
    <input type="submit" class="button" value="{$lang->amnesia_personal_data_export_proceed}" />
</div>