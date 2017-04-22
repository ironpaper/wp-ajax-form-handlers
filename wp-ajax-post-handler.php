<?php

/**
 * @author Santiago Ramirez
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
            'order' => array(
                'default' => 'ASC',
                'sanitize_callback' => function( $param ) {
                    return strtoupper( trim( $param ) );
                },
                'validate_callback' => function( $param, $request ) {
                    return in_array( $param, array( 'ASC', 'DESC' ) );
                }
            ),
            'orderby' => array(
                'default' => 'date',
                'validate_callback' => function( $param, $request ) {
                    return in_array( $param, array( 'date' ) );
                }
            ),
            'page' => array(
                'default' => 1,
                'validate_callback' => function( $param, $request ) {
                    return is_numeric( $param );
                }
            ),
            'paged' => array(
                'default' => 1,
                'validate_callback' => function( $param, $request ) {
                    return is_numeric( $param );
                }
            ),
            'per_page' => array(
                'default' => 10,
                'validate_callback' => function( $param, $request ) {
                    return is_numeric( $param );
                }
            ),
            'type' => array(
                'default' => array( 'post' ),
                'sanitize_callback' => function( $param, $request ) {
                    return explode( ',', $param );
                }
            ),
            'template_part' => array(
                'default' => false
            ),
            'wpnonce' => array(
                'default' => false,
                'validate_callback' => function( $param, $request ) {
                    if ( isset( $request['action'] ) ) {
                        if ( wp_verify_nonce( $param,  $request['action'] ) ) {
                            return true;
                        } else {
                            return false;
                        }
                    }
                    return true;
                }
            ),
        ) );

    }

    /**
     * Add action 'wp_ajax_nopriv_{$_action}' to make resource accessible.
     */
    public function hooks() {
        add_action( 'wp_ajax_nopriv_' . $this->_action, function() {

            $this->handler_nopriv();
            $this->_execute();

            header( 'X-WP-Total: ' . $this->_query->post_count );
            header( 'X-WP-TotalPages: ' . $this->_query->max_num_pages );
            header( 'Content-type: text/html' );

            wp_die();
        });
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
                if ( !$this->_accepted_params[$key]['validate_callback']( $sanitized_param, $_REQUEST ) ) {
                    return false;
                }
            }
        }
        return true;
    }

    /**
     * Handler request for nopriv.
     */
    public function handler_nopriv() {

        $this->_sanitize_request();

        if ( !$this->_validate_request() ) {
            wp_die();
        }

        $this->_args = array(
            'paged' => $this->_get_param( 'page' ),
            'showposts' => $this->_get_param( 'per_page' ),
            'order' => $this->_get_param( 'order' ),
            'orderby' => $this->_get_param( 'orderby' ),
            'meta_query' => array(
                'relation' => 'AND',
            ),
        );

        if ( $this->_get_param( 'type' ) ) {
            $this->_args['post_type'] = $this->_get_param( 'type' );
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
     * Execute query.
     */
    protected function _execute() {
        $this->_query = new WP_Query( $this->_args );
        $posts = $this->_query->posts;

        global $post;
        foreach ( $posts as $post ) {
            setup_postdata( $post );
            if ( $this->_get_param( 'template_part' ) ) {
                get_template_part( $this->_get_param( 'template_part' ) );
            }
        }

        wp_reset_postdata( $post );
    }

}

// Create new instance
$ajax_post_handler = new WP_AJAX_Post_Handler();

// Apply action hooks
$ajax_post_handler->hooks();
