<?php
//
// Description
// -----------
// This function will flag the image as deleted, but not changing
// any of the image information.  Only when the image/album is
// emptied from the trash will it be removed from the database.
//
// Info
// ----
// Status: defined
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
function ciniki_media_deleteMedia($ciniki) {
	//
	// Check args
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/prepareArgs.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuote.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbUpdate.php');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'media_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No media specified'), 
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];

    //  
	// Make sure this module is activated, and 
	// check session user permission to run this function for this business
	//  
	require_once($ciniki['config']['core']['modules_dir'] . '/media/private/checkAccess.php');
	$rc = ciniki_media_checkAccess($ciniki, $args['business_id'], 'ciniki.media.deleteMedia', array((int)$args['media_id'])); 
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}

	//
	// Update the flags on the media to indicate deleted
	//
	$strsql = "UPDATE media SET flags = flags | 0x01 "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
		. "AND id = '" . ciniki_core_dbQuote($ciniki, $args['media_id']) . "' ";
	$rc = ciniki_core_dbUpdate($ciniki, $strsql, 'media');
	if( $rc['stat'] != 'ok' ) { 
		return array('stat'=>'fail', 'err'=>array('code'=>'338', 'msg'=>'Unable to delete media', 'err'=>$rc['err']));
	}

	return array('stat'=>'ok');
}
?>
