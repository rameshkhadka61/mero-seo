<?php

namespace ESEO\Modules\SocialSEO;

class SocialSEO {

    public function init() {
        add_action( 'wp_head', [ $this, 'output_opengraph_tags' ], 10005 );
    }

    public function output_opengraph_tags() {
        $site_name = get_bloginfo( 'name' );
        $default_desc = get_bloginfo( 'description' );

        $title = '';
        $desc = '';
        $url = '';
        $image_url = '';
        $type = 'website';
        $audio_url = '';

        if ( is_singular() ) {
            $post_id = get_the_ID();
            $title = get_post_meta( $post_id, '_eseo_social_title', true ) ?: ( get_post_meta( $post_id, '_eseo_meta_title', true ) ?: get_the_title() );
            $desc = get_post_meta( $post_id, '_eseo_social_description', true ) ?: ( get_post_meta( $post_id, '_eseo_meta_description', true ) ?: wp_trim_words( get_post_field( 'post_content', $post_id ), 25, '...' ) );
            $url = get_permalink( $post_id );
            $image_url = get_post_meta( $post_id, '_eseo_social_image', true ) ?: get_the_post_thumbnail_url( $post_id, 'full' );

            if ( get_post_type( $post_id ) === 'radio_station' ) {
                $type = 'music.radio_station';
                $audio_url = get_post_meta( $post_id, 'streaming_url', true ) ?: get_post_meta( $post_id, '_stream_url', true );
            } else {
                $type = 'article';
            }
        } elseif ( is_tax() || is_category() || is_tag() ) {
            $term = get_queried_object();
            if ( $term && ! is_wp_error( $term ) ) {
                $title = $term->name . ' Radio Stations - ' . $site_name;
                $desc = $term->description ?: 'Listen to live ' . $term->name . ' internet radio stations and streams online for free.';
                $url = get_term_link( $term );
            }
        } elseif ( is_front_page() || is_home() ) {
            $title = $site_name . ' - Listen to Live Internet Radio Stations Online';
            $desc = $default_desc ?: 'Tune into thousands of live internet radio stations, FM streams, and online broadcasts from around the world.';
            $url = home_url( '/' );
        }

        if ( empty( $title ) ) {
            $title = wp_get_document_title();
        }
        if ( empty( $desc ) ) {
            $desc = $default_desc ?: 'Live Radio Streaming Platform';
        }
        if ( empty( $url ) ) {
            $url = home_url( add_query_arg( array(), $GLOBALS['wp']->request ) );
        }

        $title = strip_tags( html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) );
        $desc = strip_tags( html_entity_decode( $desc, ENT_QUOTES, 'UTF-8' ) );

        if ( empty( $image_url ) && function_exists( 'has_custom_logo' ) && has_custom_logo() ) {
            $logo_id = get_theme_mod( 'custom_logo' );
            $image_url = wp_get_attachment_image_url( $logo_id, 'full' );
        }

        echo "\n<!-- Enterprise Social Media Optimization (Mero SEO SMO) -->\n";
        echo '<meta property="og:title" content="' . esc_attr( $title ) . '" />' . "\n";
        echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" />' . "\n";
        echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
        echo '<meta property="og:type" content="' . esc_attr( $type ) . '" />' . "\n";
        echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '" />' . "\n";
        echo '<meta property="og:locale" content="' . esc_attr( get_locale() ) . '" />' . "\n";

        if ( ! empty( $audio_url ) ) {
            echo '<meta property="og:audio" content="' . esc_url( $audio_url ) . '" />' . "\n";
            echo '<meta property="og:audio:type" content="audio/mpeg" />' . "\n";
        }

        if ( ! empty( $image_url ) ) {
            if ( is_string( $image_url ) ) {
                $image_url = preg_replace( '/-\d+x\d+(?=\.[a-zA-Z]{3,4}($|\?))/i', '', $image_url );
            }

            $width  = '';
            $height = '';
            $mime   = 'image/jpeg';

            if ( function_exists( 'attachment_url_to_postid' ) ) {
                $attachment_id = attachment_url_to_postid( $image_url );
                if ( $attachment_id ) {
                    $img_meta = wp_get_attachment_image_src( $attachment_id, 'full' );
                    if ( $img_meta && is_array( $img_meta ) ) {
                        $width  = $img_meta[1];
                        $height = $img_meta[2];
                    }
                    $post_mime = get_post_mime_type( $attachment_id );
                    if ( $post_mime ) {
                        $mime = $post_mime;
                    }
                }
            }
            if ( empty( $mime ) ) {
                if ( preg_match( '/\.png($|\?)/i', $image_url ) ) {
                    $mime = 'image/png';
                } elseif ( preg_match( '/\.webp($|\?)/i', $image_url ) ) {
                    $mime = 'image/webp';
                } elseif ( preg_match( '/\.gif($|\?)/i', $image_url ) ) {
                    $mime = 'image/gif';
                }
            }

            echo '<meta property="og:image" content="' . esc_url( $image_url ) . '" />' . "\n";
            echo '<meta property="og:image:secure_url" content="' . esc_url( preg_replace('/^http:/i', 'https:', $image_url) ) . '" />' . "\n";
            echo '<meta property="og:image:type" content="' . esc_attr( $mime ) . '" />' . "\n";
            if ( ! empty( $width ) ) {
                echo '<meta property="og:image:width" content="' . esc_attr( $width ) . '" />' . "\n";
            }
            if ( ! empty( $height ) ) {
                echo '<meta property="og:image:height" content="' . esc_attr( $height ) . '" />' . "\n";
            }
            echo '<meta property="og:image:alt" content="' . esc_attr( $title ) . '" />' . "\n";
        }

        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
        echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" />' . "\n";
        echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" />' . "\n";
        if ( ! empty( $image_url ) ) {
            echo '<meta name="twitter:image" content="' . esc_url( $image_url ) . '" />' . "\n";
        }
        echo "<!-- /Mero SEO SMO -->\n\n";
    }
}
