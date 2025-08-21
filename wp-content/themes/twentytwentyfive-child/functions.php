<?php

add_action( 'wp_enqueue_scripts', 'twentytwentyfive_styles' );

function twentytwentyfive_styles() {
	wp_enqueue_style( 
		'twentytwentyfive-style', 
		get_stylesheet_uri()
	);
}