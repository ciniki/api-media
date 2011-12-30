<?php
//
// Description
// -----------
// This function will return the array of rulesets available to the media module,
// and all the information for them.
//
// Info
// ----
// Status: alpha
//
// Arguments
// ---------
// 
// Returns
// -------
//
function ciniki_media_getRulesets($ciniki) {

	//
	// permission_groups rules are OR'd together with customers rules
	//
	// - customers - 'any', (any customers of the business)
	// - customers - 'self', (the session user_id must be the same as requested user_id)
	//
	// *note* A function can only be allowed to customers, if there is no permission_groups rule.
	//

	return array(
		//
		// The default for nothing selected is to have access restricted to nobody
		//
		''=>array('label'=>'Nobody',
			'description'=>'Nobody has access, no even owners.',
			'details'=>array(
				'owners'=>'no access.',
				'employees'=>'no access.',
				'customers'=>'no access.'
				),
			'default'=>array(),
			'methods'=>array()
			),

		//
		// For all methods, you must be in the group Bug Tracker.  Only need to specify
		// the default permissions, will automatically be applied to all methods.
		//
		'employees'=>array('label'=>'Employees', 
			'description'=>'This permission setting allows all owners and employees of the business to manage media',
			'details'=>array(
				'owners'=>'all tasks',
				'employees'=>'all tasks',
				'customers'=>'no access.'
				),
			'default'=>array('permission_groups'=>array('ciniki.owners', 'ciniki.employees', 'ciniki.media')),
			'methods'=>array()
			),

		//
		// For all methods, you must be in the group Bug Tracker.  Only need to specify
		// the default permissions, will automatically be applied to all methods.
		//
		'group_restricted'=>array('label'=>'Group Restricted', 
			'description'=>'This permission setting will only allow the business owner '
				. 'and any employees assigned to the Media group will be allowed, all other '
				. 'employees will be denied access.',
			'details'=>array(
				'owners'=>'all tasks on all media.',
				'employees'=>'all tasks on all media if assigned to group Media.',
				'customers'=>'no access.'
				),
			'default'=>array('permission_groups'=>array('ciniki.media')),
			'methods'=>array()
			),
	);
}
?>
