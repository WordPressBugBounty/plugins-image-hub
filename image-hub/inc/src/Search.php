<?php

namespace ImageHub;

use ImageHub\Settings;
use ImageHub\Utils;

/**
 * REST proxy for image searches, so the site's API keys never reach the browser.
 */
class Search
{

    public function __construct()
    {
        add_action('rest_api_init', array($this, 'image_hub_register_routes'));
    }

    public function image_hub_register_routes()
    {
        register_rest_route('image-hub/v1', '/search', array(
            'methods'             => \WP_REST_Server::READABLE,
            'callback'            => array($this, 'image_hub_search'),
            'permission_callback' => array($this, 'image_hub_can_search'),
        ));
    }

    public function image_hub_can_search()
    {
        return current_user_can('upload_files');
    }

    /**
     * Forwards the search to the image service, adding the API key headers.
     */
    public function image_hub_search(\WP_REST_Request $request)
    {
        $params = array();
        foreach ($request->get_query_params() as $key => $value) {
            // Skip REST internals like _locale and rest_route.
            if (!is_scalar($value) || strpos($key, '_') === 0 || $key === 'rest_route') {
                continue;
            }
            $params[sanitize_key($key)] = sanitize_text_field($value);
        }

        $url = add_query_arg(rawurlencode_deep($params), trailingslashit(Utils::get_service_url()));

        $response = wp_remote_get($url, array(
            'timeout' => 30,
            'headers' => Settings::get_api_key_headers(),
        ));

        if (is_wp_error($response)) {
            return new \WP_Error('image_hub_service_error', $response->get_error_message(), array('status' => 502));
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        if (!is_array($data)) {
            return new \WP_Error('image_hub_service_error', 'Invalid response from the image service.', array('status' => 502));
        }

        return new \WP_REST_Response($data, wp_remote_retrieve_response_code($response));
    }
}
