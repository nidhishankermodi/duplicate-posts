<?php

if ( !defined( 'ABSPATH' ) ) exit;
/*
	Plugin Name: Duplicate Posts
	Plugin URI:
	Description: This plugin provides a feature to duplicate wordpress post type such posts, pages & products.
	Version: 1.0
	Author: Nidhishanker Modi
	Author URI: https://www.linkedin.com/in/nidhishanker-modi-614a1246/
	Text Domain: nsmodi
	License: GPL2
	This WordPress plugin is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version. This WordPress plugin is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details. You should have received a copy of the GNU General Public License	along with this WordPress plugin. If not, see http://www.gnu.org/licenses/gpl-2.0.html.
*/

if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
} else {
	clearstatcache();
}

require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
!defined( 'NSMODI_PD_PLUGIN_DIR' ) && define( 'NSMODI_PD_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
!defined( 'NSMODI_PD_PLUGIN_URL' ) && define( 'NSMODI_PD_PLUGIN_URL', plugins_url( '/', __FILE__ ) );

/*
 * Function for create duplicate posts. The new duplicated post will appear as drafts. User is redirected to the edit screen
 */
function nsmodi_pd_duplicate_post_as_draft() {
	global $wpdb;
	if ( !( isset( $_GET[ 'post' ] ) || isset( $_POST[ 'post' ] ) || ( isset( $_REQUEST[ 'action' ] ) && 'nsmodi_pd_duplicate_post_as_draft' == $_REQUEST[ 'action' ] ) ) ) {
		wp_die( 'No post to duplicate has been supplied!' );
	}
	/*
	 * Nonce verification
	 */
	if ( !isset( $_GET[ 'duplicate_nonce' ] ) || !wp_verify_nonce( $_GET[ 'duplicate_nonce' ], basename( __FILE__ ) ) )
		return;
	/*
	 * get the original post id
	 */
	$post_id = ( isset( $_GET[ 'post' ] ) ? absint( $_GET[ 'post' ] ) : absint( $_POST[ 'post' ] ) );
	/*
	 * and all the original post data then
	 */
	$post = get_post( $post_id );
	/*
	 * if you don't want current user to be the new post author,
	 * then change next couple of lines to this: $new_post_author = $post->post_author;
	 */
	$current_user = wp_get_current_user();
	$new_post_author = $current_user->ID;
	/*
	 * if post data exists, create the post duplicate
	 */
	if ( isset( $post ) && $post != null ) {
		/*
		 * new post data array
		 */
		$args = array(
			'comment_status' => $post->comment_status,
			'ping_status' => $post->ping_status,
			'post_author' => $new_post_author,
			'post_content' => $post->post_content,
			'post_excerpt' => $post->post_excerpt,
			'post_name' => $post->post_name,
			'post_parent' => $post->post_parent,
			'post_password' => $post->post_password,
			'post_status' => 'draft',
			'post_title' => $post->post_title,
			'post_type' => $post->post_type,
			'to_ping' => $post->to_ping,
			'menu_order' => $post->menu_order
		);
		/*
		 * insert the post by wp_insert_post() function
		 */
		$new_post_id = wp_insert_post( $args );
		/*
		 * get all current post terms ad set them to the new post draft
		 */
		$taxonomies = get_object_taxonomies(
			$post->post_type ); // returns array of taxonomy names for post type, ex array("category", "post_tag");
		foreach ( $taxonomies as $taxonomy ) {
			$post_terms = wp_get_object_terms( $post_id, $taxonomy, array('fields' => 'slugs') );
			wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
		}
		/*
		 * duplicate all post meta just in two SQL queries
		 */
		$post_meta_infos = $wpdb->get_results(
			"SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id" );
		if ( count( $post_meta_infos ) != 0 ) {
			$sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
			foreach ( $post_meta_infos as $meta_info ) {
				$meta_key = $meta_info->meta_key;
				if ( $meta_key == '_wp_old_slug' ) continue;
				$meta_value = addslashes( $meta_info->meta_value );
				$sql_query_sel[] = "SELECT $new_post_id, '$meta_key', '$meta_value'";
			}
			$sql_query .= implode( " UNION ALL ", $sql_query_sel );
			$wpdb->query( $sql_query );
		}
		/*
		 * finally, redirect to the edit post screen for the new draft
		 */
		wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
		exit;
	} else {
		wp_die( 'Post creation failed, could not find original post: ' . $post_id );
	}
}

add_action( 'admin_action_nsmodi_pd_duplicate_post_as_draft', 'nsmodi_pd_duplicate_post_as_draft' );
/*
 * Add the duplicate link to action list for post_row_actions
 */
function nsmodi_pd_duplicate_post_link( $actions, $post ) {
	if ( current_user_can( 'edit_posts' ) ) {
		$actions[ 'duplicate' ] = '<a href="' . wp_nonce_url(
				'admin.php?action=nsmodi_pd_duplicate_post_as_draft&post=' . $post->ID, basename( __FILE__ ),
				'duplicate_nonce' ) . '" title="Duplicate this item" rel="permalink">Duplicate</a>';
	}
	return $actions;
}

add_filter( 'post_row_actions', 'nsmodi_pd_duplicate_post_link', 10, 2 );

//To enable cloning for pages as well, use the same code but replace the final line with:

add_filter( 'page_row_actions', 'nsmodi_pd_duplicate_post_link', 10, 2 );
