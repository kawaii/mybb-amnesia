<table border="0" cellspacing="{$theme['borderwidth']}" cellpadding="{$theme['tablespace']}" class="tborder">
    <tr>
        <td class="thead" colspan="2"><strong>{$lang->amnesia_personal_data_erasure}</strong></td>
    </tr>
    <tr>
        <td class="trow1" colspan="2">
            {$description}
        </td>
    </tr>
    <tr>
        <td class="tcat" colspan="2">
            <strong>{$lang->amnesia_personal_data_erasure_options}</strong>
        </td>
    </tr>
    {$withContent}
    <tr>
        <td class="trow1" width="40%">
            <strong>{$lang->amnesia_personal_data_erasure_comment}</strong>
            <br>
            <span class="smalltext">{$lang->amnesia_personal_data_erasure_comment_description}</span>
        </td>
        <td class="trow1" width="60%">
            <label>
                <textarea class="textbox" name="comment" cols="60" rows="6"></textarea>
            </label>
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
    <input type="submit" class="button" value="{$lang->amnesia_personal_data_erasure_proceed}" />
</div>