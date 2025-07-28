<?php
/**
 * MCP Server API Class
 *
 * @package Mcp-Server-For-Wordpress
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * MCP_Server_API Class
 */
class MCP_Server_API {

    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'rest_api_init', [ $this, 'register_routes' ] );
        add_action( 'wp_head', [ $this, 'add_discovery_link' ] );
    }

    /**
     * Register REST API routes
     */
    public function register_routes() {
        $namespace = 'mcp/v1';
        
        // Inquiry Schema Endpoint
        register_rest_route( $namespace, '/inquiry/schema', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_inquiry_schema' ],
            'permission_callback' => [ $this, 'permission_check' ],
        ] );

        // Inquiry Submission Endpoint
        register_rest_route( $namespace, '/inquiry', [
            'methods'             => 'POST',
            'callback'            => [ $this, 'handle_inquiry' ],
            'permission_callback' => [ $this, 'permission_check' ],
        ] );

        // Report List Endpoint
        register_rest_route( $namespace, '/reports', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_reports_list' ],
            'permission_callback' => [ $this, 'permission_check' ],
        ] );
        
        // Single Report Endpoint
        register_rest_route( $namespace, '/reports/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [ $this, 'handle_report_request' ],
            'permission_callback' => [ $this, 'permission_check' ],
        ] );
    }

    /**
     * Permission check for API requests
     *
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function permission_check( $request ) {
        $options      = get_option( 'mcp_server_settings' );
        $secret_token = $options['mcp_server_secret_token'] ?? '';
        if ( empty( $secret_token ) ) {
            return new WP_Error( 'mcp_config_error', 'Secret token is not configured.', [ 'status' => 500 ] );
        }

        $client_signature = $request->get_header( 'x-mcp-signature' );
        $client_timestamp = $request->get_header( 'x-mcp-timestamp' );
        if ( ! $client_signature || ! $client_timestamp ) {
            return new WP_Error( 'mcp_auth_error', 'Missing signature or timestamp.', [ 'status' => 401 ] );
        }
        if ( abs( time() - (int) $client_timestamp ) > 300 ) {
            return new WP_Error( 'mcp_auth_error', 'Timestamp expired.', [ 'status' => 401 ] );
        }

        $body               = $request->get_body();
        $string_to_sign     = $client_timestamp . '.' . $body;
        $expected_signature = hash_hmac( 'sha256', $string_to_sign, $secret_token );

        if ( ! hash_equals( $expected_signature, $client_signature ) ) {
            return new WP_Error( 'mcp_auth_error', 'Invalid signature.', [ 'status' => 403 ] );
        }
        return true;
    }

    /**
     * Add discovery link to head
     */
    public function add_discovery_link() {
        echo '<link rel="mcp-protocol" href="' . esc_url( home_url( '/wp-json/mcp/v1/' ) ) . '" />' . "\n";
    }

    /**
     * Handle inquiry schema request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_inquiry_schema( $request ) {
        if ( ! function_exists( 'Ninja_Forms' ) ) {
            return new WP_Error( 'plugin_error', 'Ninja Forms is not active.', [ 'status' => 501 ] );
        }

        $params  = $request->get_json_params();
        $form_id = $params['form_id'] ?? 0;

        if ( empty( $form_id ) ) {
            $options = get_option( 'mcp_server_settings' );
            $form_id = $options['mcp_ninja_form_id'] ?? 0;
        }

        if ( empty( $form_id ) ) {
            return new WP_Error( 'mcp_config_error', 'Ninja Forms ID is not specified or configured.', [ 'status' => 400 ] );
        }

        $fields = Ninja_Forms()->form( (int) $form_id )->get_fields();
        $schema = [];
        foreach ( $fields as $field ) {
            $settings   = $field->get_settings();
            $schema[] = [
                'key'         => $settings['key'],
                'label'       => $settings['label'],
                'type'        => $settings['type'],
                'required'    => (bool) ( $settings['required'] ?? false ),
                'description' => $settings['desc_text'] ?? '',
            ];
        }
        return new WP_REST_Response( $schema, 200 );
    }

    /**
     * Handle inquiry request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_inquiry( $request ) {
        if ( ! function_exists( 'Ninja_Forms' ) ) {
            return new WP_Error( 'plugin_error', 'Ninja Forms is not active.', [ 'status' => 501 ] );
        }
        $options  = get_option( 'mcp_server_settings' );
        $form_id  = $options['mcp_ninja_form_id'] ?? 0;
        $mappings = $options['mcp_ninja_field_mappings'] ?? [];
        if ( empty( $form_id ) ) {
            return new WP_Error( 'mcp_config_error', 'Ninja Forms ID is not configured.', [ 'status' => 500 ] );
        }

        $params = $request->get_json_params();
        if ( ! $params ) {
            return new WP_Error( 'validation_error', 'Invalid JSON data.', [ 'status' => 400 ] );
        }

        $submission     = Ninja_Forms()->form( $form_id )->sub()->get();
        $all_field_keys = array_map(
            function( $field ) {
                return $field->get_setting( 'key' );
            },
            Ninja_Forms()->form( $form_id )->get_fields()
        );

        foreach ( $all_field_keys as $nf_key ) {
            if ( isset( $params[ $nf_key ] ) ) {
                $submission->update_field_value( $nf_key, sanitize_text_field( $params[ $nf_key ] ) );
            }
        }

        foreach ( $mappings as $map ) {
            $ai_key = $map['ai_key'];
            $nf_key = $map['nf_key'];
            if ( ! empty( $ai_key ) && ! empty( $nf_key ) && isset( $params[ $ai_key ] ) ) {
                $submission->update_field_value( $nf_key, sanitize_text_field( $params[ $ai_key ] ) );
            }
        }

        $submission->save();
        return new WP_REST_Response( [ 'status' => 'success', 'message' => 'Inquiry submitted successfully.' ], 200 );
    }

    /**
     * Handle reports list request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_reports_list( $request ) {
        $options = get_option( 'mcp_server_settings' );
        $taxonomy = $options['mcp_report_taxonomy'] ?? '';
        $term_id = $options['mcp_report_term'] ?? 0;

        if ( empty( $taxonomy ) || empty( $term_id ) ) {
            return new WP_Error( 'mcp_config_error', 'Report listing is not configured.', [ 'status' => 500 ] );
        }

        $args = [
            'post_type'      => 'post',
            'posts_per_page' => -1,
            'tax_query'      => [
                [
                    'taxonomy' => $taxonomy,
                    'field'    => 'term_id',
                    'terms'    => (int) $term_id,
                ],
            ],
        ];

        $query = new WP_Query( $args );
        $reports = [];

        if ( $query->have_posts() ) {
            while ( $query->have_posts() ) {
                $query->the_post();
                if ( has_block( 'core/file', get_the_content() ) ) {
                    $reports[] = [
                        'id'           => get_the_ID(),
                        'title'        => get_the_title(),
                        'permalink'    => get_permalink(),
                        'modified_date' => get_the_modified_date( 'c' ),
                    ];
                }
            }
        }
        wp_reset_postdata();

        return new WP_REST_Response( $reports, 200 );
    }

    /**
     * Handle single report request
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handle_report_request( $request ) {
        $post_id = (int) $request['id'];
        $post    = get_post( $post_id );
        if ( ! $post || 'publish' !== $post->post_status ) {
            return new WP_Error( 'not_found', 'Report not found.', [ 'status' => 404 ] );
        }

        $blocks = parse_blocks( $post->post_content );
        $files  = [];
        foreach ( $blocks as $block ) {
            if ( 'core/file' === $block['blockName'] && ! empty( $block['attrs']['id'] ) ) {
                $file_id = (int) $block['attrs']['id'];
                $file_url  = wp_get_attachment_url( $file_id );
                if ( $file_url ) {
                    $files[] = [
                        'id'        => $file_id,
                        'fileName'  => get_the_title( $file_id ),
                        'url'       => $file_url,
                        'mime_type' => get_post_mime_type( $file_id ),
                    ];
                }
            }
        }
        if ( empty( $files ) ) {
            return new WP_Error( 'no_files_found', 'No files found in this report.', [ 'status' => 404 ] );
        }
        return new WP_REST_Response( $files, 200 );
    }
}
