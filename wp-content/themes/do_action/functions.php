<?php

function storefront_post_header() {
	do_action_functions()->storefront_post_header();
}

load_theme_textdomain( 'do-action' );

/** STRING TRANSLATIONS **/

add_filter( 'theme_mod_sph_hero_text', 'doaction_sph_hero_text', 10, 1 );
function doaction_sph_hero_text ( $text ) {
	$text = __( 'do_action hackathons are community-organised events that are focussed on using WordPress to give deserving charitable organisations their own online presence. Each do_action event includes participants from the local WordPress community coming together to plan and build brand new websites for a number of local organisations in one day.', 'do-action' );
	return $text;
}

add_filter( 'theme_mod_sph_hero_heading_text', 'doaction_sph_hero_heading_text', 10, 1 );
function doaction_sph_hero_heading_text ( $text ) {
	$text = __( 'do_action is a charity hackathon that uses WordPress to uplift local communities', 'do-action' );
	return $text;
}

add_filter( 'storefront_copyright_text', 'doaction_storefront_copyright_text', 21, 1 );
function doaction_storefront_copyright_text ( $text ) {
	$text = __( 'do_action is a charity hackathon that uses WordPress to uplift local communities', 'do-action' );
	return $text;
}