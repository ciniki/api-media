<?php
//
// Description
// -----------
// This function returns the list of details about an album,
// including the information from media and ciniki_media_details.
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
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'), 
		'parent_id'=>array('required'=>'no', 'default'=>'0', 'blank'=>'yes', 'name'=>'Parent'),
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
	$rc = ciniki_media_checkAccess($ciniki, $args['business_id'], 'ciniki.media.getAlbumInfo', array()); 
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}   

	return array('stat'=>'ok', 'info'=>array());
}
?>
