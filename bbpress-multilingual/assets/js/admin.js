// Fix for duplicate checkbox
jQuery(document).ready(function() {
	jQuery( "input:checkbox[name='icl_dupes[]']" ).prop( 'disabled', false );
});