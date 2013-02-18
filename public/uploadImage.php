<?php
//
// Description
// -----------
// This function will add an image to approriate album, or
// main media section if no album is specified.
//
// Info
// ----
// Status: defined
//
// Arguments
// ---------
// user_id: 		The user making the request
// 
// Example Return
// --------------
// <rsp stat="ok" id="4" />
//
function ciniki_media_uploadImage($ciniki) {
	//
	// Check args
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'parent_id'=>array('required'=>'no', 'default'=>'0', 'blank'=>'yes', 'errmsg'=>'Invalid parent_id'),
		'sequence'=>array('required'=>'no', 'default'=>'1', 'blank'=>'yes', 'errmsg'=>'Invalid sequence number'),
		'force_duplicate'=>array('required'=>'no', 'default'=>'no', 'blank'=>'yes', 'errmsg'=>'Invalid force_duplicate argument.'),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];

    //  
	// Make sure this module is activated, and 
	// check permission to run this function for this business
	//  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'media', 'private', 'checkAccess');
	$rc = ciniki_media_checkAccess($ciniki, $args['business_id'], 'ciniki.media.uploadImage', array()); 
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}   

	//
	// Check to make sure a file was uploaded
	//
	if( isset($_FILES['uploadfile']['error']) && $_FILES['uploadfile']['error'] == UPLOAD_ERR_INI_SIZE ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'300', 'msg'=>'Upload failed, file too large.'));
	}
	// FIXME: Add other checkes for $_FILES['uploadfile']['error']

	if( !isset($_FILES) || !isset($_FILES['uploadfile']) || $_FILES['uploadfile']['tmp_name'] == '' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'301', 'msg'=>'Upload failed, no file specified.'));
	}
	$uploaded_file = $_FILES['uploadfile']['tmp_name'];

	//
	// Start transaction
	//
	require($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionStart.php');
	require($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionRollback.php');
	require($ciniki['config']['core']['modules_dir'] . '/core/private/dbTransactionCommit.php');
	$rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.media');
	if( $rc['stat'] != 'ok' ) { 
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'303', 'msg'=>'Internal Error', 'err'=>$rc['err']));
	}   

	//
	// The image type will be checked in the insertFromUpload method to ensure it is an image
	// in a format we accept
	//

	//
	// Insert image into the database
	// The name for the image is not being passed, it will be picked from the $_FILES['uploadfile']['name'] field automatically.
	//
	require($ciniki['config']['core']['modules_dir'] . '/images/private/insertFromUpload.php');
	$rc = ciniki_images_insertFromUpload($ciniki, $args['business_id'], $ciniki['session']['user']['id'], 
		$_FILES['uploadfile'], 1, '', '', $args['force_duplicate']);
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.media');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'304', 'msg'=>'Internal Error', 'err'=>$rc['err']));
	}

	$image_id = 0;
	if( !isset($rc['id']) ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.media');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'305', 'msg'=>'Invalid file type'));
	}
	$image_id = $rc['id'];

	//
	// Push any current media content entrys up the sequence to make room.
	//
	$strsql = "UPDATE ciniki_media SET sequence = sequence + 1 "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND parent_id = '" . ciniki_core_dbQuote($ciniki, $args['parent_id']) . "' "
		. "AND sequence >= '" . ciniki_core_dbQuote($ciniki, $args['sequence']) . "' ";
	require($ciniki['config']['core']['modules_dir'] . '/core/private/dbUpdate.php');
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'ciniki.media');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.media');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'328', 'msg'=>'Unable to insert into sequence'));
	}

	//
	// Insert the image into the media table
	//
	$strsql = "INSERT INTO ciniki_media (uuid, business_id, parent_id, type, remote_id, sequence, perms, date_added, last_updated"
		. ") VALUES ("
		. "UUID(), "
		. "'" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "', "
		. "'" . ciniki_core_dbQuote($ciniki, $args['parent_id']) . "', "
		. "128, '" . ciniki_core_dbQuote($ciniki, $image_id) . "', "
		. "'" . ciniki_core_dbQuote($ciniki, $args['sequence']) . "', "
		. "1, UTC_TIMESTAMP(), UTC_TIMESTAMP())";
	$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.media');
	if( $rc['stat'] != 'ok' ) {
		ciniki_core_dbTransactionRollback($ciniki, 'ciniki.media');
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'318', 'msg'=>'Unable to upload media', 'err'=>$rc['err']));
	}

	$rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.media');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'331', 'msg'=>'Unable to upload media', 'err'=>$rc['err']));
	}

	//
	// Update the last_change date in the business modules
	// Ignore the result, as we don't want to stop user updates if this fails.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
	ciniki_businesses_updateModuleChangeDate($ciniki, $args['business_id'], 'ciniki', 'media');

	return array('stat'=>'ok', 'id'=>$image_id);
}
?>
