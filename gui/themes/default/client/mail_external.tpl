
<script type="text/javascript">
    /* <![CDATA[ */
    $(document).ready(function () {
        var i = $("tbody :checkbox:not(':disabled')").change(
                function () {
					if ($("tbody :checkbox:checked").length == i) {
                        $("th :checkbox").prop('checked', true);
                    } else {
                        $("th :checkbox").prop('checked', false);
                    }
                }
        ).length;
        $("th :checkbox").click(
                function (e) {
					if($("tbody :checkbox:not(':disabled')").length != 0){
                    	$("table :checkbox:not(':disabled')").prop('checked', $(this).is(':checked'));
					} else {
						e.preventDefault();
                    }
                }
        );
    });

    function onclick_action(url, domain) {
		return (url.indexOf('delete') == -1 || confirm(sprintf("{TR_DEACTIVATE_MESSAGE}", domain)));
    }
    /* ]]> */
</script>
<form name="mail_external_delete" action="mail_external_delete.php" method="post">
    <table>
		<thead class="ui-widget-header">
        <tr>
            <th style="width:21px;"><label><input type="checkbox"/></label></th>
            <th>{TR_DOMAIN}</th>
            <th>{TR_STATUS}</th>
            <th>{TR_ACTION}</th>
        </tr>
        </thead>
        <tfoot class="ui-widget-header">
        <tr>
            <th style="width:21px;"><label><input type="checkbox"/></label></th>
            <th>{TR_DOMAIN}</th>
            <th>{TR_STATUS}</th>
            <th>{TR_ACTION}</th>
        </tr>
        </tfoot>
		<tbody class="ui-widget-content">
        <!-- BDP: item -->
        <tr>
            <td><label><input type="checkbox" name="{ITEM_TYPE}[]" value="{ITEM_ID}"{DISABLED}/></label></td>
            <td>{DOMAIN}</td>
            <td>{STATUS}</td>
            <td>
                <!-- BDP: activate_link -->
                <a href="{ACTIVATE_URL}" class="icon i_users" onclick="return onclick_action('{ACTIVATE_URL}', '');">{TR_ACTIVATE}</a>
                <!-- EDP: activate_link -->
                <!-- BDP: edit_link -->
                <a href="{EDIT_URL}" class="icon i_edit" onclick="return onclick_action('{EDIT_URL}', '');">{TR_EDIT}</a>
                <!-- EDP: edit_link -->
                <!-- BDP: deactivate_link -->
                <a href="{DEACTIVATE_URL}" class="icon i_delete" onclick="return onclick_action('{DEACTIVATE_URL}', '{DOMAIN}');">{TR_DEACTIVATE}</a>
                <!-- EDP: deactivate_link -->
            </td>
        </tr>
        <!-- EDP: item -->
        </tbody>
    </table>
    <label><input type="submit" name="submit" value="{TR_DEACTIVATE_SELECTED_ITEMS}"/></label>
</form>
