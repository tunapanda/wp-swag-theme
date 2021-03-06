<?php

namespace App;

use Roots\Sage\Assets\JsonManifest;
use Roots\Sage\Container;
use Roots\Sage\Template\Blade;
use Roots\Sage\Template\BladeProvider;

/**
 * Theme assets
 */
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('sage/main.css', asset_path('styles/main.css'), false, null);
    wp_enqueue_script('sage/main.js', asset_path('scripts/main.js'), ['jquery'], null, true);
}, 100);

/**
 * Theme setup
 */
add_action('after_setup_theme', function () {
    /**
     * Enable features from Soil when plugin is activated
     * @link https://roots.io/plugins/soil/
     */
    add_theme_support('soil-clean-up');
    add_theme_support('soil-jquery-cdn');
    add_theme_support('soil-nav-walker');
    add_theme_support('soil-nice-search');
    add_theme_support('soil-relative-urls');

    /**
     * Enable plugins to manage the document title
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#title-tag
     */
    add_theme_support('title-tag');

    /**
     * Register navigation menus
     * @link https://developer.wordpress.org/reference/functions/register_nav_menus/
     */
    register_nav_menus([
        'primary_navigation' => __('Primary Navigation', 'sage'),
    ]);

    /**
     * Enable post thumbnails
     * @link https://developer.wordpress.org/themes/functionality/featured-images-post-thumbnails/
     */
    add_theme_support('post-thumbnails');

    /**
     * Enable HTML5 markup support
     * @link https://developer.wordpress.org/reference/functions/add_theme_support/#html5
     */
    add_theme_support('html5', ['caption', 'comment-form', 'comment-list', 'gallery', 'search-form']);

    /**
     * Enable selective refresh for widgets in customizer
     * @link https://developer.wordpress.org/themes/advanced-topics/customizer-api/#theme-support-in-sidebars
     */
    add_theme_support('customize-selective-refresh-widgets');

    /**
     * Use main stylesheet for visual editor
     * @see resources/assets/styles/layouts/_tinymce.scss
     */
    add_editor_style(asset_path('styles/main.css'));
}, 20);

/**
 * Register sidebars
 */
add_action('widgets_init', function () {
    $config = [
        'before_widget' => '<section class="widget %1$s %2$s">',
        'after_widget' => '</section>',
        'before_title' => '<h3>',
        'after_title' => '</h3>',
    ];
    register_sidebar([
        'name' => __('Primary', 'sage'),
        'id' => 'sidebar-primary',
    ] + $config);
    register_sidebar([
        'name' => __('Footer', 'sage'),
        'id' => 'sidebar-footer',
    ] + $config);
});

/**
 * Updates the `$post` variable on each iteration of the loop.
 * Note: updated value is only available for subsequently loaded views, such as partials
 */
add_action('the_post', function ($post) {
    sage('blade')->share('post', $post);
});

/**
 * Setup Sage options
 */
add_action('after_setup_theme', function () {
    /**
     * Add JsonManifest to Sage container
     */
    sage()->singleton('sage.assets', function () {
        return new JsonManifest(config('assets.manifest'), config('assets.uri'));
    });

    /**
     * Add Blade to Sage container
     */
    sage()->singleton('sage.blade', function (Container $app) {
        $cachePath = config('view.compiled');
        if (!file_exists($cachePath)) {
            wp_mkdir_p($cachePath);
        }
        (new BladeProvider($app))->register();
        return new Blade($app['view']);
    });

    /**
     * Create @asset() Blade directive
     */
    sage('blade')->compiler()->directive('asset', function ($asset) {
        return "<?= " . __NAMESPACE__ . "\\asset_path({$asset}); ?>";
    });
});

add_action('init', function () {
    add_rewrite_rule('^swagtrack\/[0-9a-z\-]+\/([0-9a-z\-]+)\/?$', 'index.php?post_type=swagpath&name=$matches[1]', 'top');
    add_rewrite_rule('^swagtrack\/[0-9a-z\-]+\/([0-9a-z\-]+)\/([0-9a-z\-]+)\/?$', 'index.php?post_type=swagpath&name=$matches[1]&swagifact=$matches[2]', 'top');

    add_rewrite_tag('%badge_slug%', '([[0-9a-z\-]+)', 'badge=');
    add_rewrite_tag('%issuee_slug%', '([[0-9a-z\-]+)', 'issuee=');

    add_permastruct('badge_assertion', '/author/%issuee_slug%/badge/%badge_slug%', false);
});

add_action('rest_api_init', function() {
    register_rest_route('swag/v1', '/badge/assertion/(?P<issuee>[[0-9a-z\-]+)/badge/(?P<badge>[[0-9a-z\-]+)', array(
        'methods' => \WP_REST_Server::READABLE,
        'callback' => function ($args) {
            global $post;

            query_posts(array(
                "name" => $args['badge'],
                "post_type" => "badge",
            ));

            the_post();

            $issuee = get_user_by('slug', $args['issuee']);

            $controller = new \App\Controllers\SingleBadgeForUser();

            $salt = bin2hex(random_bytes(10));

            $settings = get_option('open_badges_issuer');

            $images = rwmb_meta('badge_image', array("size" => "large"));

            $image_url = sizeof($images) > 0 ? $images[0] : $settings['default_badge_image'];

            $json = array(
                "@context" => "https://w3id.org/openbadges/v2",
                "description" => $post->post_content,
                "type" => "Assertion",
                "id" => get_author_posts_url($issuee->ID) . 'badge/' . $post->post_name,
                "badge" => get_permalink($post),
                "image" => $image_url,
                "verification" => array(
                    "type" => "HostedBadge",
                ),
                "issuedOn" => $controller->assertion_statement($args['issuee'])['timestamp'],
                "recipient" => array(
                    "type" => "email",
                    "hashed" => true,
                    "salt" => $salt,
                    "identity" => "sha256$" . hash('sha256', $issuee->user_email + $salt),
                ),
            );
            return $json;
        }
    ));
});