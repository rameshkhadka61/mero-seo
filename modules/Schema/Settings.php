<?php

namespace ESEO\Modules\Schema;

class Settings {

    public function init() {
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_settings() {
        register_setting( 'eseo_schema_options', 'eseo_schema_settings' );
    }

    public function render_settings_page() {
        if ( ! function_exists( 'wp_enqueue_media' ) ) {
            require_once ABSPATH . 'wp-includes/media.php';
        }
        wp_enqueue_media();

        $settings = get_option( 'eseo_schema_settings', [] );
        $type = isset( $settings['entity_type'] ) ? $settings['entity_type'] : 'organization';
        $name = isset( $settings['entity_name'] ) ? $settings['entity_name'] : get_bloginfo('name');
        $logo_url = isset( $settings['logo_url'] ) ? $settings['logo_url'] : '';

        ?>
        <div class="wrap" style="font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Oxygen-Sans,Ubuntu,Cantarell,'Helvetica Neue',sans-serif;">
            <h1>Schema Graph Settings</h1>
            <p>Configure the core entity details for your site's Knowledge Graph.</p>
            
            <form method="post" action="options.php">
                <?php settings_fields( 'eseo_schema_options' ); ?>
                
                <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; margin-top:20px; max-width:800px;">
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label>Does your site represent an Organization or a Person?</label></th>
                            <td>
                                <select name="eseo_schema_settings[entity_type]" style="width:100%; max-width:300px;">
                                    <option value="organization" <?php selected($type, 'organization'); ?>>Organization</option>
                                    <option value="person" <?php selected($type, 'person'); ?>>Person</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Name of Organization/Person</label></th>
                            <td>
                                <input type="text" name="eseo_schema_settings[entity_name]" value="<?php echo esc_attr($name); ?>" class="regular-text" style="width:100%; max-width:400px;">
                                <p class="description">Google uses this name for the Knowledge Graph.</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label>Organization Logo URL</label></th>
                            <td>
                                <div style="display:flex; gap:10px;">
                                    <input type="text" id="eseo_logo_url" name="eseo_schema_settings[logo_url]" value="<?php echo esc_attr($logo_url); ?>" class="regular-text" style="width:100%; max-width:400px;">
                                    <button type="button" class="button" id="eseo_upload_logo_btn">Upload Image</button>
                                </div>
                                <?php if ( $logo_url ) : ?>
                                    <div style="margin-top:10px;">
                                        <img src="<?php echo esc_url($logo_url); ?>" style="max-height:100px; border:1px solid #ccc; padding:5px; background:#f8f9fa;">
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php submit_button( 'Save Schema Settings' ); ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var file_frame;
            $('#eseo_upload_logo_btn').on('click', function(e) {
                e.preventDefault();
                if (file_frame) {
                    file_frame.open();
                    return;
                }
                file_frame = wp.media.frames.file_frame = wp.media({
                    title: 'Select or Upload Logo',
                    button: { text: 'Use this image' },
                    multiple: false
                });
                file_frame.on('select', function() {
                    var attachment = file_frame.state().get('selection').first().toJSON();
                    $('#eseo_logo_url').val(attachment.url);
                });
                file_frame.open();
            });
        });
        </script>
        <?php
    }
}
