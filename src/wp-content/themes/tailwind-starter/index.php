<?php
// Silence is golden;
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body class="bg-black min-h-screen flex items-center justify-center">
    <div class="dots flex items-center justify-center">
        <span class="w-50 h-50 bg-white rounded-full"></span>
    </div>
    <?php wp_footer(); ?>
</body>
</html>
