<?php
// Enqueue Tailwind CSS
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('tailwindcss', get_template_directory_uri() . '/dist/output.css', [], null);
});
