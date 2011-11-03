<?php
//
// Description
// -----------
// This function will create a new album, using the information
// from the first piece of media based for album information.
//
// Info
// ----
// Status: 				defined
//
// Arguments
// ---------
// business_id:			The business the image is attached to.
// media_id:			The ID if the media to be marked as deleted.
//
// Returns
// -------
// <rsp stat="ok"/>
//
function ciniki_media_createAlbumFromMedia($ciniki) {
	//
	// Check args
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/prepareArgs.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuote.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbUpdate.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbInsert.php');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'parent_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'media'=>array('required'=>'yes', 'blank'=>'no', 'type'=>'idlist', 'errmsg'=>'No media specified'), 
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
	require_once($ciniki['config']['core']['modules_dir'] . '/media/private/checkAccess.php');
	$media_ids = $args['media'];
	if( $args['parent_id'] > 0 ) {
		array_push($media_ids, (int)$args['parent_id']);
	}
	$rc = ciniki_media_checkAccess($ciniki, $args['business_id'], 'ciniki.media.createAlbumFromMedia', $media_ids);
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}

	//
	// Check there is at least 1 media element to be included in the album
	//
	if( !isset($args['media'][0]) || $args['media'][0] < 1 ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'324', 'msg'=>'No media specified'));
	}
	$primary_media_id = $args['media'][0];

	//  
	// Start a database transaction
	//  
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionStart.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionRollback.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionCommit.php');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'media');
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}   

	//
	// Get the info from the media_id
	//
	$strsql = "SELECT type, remote_id, sequence, perms, flags FROM media "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND id = '" . ciniki_core_dbQuote($ciniki, $primary_media_id) . "' "
		. "";
	$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'media', 'info');
	if( $rc['stat'] != 'ok' ) { 	
		ciniki_core_dbTransactionRollback($ciniki, 'media');
		return $rc;
	}

	if( !isset($rc['info']) || !isset($rc['info']['type']) ) {
		ciniki_core_dbTransactionRollback($ciniki, 'media');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'312', 'msg'=>'No media specified'));
	}
	
	//
	// Setup the new album information, based on the first media id passed
	//
	$album_info = array(
		'remote_id'=>$rc['info']['remote_id'], 
		'title'=>'', 
		'sequence'=>$rc['info']['sequence'],
		'perms'=>$rc['info']['perms'],
		'flags'=>$rc['info']['flags'],
		);
	if( $rc['info']['type'] == 128 ) {
		//
		// Get the image title and set the album title
		//
		require_once($ciniki['config']['core']['modules_dir'] . '/images/private/getImageTitle.php');
		$rc = ciniki_images_getImageTitle($ciniki, $args['business_id'], $rc['info']['remote_id']);
		if( $rc['stat'] != 'ok' ) { 	
			ciniki_core_dbTransactionRollback($ciniki, 'media');
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'343', 'msg'=>'No media specified', 'err'=>$rc['err']));
		}
		$album_info['title'] = $rc['title'];
	} elseif( $rc['info']['type'] == 1 ) {
		$strsql = "SELECT detail_value FROM media_details "
			. "WHERE media_id = '" . ciniki_core_dbQuote($ciniki, $primary_media_id) . "' "
			. "AND detail_key = 'title'";
		$rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'media', 'details');
		if( $rc['stat'] != 'ok' ) { 	
			ciniki_core_dbTransactionRollback($ciniki, 'media');
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'310', 'msg'=>'No media specified', 'err'=>$rc['err']));
		}
		if( !isset($rc['details']) || !isset($rc['details']['detail_value']) ) {
			ciniki_core_dbTransactionRollback($ciniki, 'media');
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'302', 'msg'=>'No media specified', 'err'=>$rc['err']));
		}
		$album_info['title'] = $rc['details']['title'];
	} else {
		ciniki_core_dbTransactionRollback($ciniki, 'media');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'311', 'msg'=>'Unsupported media'));
	}

	//
	// Create the album
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/media/private/createAlbum.php');
	$rc = ciniki_media_createAlbum($ciniki, $args['business_id'], $args['parent_id'], $album_info);
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'media');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'293', 'msg'=>'Unable to create album', 'err'=>$rc['err']));
	}
	if( !isset($rc['id']) || $rc['id'] < 1 ) {
		ciniki_core_dbTransactionRollback($ciniki, 'media');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'294', 'msg'=>'Unable to create album'));
	}
	$parent_id = $rc['id'];

	//
	// Move the specified media into the new album
	//
	$strsql = "UPDATE media SET parent_id = '" . ciniki_core_dbQuote($ciniki, $parent_id) . "' "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND id IN (" . ciniki_core_dbQuoteIDs($ciniki, $args['media']) . ")";
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'media');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'media');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'292', 'msg'=>'Unable to create album', 'err'=>$rc['err']));
	}

	//
	// Update the last_updated field of the parent
	//
	if( $parent_id > 0 ) {
		$strsql = "UPDATE media SET last_updated = UTC_TIMESTAMP() "
			. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
			. "AND id = '" . ciniki_core_dbQuote($ciniki, $parent_id) . "' ";
		$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'media');
		if( $rc['stat'] != 'ok' ) {
			ciniki_core_dbTransactionRollback($ciniki, 'media');
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'291', 'msg'=>'Unable to create album', 'err'=>$rc['err']));
		}
	}

	$rc = ciniki_core_dbTransactionCommit($ciniki, 'media');
	if( $rc['stat'] != 'ok' ) { 	
		return $rc;
	}

	return array('stat'=>'ok', 'id'=>$parent_id);
}
?>
