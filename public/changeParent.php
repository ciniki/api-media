<?php
//
// Description
// -----------
// This function will move a piece of media from one
// parent_id to another.
//
// Info
// ----
// Status: 				defined
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:			The business the image is attached to.
// media_id:			The ID if the media to be marked as deleted.
// parent_id:			The new parent_id of the media
//
// Returns
// -------
// <rsp stat="ok"/>
//
function ciniki_media_changeParent($ciniki) {
	//
	// Check args
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbUpdate');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbCount');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbDelete');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
		'media_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Media'), 
		'parent_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Parent'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];

    //  
	// Make sure this module is activated, and 
	// check session user permission to run this function for this business
	// check the media requested is attached to the business
	//  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'media', 'private', 'checkAccess');
	$media_ids = array((int)$args['media_id']);
	if( $args['parent_id'] > 0 ) {
		//
		// Make sure this business owns the parent_id as well, otherwise the media could be 
		// attached to another business
		//
		$media_ids[] = (int)$args['parent_id'];
	}
	$rc = ciniki_media_checkAccess($ciniki, $args['business_id'], 'ciniki.media.changeParent', $media_ids);
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}

	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.media');
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}

	//
	// Check the original parents
	//
	$strsql = "SELECT parent_id FROM ciniki_media "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND id = '" . ciniki_core_dbQuote($ciniki, $args['media_id']) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.media', 'parent');
	if( $rc['stat'] != 'ok' ) { 
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.media');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'351', 'msg'=>'Unable to move', 'err'=>$rc['err']));
	}
	if( !isset($rc['parent']) || !isset($rc['parent']['parent_id']) || $rc['parent']['parent_id'] < 0 ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.media');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'352', 'msg'=>'Unable to move'));
	}
	$old_parent_id = $rc['parent']['parent_id'];

	//
	// Update the parent_id
	//
	$strsql = "UPDATE ciniki_media SET parent_id = '" . ciniki_core_dbQuote($ciniki, $args['parent_id']) . "', "
		. "last_updated = UTC_TIMESTAMP() "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND id = '" . ciniki_core_dbQuote($ciniki, $args['media_id']) . "' "
		. "";
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.media', 'info');
	if( $rc['stat'] != 'ok' ) { 	
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.media');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'353', 'msg'=>'Unable to move', 'err'=>$rc['err']));
	}

	//
	// If the original parent the media was in is not the HOME folder, then,
	// check if the original album is now empty and should be deleted.
	//
	if( $old_parent_id > 0 ) {
		$strsql = "SELECT parent_id, COUNT(id) FROM ciniki_media "
			. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
			. "AND parent_id = '" . ciniki_core_dbQuote($ciniki, $old_parent_id) . "' "
			. "GROUP BY parent_id ";
		$rc = ciniki_core_dbCount($ciniki, $strsql, 'ciniki.media', 'items');
		if( $rc['stat'] != 'ok' ) { 	
			ciniki_core_dbTransactionRollback($ciniki, 'ciniki.media');
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'354', 'msg'=>'Unable to move', 'err'=>$rc['err']));
		}
		//
		// If no rows returned, then nothing left, and album can be removed
		//
		if( !isset($rc['items']) || !isset($rc['items'][$old_parent_id]) || $rc['items'][$old_parent_id] < 1 ) {
			$strsql = "DELETE FROM ciniki_media "
				. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
				. "AND id = '" . ciniki_core_dbQuote($ciniki, $old_parent_id) . "' "
				. "";
			$rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.media');
			if( $rc['stat'] != 'ok' ) { 	
				ciniki_core_dbTransactionRollback($ciniki, 'ciniki.media');
				return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'355', 'msg'=>'Unable to move', 'err'=>$rc['err']));
			}
			$strsql = "DELETE FROM ciniki_media_details "
				. "WHERE media_id = '" . ciniki_core_dbQuote($ciniki, $old_parent_id) . "' "
				. "";
			$rc = ciniki_core_dbDelete($ciniki, $strsql, 'ciniki.media');
			if( $rc['stat'] != 'ok' ) { 	
				ciniki_core_dbTransactionRollback($ciniki, 'ciniki.media');
				return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'356', 'msg'=>'Unable to move', 'err'=>$rc['err']));
			}
		}
	}

	$rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.media');
	if( $rc['stat'] != 'ok' ) { 	
		return $rc;
	}

	//
	// Update the last_change date in the business modules
	// Ignore the result, as we don't want to stop user updates if this fails.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
	ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'media');

	return array('stat'=>'ok', 'id'=>$album_id);
}
?>
