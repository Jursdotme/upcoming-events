<?php
/**
 * Plugin Name: Upcoming Events
 * Plugin URI: http://jurs.me
 * Description: A plugin to show a list of upcoming events on the front-end.
 * Version: 1.0
 * Author: Rasmus Jürs
 * Author URI: http://jurs.me
 * License: GPL2
 */


/*
  Use plugins_url() function to get our root directory path.
  It accepts two parameters i.e. $path and $plugin.

  Since we don't need to refer to any file, but to the root directory of our plugin, we didn’t provide the $path argument and for the $plugin argument, we provided the current file. We then simply appended the names of other directories to the ROOT constant for their respective paths.
*/

 define( 'ROOT', plugins_url( '', __FILE__ ) );
 define( 'IMAGES', ROOT . '/img/' );
 define( 'STYLES', ROOT . '/css/' );
 define( 'SCRIPTS', ROOT . '/js/' );


 // Define Events post type
 function uep_custom_post_type() {
    $labels = array(
        'name'                  =>   __( 'Events', 'uep' ),
        'singular_name'         =>   __( 'Event', 'uep' ),
        'add_new_item'          =>   __( 'Add New Event', 'uep' ),
        'all_items'             =>   __( 'All Events', 'uep' ),
        'edit_item'             =>   __( 'Edit Event', 'uep' ),
        'new_item'              =>   __( 'New Event', 'uep' ),
        'view_item'             =>   __( 'View Event', 'uep' ),
        'not_found'             =>   __( 'No Events Found', 'uep' ),
        'not_found_in_trash'    =>   __( 'No Events Found in Trash', 'uep' )
    );

    $supports = array(
        'title',
        'editor',
        'excerpt'
    );

    $args = array(
        'label'         =>   __( 'Events', 'uep' ),
        'labels'        =>   $labels,
        'description'   =>   __( 'A list of upcoming events', 'uep' ),
        'public'        =>   true,
        'show_in_menu'  =>   true,
        'menu_icon'     =>   'dashicons-calendar-alt',
        'has_archive'   =>   true,
        'rewrite'       =>   true,
        'supports'      =>   $supports
    );

    register_post_type( 'event', $args );
}
add_action( 'init', 'uep_custom_post_type' );

// Add Event info metabox
function uep_add_event_info_metabox() {
    add_meta_box(
        'uep-event-info-metabox',
        __( 'Event Info', 'uep' ),
        'uep_render_event_info_metabox',
        'event',
        'side',
        'core'
    );
}
add_action( 'add_meta_boxes', 'uep_add_event_info_metabox' );

// Add Event info metabox contents
function uep_render_event_info_metabox( $post ) {

    // generate a nonce field
    wp_nonce_field( basename( __FILE__ ), 'uep-event-info-nonce' );

    // get previously saved meta values (if any)
    $event_start_date = get_post_meta( $post->ID, 'event-start-date', true );
    $event_end_date = get_post_meta( $post->ID, 'event-end-date', true );
    $event_venue = get_post_meta( $post->ID, 'event-venue', true );

    // if there is previously saved value then retrieve it, else set it to the current time
    $event_start_date = ! empty( $event_start_date ) ? $event_start_date : time();

    // we assume that if the end date is not present, event ends on the same day
    $event_end_date = ! empty( $event_end_date ) ? $event_end_date : $event_start_date;

    ?>

    <label for="uep-event-start-date"><?php _e( 'Event Start Date:', 'uep' ); ?></label>
      <input class="widefat uep-event-date-input" id="uep-event-start-date" type="text" name="uep-event-start-date" placeholder="Format: February 18, 2014" value="<?php echo date( 'F d, Y', $event_start_date ); ?>" />

    <label for="uep-event-end-date"><?php _e( 'Event End Date:', 'uep' ); ?></label>
      <input class="widefat uep-event-date-input" id="uep-event-end-date" type="text" name="uep-event-end-date" placeholder="Format: February 18, 2014" value="<?php echo date( 'F d, Y', $event_end_date ); ?>" />

    <label for="uep-event-venue"><?php _e( 'Event Venue:', 'uep' ); ?></label>
      <input class="widefat" id="uep-event-venue" type="text" name="uep-event-venue" placeholder="eg. Times Square" value="<?php echo $event_venue; ?>" />

    <br>

<?php }


// Enqueue Styles and Scripts
function uep_admin_script_style( $hook ) {

  global $post_type;

  if ( ( 'post.php' == $hook || 'post-new.php' == $hook ) && ( 'event' == $post_type ) ) {
        wp_enqueue_script(
            'upcoming-events',
            SCRIPTS . 'scripts.js',
            array( 'jquery', 'jquery-ui-datepicker' ),
            '1.0',
            true
        );

        wp_enqueue_style(
            'jquery-ui-calendar',
            STYLES . 'jquery-ui.css',
            false,
            '1.10.4',
            'all'
        );

    }
}
add_action( 'admin_enqueue_scripts', 'uep_admin_script_style' );

function uep_widget_style() {
    if ( is_active_widget( '', '', 'uep_upcoming_events', true ) ) {
        wp_enqueue_style(
            'upcoming-events',
            STYLES . 'style.css',
            false,
            '1.0',
            'all'
        );
    }
}
add_action( 'wp_enqueue_scripts', 'uep_widget_style' );


// Save meta values
function uep_save_event_info( $post_id ) {

    global $_POST;

    // checking if the post being saved is an 'event',
    // if not, then return
    if ( isset($_POST['post_type']) && 'event' != $_POST['post_type'] ) {
			return;
		}

    // checking for the 'save' status
    $is_autosave = wp_is_post_autosave( $post_id );
    $is_revision = wp_is_post_revision( $post_id );
    $is_valid_nonce = ( isset( $_POST['uep-event-info-nonce'] ) && ( wp_verify_nonce( $_POST['uep-event-info-nonce'], basename( __FILE__ ) ) ) ) ? true : false;

    // exit depending on the save status or if the nonce is not valid
    if ( $is_autosave || $is_revision || ! $is_valid_nonce ) {
        return;
    }

    // checking for the values and performing necessary actions
    if ( isset( $_POST['uep-event-start-date'] ) ) {
        update_post_meta( $post_id, 'event-start-date', strtotime( $_POST['uep-event-start-date'] ) );
    }

    if ( isset( $_POST['uep-event-end-date'] ) ) {
        update_post_meta( $post_id, 'event-end-date', strtotime( $_POST['uep-event-end-date'] ) );
    }

    if ( isset( $_POST['uep-event-venue'] ) ) {
        update_post_meta( $post_id, 'event-venue', sanitize_text_field( $_POST['uep-event-venue'] ) );
    }
}
add_action( 'save_post', 'uep_save_event_info' );

// Add Event start and end Dates to Events admin Screen:
  // Add Column head
  function uep_custom_columns_head( $defaults ) {
      unset( $defaults['date'] );

      $defaults['event_start_date'] = __( 'Start Date', 'uep' );
      $defaults['event_end_date'] = __( 'End Date', 'uep' );
      $defaults['event_venue'] = __( 'Venue', 'uep' );

      return $defaults;
  }
  add_filter( 'manage_edit-event_columns', 'uep_custom_columns_head', 10 );

  // Add column content
  function uep_custom_columns_content( $column_name, $post_id ) {

      if ( 'event_start_date' == $column_name ) {
          $start_date = get_post_meta( $post_id, 'event-start-date', true );
          echo date( 'F d, Y', $start_date );
      }

      if ( 'event_end_date' == $column_name ) {
          $end_date = get_post_meta( $post_id, 'event-end-date', true );
          echo date( 'F d, Y', $end_date );
      }

      if ( 'event_venue' == $column_name ) {
          $venue = get_post_meta( $post_id, 'event-venue', true );
          echo $venue;
      }
  }
  add_action( 'manage_event_posts_custom_column', 'uep_custom_columns_content', 10, 2 );


// Include the widget
include( 'inc/widget-upcoming-events.php' );

// Flush rewrite rules on plugin activation. This makes custom post types that are set via a plugin work with pritty permalinks.
function uep_activation_callback() {
    uep_custom_post_type();
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'uep_activation_callback' );
