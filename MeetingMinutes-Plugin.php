<?php
/*
Plugin Name: Cofnodion
Plugin URI: http://www.cambrianweb.com
Description: Plugin that allows small councils/committees to publish their meeting agendas & minutes online.
Version: 0.1.0
Text Domain: m-minutes-plugin

Author: Gwe Cambrian Web
Author URI: http://www.cambrianweb.com
License: GPL v2
*/

/*
Copyright 2014 Emlyn Jones (email: emlyn@cambrianweb.com)

This program is free software; you can distribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
*/

add_action('init', 'mminutes_init');

function mminutes_init() {
	//Register a new custom post type
	$labels = array(
		'name' => __('Meetings', 'm-minutes-plugin'),
		'singular_name' => __('Meeting', 'm-minutes-plugin'),
		'add_new' => __('Add New', 'm-minutes-plugin'),
		'add_new_item' => __('Add New Meeting', 'm-minutes-plugin'),
		'edit_item' => __('Edit Meeting', 'm-minutes-plugin'),
		'new_item' => __('New Meeting', 'm-minutes-plugin'),
		'all_items' => __('All Meetings', 'm-minutes-plugin'),
		'view_item' => __('View Meetings', 'm-minutes-plugin'),
		'search_items' => __('Search Meetings', 'm-minutes-plugin'),
		'not_found' => __('No meetings found...', 'm-minutes-plugin'),
		'not_found_in_trash' => __('No meetings found in trash', 'm-minutes-plugin'),
		'menu_name' => __('Meetings', 'm-minutes-plugin')
	);
	$args = array(
		'labels' => $labels,
		'public' => true,
		'publicly_queryable' => true,
		'show_ui' => true,
		'show_in_menu' => true,
		'query_var' => true,
		'rewrite' => true,
		'capability_type' => 'post',
		'has_archive' => true,
		'hierarchical' => true,
		'menu_positions' => true,
		'supports' => array ('title')
	);

	register_post_type('meetings', $args);

	//Action hook to call on function to create meta boxes

	//Echo code to enable file uploads in the form
		function mminutes_update_edit_form(){
			echo 'enctype = "multipart/form-data"';
		};

		add_action('post_edit_form_tag','mminutes_update_edit_form');

	//Register metaboxes for Minutes information
	function mminutes_call_meta_boxes($post){
		add_meta_box('mminutes_meta', 
		__('Meeting Information', 'm-minutes-plugin'),
		'mminutes_display_meta_box',
		'meetings',
		'normal',
		'high');
	};

	add_action('add_meta_boxes', 'mminutes_call_meta_boxes', 10, 2);

	//Display the form to enter the metadata
	function mminutes_display_meta_box(){

		//Create nonce field...
		wp_nonce_field('m_minutes_save_meta_box', 'm-minutes-nonce');

		//Get any meta data that may be there already

		global $post;
		$meeting_title = get_the_title($post->ID);

		$meeting_get_date_raw = get_post_meta($post->ID, 'mminutes-meeting-date', true);
		//Check to see if date exists already...
		if (!empty($meeting_get_date_raw)){
			$meeting_date = date('Y-m-d', strtotime($meeting_get_date_raw));
		}
		else{
			
			$meeting_date = date('Y-m-d');#get today's date.
		}
		
		$meeting_summ = get_post_meta($post->ID, 'mminutes-meeting-summ', true);
		$meeting_agenda = get_post_meta($post->ID, 'mminutes-agenda', true);
		$meeting_minutes = get_post_meta($post->ID, 'mminutes-minutes', true);

		//Echo the HTML code to show form
		echo '<p>Date of meeting (dd/mm/yyyy):</p><p><input type = "date" name = "mminutes-meeting-date" value ="'.esc_attr($meeting_date).'"></input></p><p><em>*This is required</em></p>';


		if($meeting_agenda){
			echo '<p><a href = "'.esc_attr($meeting_agenda).'"target = "_blank">Preview Agenda</a></p>';

			//DELETE BUTTON STUFF HERE

		}

		elseif(empty($meeting_agenda)){
			echo '<p>Meeting Agenda (pdf): <input type = "file" name = "mminutes-agenda">'.'</input></p>';
			echo '<p>Sorry, no Agenda is uploaded yet.</p>';
		
		}
		
		if($meeting_minutes){
			echo '<p><a href = "'.esc_attr($meeting_minutes).'"target = "_blank">Preview Minutes</a></p>';
			//echo ' Delete Button to go here.';
		}
		
		elseif(empty($meeting_minutes)){
			echo '<p>Meeting Minutes (pdf): <input type = "file" name = "mminutes-minutes"></input></p>';
			echo '<p>Sorry, no Minutes have been uploaded yet.</p>';
		}
		
		echo '<p>Meeting Summary:</p>';
		echo '<p><textarea name = "mminutes-meeting-summary" style = "width: 100%; height: 150px;">'.esc_attr($meeting_summ).'</textarea>';
	};

	//Save the metadata in the table
	add_action('save_post', 'mminutes_save_meta_box');

	function mminutes_save_meta_box($post_id){
		if (get_post_type($post_id) == 'meetings' && isset($_POST['mminutes-meeting-date']) ){

			//Skip saving the data if it's Auto Saving
			if(defined('DOING_AUTOSAVE') && 'DOING_AUTOSAVE')
			return;

			//check nonce is set, and verifies it only starts save procedure if it's set, and is correct. Also - only proceeds if required fields are set. SECURITY.
			if(isset($_POST['m-minutes-nonce']) && wp_verify_nonce($_POST['m-minutes-nonce'], 'm_minutes_save_meta_box') && check_admin_referer('m_minutes_save_meta_box', 'm-minutes-nonce')){
				//CHECKS GO HERE AS AN IF STATEMENT


				//Update the post meta

				$meeting_date_raw = trim($_POST['mminutes-meeting-date']);
				$meeting_date_format = date("d-m-Y", strtotime($meeting_date_raw));


				$meetingdate_san = sanitize_text_field($meeting_date_format);
				$meetingsumm_san = sanitize_text_field($_POST['mminutes-meeting-summary']);

				update_post_meta($post_id, 'mminutes-meeting-date', $meetingdate_san );
				update_post_meta($post_id, 'mminutes-meeting-summ', $meetingsumm_san );

				//File upload - minutes and agenda


				// Setup the array of supported file types. In this case, it's just PDF. We can add more if required (e.g. .doc/.png/.jpg etc.) 
				$supported_types = array('application/pdf');  

				// Get the file type of the upload  
				$arr_file_type_ag = wp_check_filetype(basename($_FILES['mminutes-agenda']['name']));

				$arr_file_type_min = wp_check_filetype(basename($_FILES['mminutes-minutes']['name']));

				$uploaded_type_agenda = $arr_file_type_ag['type'];
				$uploaded_type_minutes = $arr_file_type_min['type'];					

				// Check if the type for the AGENDA is supported. If not, throw an error.
				
				if(in_array($uploaded_type_agenda, $supported_types)) {

					// Use the WordPress API to upload the file  
					$upload_ag = wp_upload_bits($_FILES['mminutes-agenda']['name'], null, file_get_contents($_FILES['mminutes-agenda']['tmp_name']));

					$upload_url_agenda = $upload_ag['url'];

					update_post_meta($post_id, 'mminutes-agenda', $upload_url_agenda);       

				}

				
				else{
					if(empty($_FILES['mminutes-agenda']['name'])){
						//do nothing if the thing is empty - these aren't required
					}
					
					elseif(!in_array($uploaded_type_agenda, $supported_types)) {
					
					//But if it isn't a pdf throw an error.
					
					$args = array(
							'back_link' => 'true',
							);
					wp_die("The file type that you've uploaded for the agenda is not a PDF. Click 'Go Back' To return to the previous screen.", null, $args);  
					}
					
					
				}

				
				// Check if the type for the MINUTES is supported. If not, throw an error.
				if(in_array($uploaded_type_minutes, $supported_types)) {  

					// Use the WordPress API to upload the file  
					$upload_min = wp_upload_bits($_FILES['mminutes-minutes']['name'], null, file_get_contents($_FILES['mminutes-minutes']['tmp_name']));  
					
					$upload_url_minutes = $upload_min['url'];
					//$upload_error_minutes = $upload_min['error'];

					update_post_meta($post_id, 'mminutes-minutes', $upload_url_minutes);
						
					//echo $upload_error_minutes;
				}

				

				else {
					if(empty($_FILES['mminutes-minutes']['name'])){
						//do nothing if the thing is empty - these aren't required
					}
					
					elseif(!in_array($uploaded_type_minutes, $supported_types)) {
					
					//But if it isn't a pdf throw an error.
					
					$args = array(
							'back_link' => 'true',
							);
					wp_die("The file type that you've uploaded for the minutes is not a PDF. Click 'Go Back' To return to the previous screen.", null, $args);  
					} 
				}



			}
		}	

		return;
	};




	//Set up the shortcodes to print off Meeting informations

	function mminutes_print_all_meetings($post){
		
		$args = array(
			'post_type' => 'meetings'
			);
		$the_query = new WP_Query( $args);
		
		ob_start();
		// The Loop
		if ( $the_query->have_posts() ) {
			
			ob_start();
				
			while ( $the_query->have_posts() ) {
				
				
				$the_query->the_post();
				
				
				$meeting_date = get_post_meta(get_the_ID(), 'mminutes-meeting-date', true);
				$meeting_summ = get_post_meta(get_the_ID(), 'mminutes-meeting-summ', true);
				$meeting_agenda = get_post_meta(get_the_ID(), 'mminutes-agenda', true);
				$meeting_minutes = get_post_meta(get_the_ID(), 'mminutes-minutes', true);
				
				 
				echo  '<h3>' . get_the_title() . '</h3><p><em>'.$meeting_summ.'</em></p>';
				echo 'Meeting held on: '.$meeting_date;
				
				if ($meeting_agenda){
					echo '<a href = "'.$meeting_agenda.'"target = "_blank"><p>Please click here to download the agenda for this meeting</p></a>';
				}
				else {
					echo '<p>Apologies, there is no agenda available for this meeting</p>';
				}
				
				if ($meeting_minutes){
					echo '<a href = "'.$meeting_minutes.'" target = "_blank"><p>Please click here to download the minutes for this meeting</p></a>';
				}
				
				else{
					echo '<p>Apologies, there are no minutes available for this meeting</p>';
				}
				
				
			
			}
			
		}
		else {
			// no posts found
			echo'<p>No meetings have been submitted yet. Please call back soon.</p>';
		}
		
		/* Restore original Post Data */
		wp_reset_postdata();
		
		//return $output_html;
		$output_html = ob_get_clean();
		ob_end_clean();
		return $output_html;
		
	}
	
	add_shortcode('show-meetings', 'mminutes_print_all_meetings');
	
}
?>