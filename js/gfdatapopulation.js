function gf_datapopulation( form_id ) {
	
	/* Parse the forms JSON object */
	var forms = jQuery.parseJSON( gf_datapopulation_forms );
	
	/* If this form isn't in the forms object, don't execute. */
	if ( forms[form_id] === undefined ) return false;
	
	/* Push the field values */
	for ( var field_name in forms[form_id]['fields'] )
		jQuery( '#'+ field_name ).val( forms[form_id]['fields'][field_name] );
	
}