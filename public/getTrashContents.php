<?php
//
// Description
// -----------
// Returns the list of images and albums which have been marked for
// deletion.  These images/albums could be from any parent_id, or
// just the specified parent.
//
// Info
// ----
// Status: defined
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:
// parent_id:			*optional* If not specified, then assumed to be 0, or default album.
//
// Returns
// -------
// <contents>
//	<image id="" title="" caption="" />
//	<album id="" image_id="" title="" caption="" />
// </contents>
//
function ciniki_media_getTrashContents($ciniki) {
	//
	// Check args
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'parent_id'=>array('required'=>'no', 'blank'=>'yes', 'errmsg'=>'Invalid parent_id'),
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
	$rc = ciniki_media_checkAccess($ciniki, $args['business_id'], 'ciniki.media.getTrashContents', array()); 
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}   

	//
	// Get the content for the album
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'datetimeFormat');
	$date_format = ciniki_users_datetimeFormat($ciniki);
	$strsql = "SELECT id, parent_id, type, remote_id, sequence, perms, "
		. "DATE_FORMAT(date_added, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') as date_added, "
		. "DATE_FORMAT(last_updated, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') as last_updated "
		. "FROM ciniki_media "
		. "WHERE business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' ";
	if( isset($args['parent_id']) ) {
		$strsql .= "AND parent_id = '" . ciniki_core_dbQuote($ciniki, $args['parent_id']) . "' "
	}
	$strsql .= "AND (flags^0x01) = 0x01 "
		. "ORDER BY sequence "
		. "";

	//
	// The query should be executed here for speed and due to the complexity.  
	// Also, this module must not query directly the images module incase it
	// is not located on the same server.
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuery');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbFetchHashRow');
	$rc = ciniki_core_dbQuery($ciniki, $strsql, 'ciniki.media');
	if( $rc['stat'] != 'ok' ) {
		return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'299', 'msg'=>'Unable to find album content', 'err'=>$rc['err']));
	}
	$dh = $rc['handle'];

	$contents = array();
	$i = 0;
	$image_ids = array();
	$album_ids = array();
	$result = ciniki_core_dbFetchHashRow($ciniki, $dh);
	while( isset($result['row']) ) {
		$row = $result['row'];
		//
		// Check for nested albums
		//
		if( $row['type'] == 1 ) {
			$contents[$i] = array('content'=>array('type'=>'album', 'id'=>$row['id'], 'image_id'=>$row['remote_id']));
			array_push($album_ids, $row['id']);
		}

		//
		// Check if the content is an image from the images module
		//
		elseif( $row['type'] == 128 ) {
			$contents[$i] = array('content'=>array('type'=>'image', 'id'=>$row['id'], 'image_id'=>$row['remote_id']));
			array_push($image_ids, $row['remote_id']);
		} 

		$result = ciniki_core_dbFetchHashRow($ciniki, $dh);
		$i++;
	}

	//
	// Get the images
	//
	if( count($image_ids) > 0 ) {
		ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'getImagesFromArray');
		$rc = ciniki_images_getImagesFromArray($ciniki, $args['business_id'], $image_ids);
		if( $rc['stat'] != 'ok' ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'288', 'msg'=>'Error getting image information', 'err'=>$rc['err']));
		}
		$images = $rc['images'];
	} else {
		$images = array();
	}
	
	//
	// Get the album details
	//
	if( count($album_ids) > 0 ) {
		ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashIDQuery');
		$strsql = "SELECT media_id, detail_key, detail_value FROM ciniki_media_details "
			. "WHERE media_id IN (" . ciniki_core_dbQuoteIDs($ciniki, $album_ids) . ") AND detail_key = 'title' ";
		$rc = ciniki_core_dbHashIDQuery($ciniki, $strsql, 'ciniki.media', 'albums', 'media_id');
		if( $rc['stat'] != 'ok' ) {
			return array('stat'=>'fail', 'err'=>array('pkg'=>'ciniki', 'code'=>'289', 'msg'=>'Error getting album information', 'err'=>$rc['err']));
		}
		$albums = $rc['albums'];
	} else {
		$albums = array();
	}

	//
	// Go through the contents, and fill in the addition information about images and albums (titles, captions)
	//
	foreach($contents as $i => $content) {
		if( $content['content']['type'] == 'album' ) {
			$contents[$i]['content']['title'] = $albums[$content['content']['id']]['detail_value'];
		}
		elseif( $content['content']['type'] == 'image' ) {
			$contents[$i]['content']['title'] = $images[$content['content']['image_id']]['title'];
			$contents[$i]['content']['caption'] = $images[$content['content']['image_id']]['caption'];
		}
	}

	return array('stat'=>'ok', 'contents'=>$contents);
}
?>
