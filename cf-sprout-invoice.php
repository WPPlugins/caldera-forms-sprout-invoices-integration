<?php
/**
 * Plugin Name: Caldera Forms - Sprout Invoices Integration
 * Plugin URI:  
 * Description: Sprout Invoices Form Processor
 * Version:     1.0.0
 * Author:      David Cramer
 * Author URI:  https://profiles.wordpress.org/desertsnowman
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */


// Register Processor Filter
add_filter('caldera_forms_get_form_processors', 'cf_si_register_processor');

// Meta Viewer Filter for viewing meta data for ana entry
add_filter('caldera_forms_get_entry_meta_sprout_invoice', 'cf_si_meta_data', 10, 2);

// Capture invoice ID in entry meta
add_action('si_cloned_post', 'cf_si_add_invoice_meta', 10, 4);

/**
 * Function runs on cloning to create an invoice then adds the Invoice ID to the entries metablock
 * This will allow the Invoice history to show up in the original entry modal.
 */
function cf_si_add_invoice_meta($new_post_id, $post_id, $new_post_type, $new_post_status){
	if($new_post_type === 'sa_invoice'){
		
		global $wpdb;
		// I dont have a method to get entry by meta. I should add one.
		$entry_meta_data = $wpdb->get_row($wpdb->prepare("SELECT `meta`.*, `entry`.* FROM `" . $wpdb->prefix ."cf_form_entry_meta` AS `meta` LEFT JOIN `" . $wpdb->prefix ."cf_form_entries` AS `entry` ON (`meta`.`entry_id` = `entry`.`id`) WHERE `meta_key` = 'estimate_id' AND `meta_value` = %d", $post_id), ARRAY_A);
		if(!empty($entry_meta_data)){
			$new_meta = array(
				'entry_id'		=>	$entry_meta_data['entry_id'],
				'process_id'	=>	$entry_meta_data['process_id'],
				'meta_key'		=>	'invoice_id',
				'meta_value'	=>	$new_post_id
			);
			$wpdb->insert($wpdb->prefix ."cf_form_entry_meta", $new_meta);

		}

	}
}

/**
 * Addes the processor to the forms prosessors registry
 */
function cf_si_register_processor($processors){

	// Estimate creation
	$processors['sprout_invoice'] = array(
		"name"				=>	__('Sprout Invoices', 'cf-sprout'),
		"description"		=>	__("Create an estimate from a form submission.", 'cf-sprout'),
		"icon"				=>	plugin_dir_url(__FILE__) . "img/sproutapps.png",
		"author"			=>	"David Cramer",
		"author_url"		=>	"https://profiles.wordpress.org/desertsnowman",
		"processor"			=>	'cf_si_process_form_estimate',
		"template"			=>	plugin_dir_path(__FILE__) . "config.php",
		"single"			=>	true,
		"meta_template"		=>	plugin_dir_path(__FILE__) . "meta.php",
		"styles"			=>	array(
			plugin_dir_url(__FILE__) . 'css/meta_style.css'
		),
		"magic_tags" => array(
			"estimate_id"
		)
	);

	// Just a Client Creation
	$processors['sprout_invoice_client'] = array(
		"name"				=>	__('Sprout Client', 'cf-sprout'),
		"description"		=>	__("Create a Sprout Invoces Client from a form submission.", 'cf-sprout'),
		"icon"				=>	plugin_dir_url(__FILE__) . "img/sproutapps.png",
		"author"			=>	"David Cramer",
		"author_url"		=>	"https://profiles.wordpress.org/desertsnowman",
		"processor"			=>	'cf_si_process_form_client',
		"template"			=>	plugin_dir_path(__FILE__) . "config-client.php",
		"single"			=>	true
	);

	return $processors;

}

/**
 * Function runs on form process to create an estimate.
 * This will create the Estimate and Client and Return the Estimate ID which is then a magic tag.
 */
function cf_si_process_form_estimate($config, $form){
	global $transdata;
	
	foreach ($config as $key => &$value) {
		$value = Caldera_Forms::do_magic_tags($value);
	}
	// Set Values
	$subject 				= $config['subject'];
	$requirements 			= $config['requirements'];
	$email 					= $config['email'];
	$client_name 			= $config['client_name'];
	$full_name 				= $config['first_name'] . ' ' . $config['first_name'];
	$website 				= $config['website'];
	$contact_street 		= $config['street_address'];
	$contact_city 			= $config['city'];
	$contact_zone 			= $config['state'];
	$contact_postal_code 	= $config['postal_code'];
	$contact_country 		= $config['country'];
	
	// build arguments for estimate
	$estimate_args = array(
		'subject' => $subject,
		'requirements' => $requirements,
		'fields' => array(),
		'history_link' => Caldera_Forms::do_magic_tags( $config['history_text'] ),
	);
	
	// Maybe create the estimate?
	$estimate = @cf_si_maybe_create_estimate( $estimate_args, $config['processor_id'] );
	
	if ( is_a( $estimate, 'SI_Estimate' ) ) {
		// build the client arguments if it is an estimate
		$client_args = array(
			'email' => $email,
			'client_name' => $client_name,
			'full_name' => $full_name,
			'website' => $website,
			'contact_street' => $contact_street,
			'contact_city' => $contact_city,
			'contact_zone' => $contact_zone,
			'contact_postal_code' => $contact_postal_code,
			'contact_country' => $contact_country
		);
		// maybe create a client?
		$client = cf_si_maybe_create_client( $estimate, $client_args );

		// return meta data for entry and magic tag values for the rest of the process
		return array('estimate_id' => $transdata['estimate'][$config['processor_id']]);
	}

}

/**
 * Function runs on form process to create an estimate.
 * This will create the Estimate and Client and Return the Estimate ID which is then a magic tag.
 */
function cf_si_process_form_client($config, $form){
	global $transdata;
	
	foreach ($config as $key => &$value) {
		$value = Caldera_Forms::do_magic_tags($value);
	}
	// Set Values
	$email 					= $config['email'];
	$client_name 			= $config['client_name'];
	$full_name 				= $config['first_name'] . ' ' . $config['first_name'];
	$website 				= $config['website'];
	$contact_street 		= $config['street_address'];
	$contact_city 			= $config['city'];
	$contact_zone 			= $config['state'];
	$contact_postal_code 	= $config['postal_code'];
	$contact_country 		= $config['country'];
	
	// build the client arguments
	$client_args = array(
		'email' => $email,
		'client_name' => $client_name,
		'full_name' => $full_name,
		'website' => $website,
		'contact_street' => $contact_street,
		'contact_city' => $contact_city,
		'contact_zone' => $contact_zone,
		'contact_postal_code' => $contact_postal_code,
		'contact_country' => $contact_country
	);
	// maybe create a client?
	cf_si_maybe_create_client( null, $client_args );

}

/**
 * Function attempts to create the estimate and return the estimate object
 */
function cf_si_maybe_create_estimate( $args = array(), $process_id ) {
	global $transdata;

	$defaults = array(
		'subject' => sprintf( SI_Estimate_Submissions::__('New Estimate: %s'), date( get_option( 'date_format' ).' @ '.get_option( 'time_format' ), current_time( 'timestamp' ) ) ),
		'requirements' => SI_Estimate_Submissions::__('No requirements submitted. Check to make sure the "requirements" field is required.'),
		'fields' => $_REQUEST,
	);
	$parsed_args = wp_parse_args( $args, $defaults );

	// Create estimate
	$estimate_id = SI_Estimate::create_estimate( $parsed_args );
	$estimate = SI_Estimate::get_instance( $estimate_id );

	// capture ID and add it to the process transient
	$transdata['estimate'][$process_id] = $estimate_id;

	// End, don't use estimate_submitted since a notification will be fired.
	do_action( 'estimate_submitted_from_adv_form', $estimate, $parsed_args );

	// History
	do_action( 'si_new_record', 
		sprintf( si__('%s.'), $parsed_args['history_link'] ), 
		SI_Estimate_Submissions::SUBMISSION_UPDATE, 
		$estimate_id, 
		sprintf( si__('%s.'), $parsed_args['history_link'] ),
		0, 
		FALSE );

	return $estimate;
}


function cf_si_maybe_create_client( SI_Estimate $estimate = null, $args = array() ) {
	$client_id = ( isset( $args['client_id'] ) && get_post_type( $args['client_id'] ) == SI_Client::POST_TYPE ) ? $args['client_id'] : 0;
	$user_id = get_current_user_id();

	// check to see if the user exists by email
	if ( isset( $args['email'] ) && $args['email'] != '' ) {
		if ( $user = get_user_by('email', $args['email'] ) ) {
			$user_id = $user->ID;
		}
	}

	// Check to see if the user is assigned to a client already
	if ( !$client_id ) {
		$client_ids = SI_Client::get_clients_by_user( $user_id );
		if ( !empty( $client_ids ) ) {
			$client_id = array_pop( $client_ids );
		}
	}
	
	// Create a user for the submission if an email is provided.
	if ( !$user_id ) {
		// email is critical
		if ( isset( $args['email'] ) && $args['email'] != '' ) {
			$user_args = array(
				'user_login' => SI_Estimate_Submissions::esc__($args['email']),
				'display_name' => isset( $args['client_name'] ) ? SI_Estimate_Submissions::esc__($args['client_name']) : SI_Estimate_Submissions::esc__($args['email']),
				'user_pass' => wp_generate_password(), // random password
				'user_email' => isset( $args['email'] ) ? SI_Estimate_Submissions::esc__($args['email']) : '',
				'first_name' => si_split_full_name( SI_Estimate_Submissions::esc__($args['full_name']), 'first' ),
				'last_name' => si_split_full_name( SI_Estimate_Submissions::esc__($args['full_name']), 'last' ),
				'user_url' => isset( $args['website'] ) ? SI_Estimate_Submissions::esc__($args['website']) : ''
			);
			$user_id = SI_Clients::create_user( $user_args );
		}
		
	}

	// create the client based on what's submitted.
	if ( !$client_id ) {
		$address = array(
			'street' => isset( $args['contact_street'] ) ?SI_Estimate_Submissions::esc__( $args['contact_street']) : '',
			'city' => isset( $args['contact_city'] ) ? SI_Estimate_Submissions::esc__($args['contact_city']) : '',
			'zone' => isset( $args['contact_zone'] ) ? SI_Estimate_Submissions::esc__($args['contact_zone']) : '',
			'postal_code' => isset( $args['contact_postal_code'] ) ? SI_Estimate_Submissions::esc__($args['contact_postal_code']) : '',
			'country' => isset( $args['contact_country'] ) ? SI_Estimate_Submissions::esc__($args['contact_country']) : '',
		);

		$args = array(
			'company_name' => isset( $args['client_name'] ) ? SI_Estimate_Submissions::esc__($args['client_name']) : '',
			'website' => isset( $args['website'] ) ? SI_Estimate_Submissions::esc__($args['website']) : '',
			'address' => $address,
			'user_id' => $user_id
		);

		$client_id = SI_Client::new_client( $args );
		// History
		if( null !== $estimate ){
			do_action( 'si_new_record', 
				sprintf( 'Client Created & Assigned: %s', get_the_title( $client_id ) ), 
				SI_Estimate_Submissions::SUBMISSION_UPDATE, 
				$estimate->get_id(),
				sprintf( 'Client Created & Assigned: %s', get_the_title( $client_id ) ), 
				0, 
				FALSE );
		}
	}

	// Set the estimates client
	if( null !== $estimate ){
		$estimate->set_client_id( $client_id );
	}
}


/**
 * Function formats the meta data for viewing in the item modal
 * This is just a copy of the history portion of the template.
 */
function cf_si_meta_data($meta, $form){
	$estimate = get_post( $meta['meta_value'] );
	if( empty( $estimate ) ){
		$meta['error'] = __('Estimate invalid or has been deleted');
		return $meta;
	}
	if($meta['meta_key'] == 'estimate_id'){
		$meta['title'] = __('Estimate', 'cf-sprout');
	}else if($meta['meta_key'] == 'invoice_id'){
		$meta['title'] = __('Invoice', 'cf-sprout');
	}

	$meta['view_link'] = get_permalink( $estimate->ID );
	$meta['edit_link'] = 'post.php?post=' . (int) $estimate->ID .'&action=edit';
	
	ob_start();
	?>
	<div id="doc_history">
		<?php foreach ( si_doc_history_records( $meta['meta_value'] ) as $item_id => $data ): ?>
			<dt>
				<span class="history_status <?php echo $data['status_type'] ?>"><?php echo $data['type']; ?></span><br/>
				<span class="history_date"><?php echo date( get_option( 'date_format' ).' @ '.get_option( 'time_format' ), strtotime( $data['post_date'] ) ) ?></span>
			</dt>

			<dd>
				<?php if ( $data['status_type'] == SI_Notifications::RECORD ): ?>
					<p>
						<?php echo $update_title ?>
						<br/><a href="#TB_inline?width=600&height=380&inlineId=notification_message_<?php echo $item_id ?>" id="show_notification_tb_link_<?php echo $item_id ?>" class="thickbox tooltip notification_message" title="<?php si_e('View Message') ?>"><?php si_e('View Message') ?></a>
					</p>
					<div id="notification_message_<?php echo $item_id ?>" class="cloak">
						<?php echo apply_filters( 'the_content', $data['content'] ) ?>
					</div>
				<?php elseif ( $data['status_type'] == SI_Invoices::VIEWED_STATUS_UPDATE ) : ?>
					<p>
						<?php echo $data['update_title'] ?>
					</p>
				<?php else: ?>
					<?php echo apply_filters( 'the_content', $data['content'] ) ?>
				<?php endif ?>
				
			</dd>
		<?php endforeach ?>
	</div><!-- #doc_history -->
	<?php
	$meta['html'] = ob_get_clean();

return $meta;
}