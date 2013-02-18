<?php
//
// Description
// -----------
// This function will return the image binary data in jpg format.
//
// Info
// ----
// Status: defined
//
// Arguments
// ---------
// image_id:			The ID if the image requested.
// version:				The version of the image (regular, thumbnail)
//
//						*note* the thumbnail is not referring to the size, but to a 
//						square cropped version, designed for use as a thumbnail.
//						This allows only a portion of the original image to be used
//						for thumbnails, as some images are too complex for thumbnails.
//
// maxlength:			The max length of the longest side should be.  This allows
//						for generation of thumbnail's, etc.
//
// Returns
// -------
// Binary image data
//
function ciniki_media_getImage($ciniki) {
	//
	// Check args
	//
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
	ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbQuote');
	$rc = ciniki_core_prepareArgs($ciniki, 'no', array(
		'business_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No business specified'), 
		'image_id'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No image specified'), 
		'version'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No version specified'),
		'maxlength'=>array('required'=>'yes', 'blank'=>'no', 'errmsg'=>'No size specified'),
		));
	if( $rc['stat'] != 'ok' ) {
		return $rc;
	}
	$args = $rc['args'];

    //  
	// Make sure this module is activated, and 
	// check session user permission to run this function for this business
	//  
	ciniki_core_loadMethod($ciniki, 'ciniki', 'media', 'private', 'checkAccess');
	$rc = ciniki_media_checkAccess($ciniki, $args['business_id'], 'ciniki.media.getImage', array()); 
	if( $rc['stat'] != 'ok' ) { 
		return $rc;
	}
	
	ciniki_core_loadMethod($ciniki, 'ciniki', 'images', 'private', 'getImage');
	return ciniki_images_getImage($ciniki, $args['business_id'], $args['image_id'], $args['version'], $args['maxlength']);
}
?>
