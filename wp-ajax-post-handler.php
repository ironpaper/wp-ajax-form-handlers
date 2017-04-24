<?php

/**
 * @author Santiago Ramirez
 * @version 1.0.0
 *
 * Filter posts using wp-admin/admin-ajax.php
 */

class WP_AJAX_Post_Handler {

    /**
     * @var string $_action
     * AJAX action to perform
     */
    protected $_action = 'ajax_post_handler';

    /**
     * @var array $_accepted_params
     * Accepted params
     */
    protected $_accepted_params = array();

    /**
     * @var array $_sanitized_params
     * Sanitized params
     */
    protected $_sanitized_params = array();

    /**
     * @var array $_sanitized_params
     * WP_Query args
     */
    protected $_args = array();

    /**
     * Class constructor
     * Set accepted params.
     */
    function __construct() {

        $this->_set_accepted_params( array(
            '_wpnonce' => array(
                'default' => false,
                'validate_callback' => function( $param ) {
                    if ( wp_verify_nonce( $param,  $_REQUEST['_action'] ) ) {
                        return true;
                    }
                    return false;
                }
            ),
            'format' => array(
                'default' => 'html',
                'validate_callback' => function( $param ) {
                    return in_array( $param, array( 'json', 'html' ) );
                }
            ),
            'order' => array(
                'default' => 'ASC',
                'sanitize_callback' => function( $param ) {
                    return strtoupper( trim( $param ) );
                },
                'validate_callback' => function( $param ) {
                    return in_array( $param, array( 'ASC', 'DESC' ) );
                }
            ),
            'orderby' => array(
                'default' => 'date',
                'validate_callback' => function( $param ) {
                    return in_array( $param, array( 'date' ) );
                }
            ),
            'paged' => array(
                'default' => 1,
                'validate_callback' => function( $param ) {
                    return is_numeric( $param );
                }
            ),
            'partial' => array(
                'default' => false
            ),
            'post_type' => array(
                'default' => array( 'post' ),
                'sanitize_callback' => function( $param ) {
                    return explode( ',', $param );
                }
            ),
            'showposts' => array(
                'default' => 10,
                'validate_callback' => function( $param ) {
                    return is_numeric( $param );
                }
            ),
        ) );

    }

    /**
     * Add action 'wp_ajax_nopriv_{$_action}' to make resource accessible.
     */
    public function hooks() {
        add_action( 'wp_ajax_nopriv_' . $this->_action, array( $this, 'wp_ajax_callback' ) );
        add_action( 'wp_ajax_' . $this->_action, array( $this, 'wp_ajax_callback' ) );
    }

    /**
     *  Callback for action 'wp_ajax_' and 'wp_ajax_nopriv'
     */
    function wp_ajax_callback() {
        $this->handler();
        $this->_response();
        wp_die();
    }

    /**
     * Set accepted param defaults, sanitize callback and validate callback.
     * @param array $accepted_params
     */
    protected function _set_accepted_params( $accepted_params ) {
        foreach ( $accepted_params as $k => $accepted_param ) {
            $this->_accepted_params[$k] = $accepted_param;
        }
    }

    /**
     * Get sanitized param.
     * @param string $keyd
     * @return boolen|mixed
     */
    protected function _get_param( $key ) {
        if ( isset( $this->_sanitized_params[$key] ) ) {
            return $this->_sanitized_params[$key];
        }
        return false;
    }

    /**
     * Sanitize params based on sanitize callback or use default if not set.
     */
    protected function _sanitize_request() {
        foreach ( $this->_accepted_params as $key => $accepted_param ) {
            if ( isset( $_REQUEST[$key] ) ) {
                if ( isset( $accepted_param['sanitize_callback'] ) ) {
                    $this->_sanitized_params[$key] = $accepted_param['sanitize_callback']( $_REQUEST[$key], $_REQUEST );
                } else {
                    $this->_sanitized_params[$key] = $_REQUEST[$key];
                }
            } else if ( isset( $accepted_param['default'] ) ) {
                $this->_sanitized_params[$key] = $accepted_param['default'];
            }
        }
    }

    /**
     * Validate params based on validate callback.
     */
    protected function _validate_request() {
        foreach ( $this->_sanitized_params as $key => $sanitized_param ) {
            if ( isset( $this->_accepted_params[$key]['validate_callback'] ) ) {
                if ( !$this->_accepted_params[$key]['validate_callback']( $sanitized_param ) ) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Handle request.
     */
    public function handler() {

        $this->_sanitize_request();

        if ( !$this->_validate_request() ) {
            wp_die();
        }

        $this->_args = array(
            'paged' => $this->_get_param( 'paged' ),
            'showposts' => $this->_get_param( 'showposts' ),
            'order' => $this->_get_param( 'order' ),
            'orderby' => $this->_get_param( 'orderby' ),
            'meta_query' => array(
                'relation' => 'AND',
            ),
        );

        if ( $this->_get_param( 'post_type' ) ) {
            $this->_args['post_type'] = $this->_get_param( 'post_type' );
        }

        $this->_args['tax_query'] = array();
        $taxonomies = get_taxonomies();

        foreach ( $taxonomies as $taxonomy ) {
            if ( isset( $_REQUEST['tax_' . $taxonomy] ) ) {
                $this->_args['tax_query'][] = array(
                    'taxonomy' => $taxonomy,
                    'field' => 'slug',
                    'terms' => explode( ',', $_REQUEST['tax_' . $taxonomy] ),
                );
            }
        }
        
    }

    /**
     * Execute query and send response.
     */
    protected function _response() {
        $this->_query = new WP_Query( $this->_args );
        $posts = $this->_query->posts;

        header( 'X-WP-Total: ' . $this->_query->post_count );
        header( 'X-WP-TotalPages: ' . $this->_query->max_num_pages );

        if ( $this->_get_param( 'format' ) === 'json' ) {
            $this->_response_json();
        } else {
            $this->_response_html();
        }
    }

    /**
     * Send response as JSON.
     * @param array $posts
     */
    protected function _response_json( $posts ) {
        header( 'Content-type: application/json' );
        echo json_encode( $posts );
    }

    /**
     * Send response as HTML.
     * @param array $posts
     */
    protected function _response_html( $posts ) {
        header( 'Content-type: text/html' );
        global $post;
        foreach ( $posts as $post ) {
            setup_postdata( $post );
            if ( $this->_get_param( 'partial' ) ) {
                get_template_part( 'partials/' . $this->_get_param( 'partial' ) );
            }
        }
        wp_reset_postdata( $post );
    }

}

// Create new instance
$ajax_post_handler = new WP_AJAX_Post_Handler();

// Apply action hooks
$ajax_post_handler->hooks();
