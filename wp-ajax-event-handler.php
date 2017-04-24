<?php

require_once 'wp-ajax-post-handler.php';

/**
 * @author Santiago Ramirez
 * @version 1.0.0
 *
 * Filter event based posts using wp-admin/admin-ajax.php
 */

class WP_AJAX_Event_Handler extends WP_AJAX_Post_Handler {

    /**
     * @var string $_action
     * AJAX action to perform
     */
    protected $_action = 'ajax_event_handler';

    /**
     * Class constructor
     * Set accepted params.
     */
    function __construct() {

        // Do not delete
        parent::__construct();

        $this->_set_accepted_params(array(
            'event_after' => array(
                'default' => false,
                'validate_callback' => function( $param, $request ) {
                    if ( $param == false || is_numeric( $param ) ) {
                        return true;
                    }
                    return false;
                }
            ),
            'event_before' => array(
                'default' => false,
                'validate_callback' => function( $param, $request ) {
                    if ( $param == false || is_numeric( $param ) ) {
                        return true;
                    }
                    return false;
                }
            )
        ));

    }

    /**
     * Handle request.
     */
    public function handler() {

        // Do not delete
        parent::handler();

        if ( $this->_get_param( 'event_before' ) || $this->_get_param( 'event_after' ) ) {
            $meta_query = array(
                'relation' => 'AND',
            );
        }

        if ( $this->_get_param( 'event_after' ) ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => 'date',
                    'value' => $this->_get_param( 'event_after' ),
                    'type' => 'numeric',
                    'compare' => '>=',
                ),
                array(
                    'key' => 'end_date',
                    'value' => $this->_get_param( 'event_after' ),
                    'type' => 'numeric',
                    'compare' => '>=',
                ),
            );
        }

        if ( $this->_get_param( 'event_before' ) ) {
            $meta_query[] = array(
                'relation' => 'OR',
                array(
                    'key' => 'date',
                    'value' => $this->_get_param( 'event_before' ),
                    'type' => 'numeric',
                    'compare' => '<=',
                ),
            );
        }

        if ( isset( $meta_query ) ) {
            $this->_args['meta_query'][] = $meta_query;
            $this->_args['meta_key'] = 'date';
            $this->_args['orderby'] = 'meta_value';
        }
    }

}

// Create new instance
$ajax_event_handler = new WP_AJAX_Event_Handler();

// Apply action hooks
$ajax_event_handler->hooks();
