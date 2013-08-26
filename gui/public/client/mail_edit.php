<?php
/**
 * i-MSCP - internet Multi Server Control Panel
 *
 * The contents of this file are subject to the Mozilla Public License
 * Version 1.1 (the "License"); you may not use this file except in
 * compliance with the License. You may obtain a copy of the License at
 * http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS"
 * basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See the
 * License for the specific language governing rights and limitations
 * under the License.
 *
 * The Original Code is "VHCS - Virtual Hosting Control System".
 *
 * The Initial Developer of the Original Code is moleSoftware GmbH.
 * Portions created by Initial Developer are Copyright (C) 2001-2006
 * by moleSoftware GmbH. All Rights Reserved.
 *
 * Portions created by the ispCP Team are Copyright (C) 2006-2010 by
 * isp Control Panel. All Rights Reserved.
 *
 * Portions created by the i-MSCP Team are Copyright (C) 2010-2013 by
 * i-MSCP - internet Multi Server Control Panel. All Rights Reserved.
 *
 * @category	i-MSCP
 * @package		iMSCP_Core
 * @subpackage	Client
 * @copyright   2001-2006 by moleSoftware GmbH
 * @copyright   2006-2010 by ispCP | http://isp-control.net
 * @copyright   2010-2013 by i-MSCP | http://i-mscp.net
 * @author      ispCP Team
 * @author      i-MSCP Team
 * @link        http://i-mscp.net
 */

/************************************************************************************
 * script functions
 */

/**
 * Normalize forward email addresses list.
 *
 * @access private
 * @throws iMSCP_Exception
 * @param string|array $forwardAddresses String containings email forward addresses, each separated by a line break,
 *                                       space or comma or an indexed array where each value is an email forward address
 * @param string $convertTo Tell in which format the forward email addresses must be  converted (decode_idna|encode_idna)
 * @return array Forward email addresses
 */
function _client_normalizeForwardAddresses($forwardAddresses, $convertTo = 'decode_idna')
{
	if(!is_array($forwardAddresses)) {
		$forwardAddresses  = array_unique(
			preg_split('/[\n\s,]+/', trim($forwardAddresses), 0, PREG_SPLIT_NO_EMPTY));
	}

	if($convertTo != 'decode_idna' && $convertTo != 'encode_idna') {
		throw new iMSCP_Exception('Wrong value for $convertTo argument.');
	}

	foreach ($forwardAddresses as &$forwardAddress) {
		if(($pos = strrpos($forwardAddress, '@')) !== false) {
			$forwardAddress = substr($forwardAddress, 0, $pos + 1) .
				$convertTo(substr($forwardAddress, $pos + 1));
		}
	}

	return $forwardAddresses;
}

/**
 * Gets mail account data.
 *
 * Note: For performance reasons, the data are retrieved once.
 *
 * @param int $mailAccountId Mail account unique identifier
 * @return array Mail account data
 */
function client_getMailAccountData($mailAccountId)
{
	static $mailAccountData = null;

	if (null === $mailAccountData) {
		$domainProperties = get_domain_default_props($_SESSION['user_id']);

		$query = '
			SELECT
				`t1`.*, t2.`domain_id`
			FROM
				`mail_users` `t1`, `domain` `t2`
			WHERE
				`t1`.`mail_id` = ?
			AND
				`t2`.`domain_id` = t1.`domain_id`
			AND
				`t2`.`domain_name` = ?
		';
		$stmt = exec_query($query, array($mailAccountId, $domainProperties['domain_name']));

		if ($stmt->rowCount()) {
			$mailAccountData = $stmt->fetchRow();
		} else {
			set_page_message(tr('Email account not found.'), 'error');
			redirectTo('mail_accounts.php');
			exit; // Useless but avoid IDE warning about possible undefined variable
		}

		if (!empty($_POST)) {
			// Forward addresses data
			if (isset($_POST['forwardAccount']) || isset($_POST['forwardList'])) {
				$mailAccountData['mail_forward_previous'] = _client_normalizeForwardAddresses(
					$mailAccountData['mail_forward'], 'encode_idna');

					$mailAccountData['mail_forward'] = _client_normalizeForwardAddresses(
						clean_input($_POST['forwardList']), 'encode_idna');
			} else {
				$mailAccountData['mail_forward'] = '_no_';
			}

			// Password data
			if($mailAccountData['mail_pass'] != '_no_' &&
				(!empty($_POST['password']) || !empty($_POST['passwordConfirmation']))
			) {
				$mailAccountData['mail_pass'] = clean_input($_POST['password']);
				$mailAccountData['mail_pass_confirmation'] = clean_input($_POST['passwordConfirmation']);
			}
		}
	}

	return $mailAccountData;
}

/**
 * Update mail account.
 *
 * @param array $mailAccountData Mail account data
 * @return bool TRUE on success, FALSE otherwise
 */
function client_UpdateMailAccount($mailAccountData)
{
	/** @var $cfg iMSCP_Config_Handler_File */
	$cfg =iMSCP_Registry::get('config');

	$passwordUpdate = $forwardAddressesUpdate = false;

	// Password validation
	if($mailAccountData['mail_pass'] != '_no_' && (!empty($_POST['password']) || !empty($_POST['passwordConfirmation']))) {
		if($mailAccountData['mail_pass'] !== $mailAccountData['mail_pass_confirmation']) {
			set_page_message(tr("Passwords do not match."), 'error');
		}

		checkPasswordSyntax($mailAccountData['mail_pass'], "/[`\xb4'\"\\\\\x01-\x1f\015\012|<>^$]/i");
		$passwordUpdate = true;
	}

	// Forward addresses validation
	if($mailAccountData['mail_forward'] != '_no_' &&
	   $mailAccountData['mail_forward'] !== $mailAccountData['mail_forward_previous']
	) {
		if(!empty($mailAccountData['mail_forward'])) {
			foreach ($mailAccountData['mail_forward'] as $forwardAddress) {
				if (!chk_email($forwardAddress)) {
					set_page_message(tr('Wrong syntax for the %s forward email address.', '<strong>' . decode_idna($forwardAddress) . '</strong>'), 'error');
				} elseif ($forwardAddress == $mailAccountData['mail_addr']) {
					set_page_message(tr('You cannot forward %s on himself.', '<strong>' . $mailAccountData['mail_addr'] . '</strong>'), 'error');
				}
			}

			// Check if the mail type doesn't contain xxx_forward and append it if needed
			if (strpos($mailAccountData['mail_type'], '_forward') === false) {
				if ($mailAccountData['mail_type'] == MT_NORMAL_MAIL) {
					$mailAccountData['mail_type'] .= ',' . MT_NORMAL_FORWARD;
				} elseif ($mailAccountData['mail_type'] == MT_ALIAS_MAIL) {
					$mailAccountData['mail_type'] .= ',' . MT_ALIAS_FORWARD;
				} elseif ($mailAccountData['mail_type'] == MT_SUBDOM_MAIL) {
					$mailAccountData['mail_type'] .= ',' . MT_SUBDOM_FORWARD;
				} elseif ($mailAccountData['mail_type'] == MT_ALSSUB_MAIL) {
					$mailAccountData['mail_type'] .= ',' . MT_ALSSUB_FORWARD;
				}
			}
		} else {
			set_page_message(tr('You must enter a least one forward address.'), 'error');
		}

		$forwardAddressesUpdate = true;
	}

	if($mailAccountData['mail_forward'] == '_no_') {
		if(strpos($mailAccountData['mail_type'], '_forward') !== false) {
			// Check if mail type was a forward type and remove it
			$mailAccountData['mail_type'] = preg_replace(
				'/,[a-z]+_forward$/', '', $mailAccountData['mail_type']);

			$forwardAddressesUpdate = true;
		}
	} else {
		// Prepare data for database insertion
		$mailAccountData['mail_forward'] = implode(',', $mailAccountData['mail_forward']);
	}

	if (!Zend_Session::namespaceIsset('pageMessages')) {
		if ($passwordUpdate || $forwardAddressesUpdate) {

			iMSCP_Events_Manager::getInstance()->dispatch(
				iMSCP_Events::onBeforeEditMail, array('mailId' => $mailAccountData['mail_id'])
			);

			$query = "
				UPDATE
					`mail_users`
				SET
					`mail_pass` = ?, `mail_forward` = ?, `mail_type` = ?, `status` = ?
				WHERE
					`mail_id` = ?
			";
			exec_query($query, array(
									$mailAccountData['mail_pass'],
									$mailAccountData['mail_forward'],
									$mailAccountData['mail_type'],
									$cfg->ITEM_TOCHANGE_STATUS,
									$mailAccountData['mail_id']));

			iMSCP_Events_Manager::getInstance()->dispatch(
				iMSCP_Events::onAfterEditMail, array('mailId' => $mailAccountData['mail_id'])
			);

			// Sending request to the i-MSCP daemon for backend process
			send_request();
			set_page_message(tr('Email account scheduled for update.'), 'success');
			write_log("{$_SESSION['user_logged']}: updated email account: {$mailAccountData['mail_addr']}", E_USER_NOTICE);
		} else {
			set_page_message(tr("Nothing has been changed."), 'info');
		}

		return true;
	}

	return false;
}

/**
 * Generates edit form.
 *
 * @param iMSCP_pTemplate $tpl Template engine
 * @param array $mailAccountData Mail account data
 * @return void
 */
function client_generateEditForm($tpl, $mailAccountData)
{
	/** @var $cfg iMSCP_Config_Handler_File */
	$cfg =iMSCP_Registry::get('config');

	if($mailAccountData['mail_pass'] == '_no_') { // Forward only mail account
		$tpl->assign('PASSWORD_FRM', '');
	}

	$htmlChecked = $cfg->HTML_CHECKED;

	$tpl->assign(
		array(
			 'MAIL_ID_VAL' => $mailAccountData['mail_id'],
			 'MAIL_ADDRESS_VAL' => tohtml($mailAccountData['mail_addr']),
			 'TR_MAIL_ACCOUNT' => tr('Email account'),
			 'FORWARD_ACCOUNT_CHECKED' => ($mailAccountData['mail_forward'] != '_no_') ? $htmlChecked : '',
			 'FORWARD_LIST_VAL' => ($mailAccountData['mail_forward'] != '_no_' && $mailAccountData['mail_forward'] != '')
				 ? tohtml(implode("\n", _client_normalizeForwardAddresses($mailAccountData['mail_forward'], 'decode_idna'))) : ''));
}

/************************************************************************************
 * Main script
 */

// Include core library
require_once 'imscp-lib.php';

iMSCP_Events_Manager::getInstance()->dispatch(iMSCP_Events::onClientScriptStart);

check_login('user');

customerHasFeature('mail') or showBadRequestErrorPage();

/** @var $cfg iMSCP_Config_Handler_File */
$cfg = iMSCP_Registry::get('config');

if(isset($_GET['id'])) {
	$mailAccountData = client_getMailAccountData((int) $_GET['id']);
} else {
	showBadRequestErrorPage();
}

if(!empty($_POST) && client_updateMailAccount($mailAccountData)) {
	redirectTo('mail_accounts.php');
}

$tpl = new iMSCP_pTemplate();
$tpl->define_dynamic('layout', 'shared/layouts/ui.tpl');
$tpl->define_dynamic(
	array(
		 'page' => 'client/mail_edit.tpl',
		 'page_message' => 'layout',
		 'logged_frm' => 'page',
		 'password_frm' => 'page'));

$tpl->assign(
	array(
		 'TR_PAGE_TITLE' => tr('Client / Email / Overview /  Edit Email Account'),
		 'ISP_LOGO' => layout_getUserLogo(),
		 'TR_PASSWORD' => tr('Password'),
		 'TR_PASSWORD_CONFIRMATION' => tr('Password confirmation'),
		 'TR_FORWARD_ACCOUNT' => tr('Forward account'),
		 'TR_FORWARD_TO' => tr('Forward to'),
		 'TR_YES' => tr('yes'),
		 'TR_NO' => tr('no'),
		 'TR_HELP' => tr('help'),
		 'TR_FWD_HELP' => tr('Separate multiple email addresses with a space, a comma or a line-break.'),
		 'TR_UPDATE' => tr('Update'),
		 'TR_CANCEL' => tr('Cancel')));

generateNavigation($tpl);
client_generateEditForm($tpl, $mailAccountData);
generatePageMessage($tpl);

$tpl->parse('LAYOUT_CONTENT', 'page');

iMSCP_Events_Manager::getInstance()->dispatch(iMSCP_Events::onClientScriptEnd, array('templateEngine' => $tpl));

$tpl->prnt();
