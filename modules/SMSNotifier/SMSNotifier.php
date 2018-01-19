<?php
/* +**********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * Contributor(s): YetiForce.com
 * ********************************************************************************** */

class SMSNotifier extends Vtiger_CRMEntity
{

	public $table_name = 'vtiger_smsnotifier';
	public $table_index = 'smsnotifierid';

	/** Indicator if this is a custom module or standard module */
	public $IsCustomModule = true;

	/**
	 * Mandatory table for supporting custom fields.
	 */
	public $customFieldTable = ['vtiger_smsnotifiercf', 'smsnotifierid'];

	/**
	 * Mandatory for Saving, Include tables related to this module.
	 */
	public $tab_name = ['vtiger_crmentity', 'vtiger_smsnotifier', 'vtiger_smsnotifiercf'];

	/**
	 * Mandatory for Saving, Include tablename and tablekey columnname here.
	 */
	public $tab_name_index = [
		'vtiger_crmentity' => 'crmid',
		'vtiger_smsnotifier' => 'smsnotifierid',
		'vtiger_smsnotifiercf' => 'smsnotifierid'];

	/**
	 * Mandatory for Listing (Related listview)
	 */
	public $list_fields = [
		/* Format: Field Label => Array(tablename, columnname) */
		// tablename should not have prefix 'vtiger_'
		'Message' => ['smsnotifier', 'message'],
		'Assigned To' => ['crmentity', 'smownerid']
	];
	public $list_fields_name = [
		/* Format: Field Label => fieldname */
		'Message' => 'message',
		'Assigned To' => 'assigned_user_id'
	];
	// Make the field link to detail view
	public $list_link_field = 'message';
	// For Popup listview and UI type support
	public $search_fields = [
		/* Format: Field Label => Array(tablename, columnname) */
		// tablename should not have prefix 'vtiger_'
		'Message' => ['smsnotifier', 'message']
	];
	public $search_fields_name = [
		/* Format: Field Label => fieldname */
		'Message' => 'message'
	];
	// For Popup window record selection
	public $popup_fields = ['message'];
	// Should contain field labels
	//var $detailview_links = Array ('Message');
	// For Alphabetical search
	public $def_basicsearch_col = 'message';
	// Column value to use on detail view record text display
	public $def_detailview_recname = 'message';
	// Required Information for enabling Import feature
	public $required_fields = ['assigned_user_id' => 1];
	// Callback function list during Importing
	public $special_functions = ['set_import_assigned_user'];
	public $default_order_by = '';
	public $default_sort_order = 'DESC';
	// Used when enabling/disabling the mandatory fields for the module.
	// Refers to vtiger_field.fieldname values.
	public $mandatory_fields = ['createdtime', 'modifiedtime', 'message', 'assigned_user_id'];

	public function __construct()
	{
		$this->column_fields = getColumnFields(vglobal('currentModule'));
		$this->db = PearDatabase::getInstance();
	}

	public function getSortOrder()
	{
		$currentModule = vglobal('currentModule');

		$sortorder = $this->default_sort_order;
		if (!\App\Request::_isEmpty('sorder'))
			$sortorder = \App\Request::_get('sorder');
		else if ($_SESSION[$currentModule . '_Sort_Order'])
			$sortorder = $_SESSION[$currentModule . '_Sort_Order'];

		return $sortorder;
	}

	public function getOrderBy()
	{
		$orderby = $this->default_order_by;
		if (!\App\Request::_isEmpty('order_by'))
			$orderby = \App\Request::_get('order_by');
		else if ($_SESSION[$currentModule . '_Order_By'])
			$orderby = $_SESSION[$currentModule . '_Order_By'];
		return $orderby;
	}

	/**
	 * Apply security restriction (sharing privilege) query part for List view.
	 */
	public function getListViewSecurityParameter($module)
	{
		$current_user = vglobal('current_user');
		require('user_privileges/user_privileges_' . $current_user->id . '.php');
		require('user_privileges/sharing_privileges_' . $current_user->id . '.php');

		$sec_query = '';
		$tabid = \App\Module::getModuleId($module);

		if ($is_admin === false && $profileGlobalPermission[1] == 1 && $profileGlobalPermission[2] == 1 && $defaultOrgSharingPermission[$tabid] == 3) {

			$sec_query .= " && (vtiger_crmentity.smownerid in($current_user->id) || vtiger_crmentity.smownerid IN
					(
						SELECT vtiger_user2role.userid FROM vtiger_user2role
						INNER JOIN vtiger_users ON vtiger_users.id=vtiger_user2role.userid
						INNER JOIN vtiger_role ON vtiger_role.roleid=vtiger_user2role.roleid
						WHERE vtiger_role.parentrole LIKE '" . $current_user_parent_role_seq . "::%'
					)
					OR vtiger_crmentity.smownerid IN
					(
						SELECT shareduserid FROM vtiger_tmp_read_user_sharing_per
						WHERE userid=" . $current_user->id . " && tabid=" . $tabid . "
					)
					OR
						(";

			// Build the query based on the group association of current user.
			if (sizeof($current_user_groups) > 0) {
				$sec_query .= " vtiger_groups.groupid IN (" . implode(",", $current_user_groups) . ") || ";
			}
			$sec_query .= " vtiger_groups.groupid IN
						(
							SELECT vtiger_tmp_read_group_sharing_per.sharedgroupid
							FROM vtiger_tmp_read_group_sharing_per
							WHERE userid=" . $current_user->id . " and tabid=" . $tabid . "
						)";
			$sec_query .= ")
				)";
		}
		return $sec_query;
	}

	/**
	 * Create query to export the records.
	 */
	public function createExportQuery($where)
	{
		$current_user = vglobal('current_user');
		$thismodule = \App\Request::_get('module');

		include('include/utils/ExportUtils.php');

		//To get the Permitted fields query and the permitted fields list
		$sql = getPermittedFieldsQuery($thismodule, 'detail_view');

		$fields_list = getFieldsListFromQuery($sql);

		$query = "SELECT $fields_list, vtiger_users.user_name AS user_name
					FROM vtiger_crmentity INNER JOIN $this->table_name ON vtiger_crmentity.crmid=$this->table_name.$this->table_index";

		if (!empty($this->customFieldTable)) {
			$query .= " INNER JOIN " . $this->customFieldTable[0] . " ON " . $this->customFieldTable[0] . '.' . $this->customFieldTable[1] .
				" = $this->table_name.$this->table_index";
		}

		$query .= " LEFT JOIN vtiger_groups ON vtiger_groups.groupid = vtiger_crmentity.smownerid";
		$query .= " LEFT JOIN vtiger_users ON vtiger_crmentity.smownerid = vtiger_users.id and vtiger_users.status='Active'";

		$linkedModulesQuery = $this->db->pquery("SELECT distinct fieldname, columnname, relmodule FROM vtiger_field" .
			" INNER JOIN vtiger_fieldmodulerel ON vtiger_fieldmodulerel.fieldid = vtiger_field.fieldid" .
			" WHERE uitype='10' && vtiger_fieldmodulerel.module=?", [$thismodule]);
		$linkedFieldsCount = $this->db->numRows($linkedModulesQuery);

		for ($i = 0; $i < $linkedFieldsCount; $i++) {
			$related_module = $this->db->queryResult($linkedModulesQuery, $i, 'relmodule');
			$fieldname = $this->db->queryResult($linkedModulesQuery, $i, 'fieldname');
			$columnname = $this->db->queryResult($linkedModulesQuery, $i, 'columnname');

			\vtlib\Deprecated::checkFileAccessForInclusion("modules/$related_module/$related_module.php");
			require_once("modules/$related_module/$related_module.php");
			$other = new $related_module();
			\VtlibUtils::vtlibSetupModulevars($related_module, $other);

			$query .= " LEFT JOIN $other->table_name ON $other->table_name.$other->table_index = $this->table_name.$columnname";
		}

		$where_auto = " vtiger_crmentity.deleted=0";

		if ($where != '')
			$query .= " WHERE ($where) && $where_auto";
		else
			$query .= " WHERE $where_auto";

		require('user_privileges/user_privileges_' . $current_user->id . '.php');
		require('user_privileges/sharing_privileges_' . $current_user->id . '.php');

		// Security Check for Field Access
		if ($is_admin === false && $profileGlobalPermission[1] == 1 && $profileGlobalPermission[2] == 1 && $defaultOrgSharingPermission[7] == 3) {
			//Added security check to get the permitted records only
			$query = $query . " " . getListViewSecurityParameter($thismodule);
		}
		return $query;
	}

	/**
	 * Transform the value while exporting (if required)
	 */
	public function transformExportValue($key, $value)
	{
		return parent::transformExportValue($key, $value);
	}

	/**
	 * Invoked when special actions are performed on the module.
	 * @param string Module name
	 * @param string Event Type (module.postinstall, module.disabled, module.enabled, module.preuninstall)
	 */
	public function moduleHandler($modulename, $eventType)
	{
		//adds sharing accsess
		$SMSNotifierModule = vtlib\Module::getInstance('SMSNotifier');
		vtlib\Access::setDefaultSharing($SMSNotifierModule);
	}
}
