<?php
//
// Description
// -----------
// This function returns the list of details about an album,
// including the information from media and media_details.
//
// Info
// ----
// Status: defined
//
// Arguments
// ---------
// api_key:
// auth_token:
//
// Returns
// -------
// <xml return>
//
//
function ciniki_media_getAlbumInfo($ciniki) {
	//
	// Check args
	//
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/prepareArgs.php');
	require_once($ciniki['config']['core']['modules_dir'] . '/core/private/dbQuote.php');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'parent_id'=>array('required'=>'no', 'default'=>'0', 'blank'=>'yes', 'errmsg'=>'Invalid parent_id'),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];

    //  
	// Make sure this module is activated, and 
	// check permission to run this function for this business
	//  
	require_once($ciniki['config']['core']['modules_dir'] . '/media/private/checkAccess.php');
	$rc = ciniki_media_checkAccess($ciniki, $args['business_id'], 'ciniki.media.getAlbumInfo', array()); 
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}   

	return array('stat'=>'ok', 'info'=>array());
}
?>
