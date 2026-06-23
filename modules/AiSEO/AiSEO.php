<?php

namespace ESEO\Modules\AiSEO;

class AiSEO {

    public function init() {
        add_action( 'wp_ajax_eseo_generate_meta', [ $this, 'ajax_generate_meta' ] );
    }

    public function ajax_generate_meta() {
        check_ajax_referer( 'eseo_ai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( 'Unauthorized' );
        }

        $type = sanitize_text_field( $_POST['type'] ); // 'title' or 'description'
        $keyword = sanitize_text_field( $_POST['keyword'] );
        $content = sanitize_textarea_field( $_POST['content'] ?? '' );
        $post_title = sanitize_text_field( $_POST['post_title'] ?? '' );
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
        
        $categories_text = '';
        if ( $post_id ) {
            $categories = get_the_category( $post_id );
            if ( ! empty( $categories ) && ! is_wp_error( $categories ) ) {
                $cat_names = wp_list_pluck( $categories, 'name' );
                $categories_text = implode( ', ', $cat_names );
            }
        }
        
        $openai_key = get_option( 'eseo_openai_key' );
        $gemini_key = get_option( 'eseo_gemini_key' );
        $preferred_engine = get_option( 'eseo_preferred_ai_engine', 'smart' );

        if ( empty( $openai_key ) && empty( $gemini_key ) ) {
            wp_send_json_error( [
                'code' => 'no_api_key',
                'message' => 'Please configure either your OpenAI or Gemini API Key in the Enterprise SEO Settings.'
            ] );
        }

        $context = "Title: {$post_title}\n";
        if ( ! empty( $categories_text ) ) {
            $context .= "Categories: {$categories_text}\n";
        }
        if ( ! empty( $content ) ) {
            $context .= "Content Snippet: {$content}\n";
        }

        $prompt = '';
        if ( $type === 'title' ) {
            $prompt = "You are an expert SEO copywriter. Analyze the following blog post context:\n\n{$context}\n\nTask: Generate a highly unique, engaging SEO Title. You MUST weave in the focus keyword '{$keyword}'. Draw heavily from the category themes and the content snippet to make it specific to this exact article. The title must be under 60 characters. Return only the title text, nothing else.";
        } elseif ( $type === 'description' ) {
            $prompt = "You are an expert SEO copywriter. Analyze the following blog post context:\n\n{$context}\n\nTask: Generate a highly unique, engaging SEO Meta Description. You MUST weave in the focus keyword '{$keyword}'. Draw heavily from the category themes and the content snippet to make it specific to this exact article. Keep it between 140 and 160 characters. Return only the description text, nothing else.";
        } elseif ( $type === 'keyword' ) {
            $prompt = "Act as an expert SEO researcher. Based on the following post context:\n\n{$context}\n\nSuggest the single most effective Focus Keyword. The keyword should ideally have high search volume intent and summarize the core topic. Return ONLY the keyword text (1 to 4 words maximum), nothing else.";
        }

        // Determine Engine based on user preference and key availability
        $engine = '';

        if ( $preferred_engine === 'openai' && ! empty( $openai_key ) ) {
            $engine = 'openai';
        } elseif ( $preferred_engine === 'gemini' && ! empty( $gemini_key ) ) {
            $engine = 'gemini';
        } elseif ( $preferred_engine === 'smart' && ! empty( $openai_key ) && ! empty( $gemini_key ) ) {
            // Both keys exist and smart routing is selected
            if ( $type === 'keyword' ) {
                $engine = 'gemini';
            } else {
                $engine = 'openai';
            }
        } elseif ( ! empty( $openai_key ) ) {
            // Fallback if the preferred key is missing
            $engine = 'openai';
        } else {
            $engine = 'gemini';
        }
        
        if ( $engine === 'openai' ) {
            $openai_model = get_option( 'eseo_openai_model', 'gpt-3.5-turbo' );
            $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $openai_key,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'model' => $openai_model,
                    'messages' => [
                        [ 'role' => 'user', 'content' => $prompt ]
                    ]
                ]),
                'timeout' => 15,
            ]);

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( 'OpenAI API Request Failed: ' . $response->get_error_message() );
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( isset( $data['choices'][0]['message']['content'] ) ) {
                $generated = trim( $data['choices'][0]['message']['content'], '"\'' );
                wp_send_json_success( $generated );
            } else {
                wp_send_json_error( 'Invalid OpenAI API Response: ' . $body );
            }
        } else {
            // Gemini API
            $gemini_model = get_option( 'eseo_ai_model_v2', 'gemini-2.0-flash' );
            $response = wp_remote_post( 'https://generativelanguage.googleapis.com/v1/models/' . $gemini_model . ':generateContent?key=' . $gemini_key, [
                'headers' => [
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode( [
                    'contents' => [
                        [
                            'parts' => [
                                [ 'text' => $prompt ]
                            ]
                        ]
                    ]
                ]),
                'timeout' => 15,
            ]);

            if ( is_wp_error( $response ) ) {
                wp_send_json_error( 'Gemini API Request Failed: ' . $response->get_error_message() );
            }

            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body, true );

            if ( isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
                $generated = trim( $data['candidates'][0]['content']['parts'][0]['text'], '"\'' );
                wp_send_json_success( $generated );
            } else {
                wp_send_json_error( 'Invalid Gemini API Response. ' . $body );
            }
        }
    }
}
