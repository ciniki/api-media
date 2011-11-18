<?php
//
// Description
// -----------
//
// Info
// ----
// Status: alpha
//
// Arguments
// ---------
// user_id: 		The user making the request
// 
// Returns
// -------
//
function ciniki_media_checkAccess($ciniki, $business_id, $method, $media) {

	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuoteIDs.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/logSecurity.php');
	//
	// Load the rulesets for this module
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/media/private/getRulesets.php');
	$rulesets = ciniki_media_getRuleSets($ciniki);

	//
	// Check if the module is turned on for the business
	// Check the business is active
	// Get the ruleset for this module
	//
	$strsql = "SELECT ruleset FROM businesses, business_modules "
		. "WHERE businesses.id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
		. "AND businesses.status = 1 "														// Business is active
		. "AND businesses.id = business_modules.business_id "
		. "AND business_modules.package = 'ciniki' "
		. "AND business_modules.module = 'media' "
		. "";
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashQuery.php');
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'businesses', 'module');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'344', 'msg'=>'Access denied.', 'err'=>$rc['err']));
	}
	if( !isset($rc['module']) || !isset($rc['module']['ruleset']) || $rc['module']['ruleset'] == '' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'319', 'msg'=>'Access denied.'));
	}

	//
	// Check to see if the ruleset is valid
	//
	if( !isset($rulesets[$rc['module']['ruleset']]) ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'320', 'msg'=>'Access denied.'));
	}
	$ruleset = $rc['module']['ruleset'];

	// 
	// Get the rules for the specified method
	//
	$rules = array();
	if( isset($rulesets[$ruleset]['methods']) && isset($rulesets[$ruleset]['methods'][$method]) ) {
		$rules = $rulesets[$ruleset]['methods'][$method];
	} elseif( isset($rulesets[$ruleset]['default']) ) {
		$rules = $rulesets[$ruleset]['default'];
	} else {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'321', 'msg'=>'Access denied.'));
	}

	//
	// Check the data belongs to the business.  If any rows are returned for the listed
	// media with a different business_id, then deny access.
	//
	// Make sure each media id specific belongs to the requested business.  This must
	// be by checking each one.  This ensures that each id is checked, and if the query
	// fails, access will be denied.
	//
	if( $media != null ) {
		$strsql = "SELECT id, business_id FROM media "
			. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. "AND id IN (" . ciniki_core_dbQuoteIDs($ciniki, $media) . ") "
			. "";
		require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbHashIDQuery.php');
		$rc = ciniki_core_dbHashIDQuery($ciniki, $strsql, 'media', 'ids', 'id');
		if( $rc['stat'] != 'ok' ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'345', 'msg'=>'Access denied.', 'err'=>$rc['err']));
		}
		if( !isset($rc['ids']) ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'346', 'msg'=>'Access denied.'));
		}
		foreach($media as $media_id) {
			if( !isset($rc['ids'][$media_id]) || !isset($rc['ids'][$media_id]['business_id']) 
				|| $rc['ids'][$media_id]['business_id'] != $business_id ) {
				ciniki_core_logSecurity($ciniki, $strsql, 347, $method, 'media', $media_id);
				return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'347', 'msg'=>'Access denied.'));
			}
		}
	}
	
	//
	// Apply the rules.  Any matching rule will allow access.
	//

	//
	// If business_group specified, check the session user in the business_users table.
	//
	if( isset($rules['business_group']) && $rules['business_group'] > 0 ) {
		//
		// Compare the session users bitmask, with the bitmask specified in the rules
		// If when AND'd together, any bits are set, they have access.
		//
		$strsql = sprintf("SELECT business_id, user_id FROM business_users "
			. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "' "
			. "AND user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
			. "AND (groups & 0x%x) > 0 ", ciniki_core_dbQuote($ciniki, $rules['business_group']));
		$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'businesses', 'user');
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		// Error if more than one row, or no rows found.
		if( $rc['num_rows'] != 1 ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'322', 'msg'=>'Access denied.'));
		}
		// Double check business_id and user_id match, for single row returned.
		if( $rc['user']['business_id'] == $business_id && $rc['user']['user_id'] = $ciniki['session']['user']['id'] ) {
			// Access Granted!
			return array('stat'=>'ok');
		}
	}

	//
	// When dealing with the master business, a customer can be any business employee from
	// any active business.  This allows them to submit MODULE via ciniki-manage.
	//
	if( isset($rules['customer']) && $rules['customer'] == 'any' && $ciniki['config']['core']['master_business_id'] == $business_id ) {
		$strsql = "SELECT user_id FROM business_users, businesses "
			. "WHERE business_users.user_id = '" . ciniki_core_dbQuote($ciniki, $ciniki['session']['user']['id']) . "' "
			. "AND business_users.business_id = businesses.id "
			. "AND businesses.status = 1 ";
		$rc = mysql_core_dbHashQuery($ciniki, $strsql, 'businesses', 'user');
		if( $rc['stat'] != 'ok' ) {
			return $rc;
		}
		if( $rc['num_rows'] > 0 ) {
			return array('stat'=>'ok');
		}
	} 
	
	// 
	// Check if the session user is a customer of the business
	//
	if( isset($rules['customer']) && $rules['customer'] == 'any' ) {
		// FIXME: finish, there is currently no link between customers and users.  When that is in place, this will work.
	//	sql = "SELECT * FROM customers "
	//		. "WHERE customers.business_id = '" . ciniki_core_dbQuote($ciniki, $business_id) . "'"
	//		. "AND customers.user_id = ";
	//	$rc = mysql_core_dbHashQuery($ciniki, $strsql, 'businesses', 'user');
	//	if( $rc['stat'] != 'ok' ) {
	//		return $rc;
	//	}
	}

	//
	// When checking the rule 'customer'=>'self', the requested method can only be done
	// if the customer making the request is requesting it for themselves.  They can't
	// call the method for another user_id.
	//
//	if( isset($rules['customer']) && $rules['customer'] == 'self' && $ciniki['session']['user']['id'] == $user_id ) {
//		return array('stat'=>'ok');
//	}

	//
	// By default, fail
	//
	return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'323', 'msg'=>'Access denied.'));
}
?>
