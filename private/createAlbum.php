<?php
//
// Description
// -----------
// This function will create a new piece of media, which is a parent or album.
//
// Info
// ----
// Status: 				defined
//
// Arguments
// ---------
// business_id:			The business the image is attached to.
// parent_id:			The new parent_id of the media
// album_info:			An array of values both required and optional to create an album.
//
// Returns
// -------
// <rsp stat="ok"/>
//
function ciniki_media_createAlbum($ciniki, $business_id, $parent_id, $album_info) {
	
	//
	// Create a new album but inserting a new media element which is an album
	//
	$strsql = "INSERT INTO ciniki_media (business_id, parent_id, type, remote_id, "
		. "sequence, perms, flags, date_added, last_updated) VALUES ("
		. "'" . ciniki_core_dbQuote($ciniki, $business_id) . "', "
		. "'" . ciniki_core_dbQuote($ciniki, $parent_id) . "', "
		. "'1', ";
	if( isset($album_info['remote_id']) ) {
		$strsql .= "'" . ciniki_core_dbQuote($ciniki, $album_info['remote_id']) . "', ";
	} else {
		$strsql .= "'0', ";
	}
	if( isset($album_info['sequence']) ) {
		$strsql .= "'" . ciniki_core_dbQuote($ciniki, $album_info['sequence']) . "', ";
	} else {
		$strsql .= "'1', ";
	}
	if( isset($album_info['perms']) ) {
		$strsql .= "'" . ciniki_core_dbQuote($ciniki, $album_info['perms']) . "', ";
	} else {
		$strsql .= "'0', ";
	}
	if( isset($album_info['flags']) ) {
		$strsql .= "'" . ciniki_core_dbQuote($ciniki, $album_info['flags']) . "', ";
	} else {
		$strsql .= "'0', ";
	}
	$strsql .= "UTC_TIMESTAMP(), UTC_TIMESTAMP())";

	$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.media');
	if( $rc['stat'] != 'ok' ) { 	
		return $rc;
	}
	if( !isset($rc['insert_id']) || $rc['insert_id'] < 1 ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'350', 'msg'=>'Unable to create album'));
	}
	$album_id = $rc['insert_id'];


	if( isset($album_info['title']) ) {
		$strsql = "INSERT INTO ciniki_media_details (media_id, detail_key, detail_value, date_added, last_updated"
			. ") VALUES ("
			. "'" . ciniki_core_dbQuote($ciniki, $album_id) . "', "
			. "'title', "
			. "'" . ciniki_core_dbQuote($ciniki, $album_info['title']) . "', "
			. "UTC_TIMESTAMP(), UTC_TIMESTAMP())";
		$rc = ciniki_core_dbInsert($ciniki, $strsql, 'ciniki.media');
		if( $rc['stat'] != 'ok' ) { 	
			return $rc;
		}
	}

	//
	// Update the last_change date in the business modules
	// Ignore the result, as we don't want to stop user updates if this fails.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'updateModuleChangeDate');
	ciniki_businesses_updateModuleChangeDate($ciniki, $business_id, 'ciniki', 'media');

	return array('stat'=>'ok', 'id'=>$album_id);
}
?>
