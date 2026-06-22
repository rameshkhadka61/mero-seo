<?php

namespace ESEO\Admin;

class Menu {

    public function init() {
        add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
    }

    public function register_admin_menu() {
        add_menu_page(
            'Mero Afno Premium SEO',
            'Mero Afno Premium SEO',
            'manage_options',
            'enterprise-seo',
            [ $this, 'render_dashboard' ],
            'dashicons-chart-line',
            80
        );

        add_submenu_page(
            'enterprise-seo',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'enterprise-seo',
            [ $this, 'render_dashboard' ]
        );

        add_submenu_page(
            'enterprise-seo',
            'AI Settings',
            'AI Settings',
            'manage_options',
            'eseo-ai-settings',
            [ $this, 'render_ai_settings' ]
        );

        add_submenu_page(
            'enterprise-seo',
            'Indexing API',
            'Indexing API',
            'manage_options',
            'eseo-indexing-settings',
            [ $this, 'render_indexing_settings' ]
        );

        $migration_module = new \ESEO\Modules\Tools\Migration();
        add_submenu_page(
            'enterprise-seo',
            'Tools & Migration',
            'Tools & Migration',
            'manage_options',
            'eseo-tools',
            [ $migration_module, 'render_tools_page' ]
        );
    }

    public function register_settings() {
        register_setting( 'eseo_ai_options', 'eseo_openai_key' );
        register_setting( 'eseo_ai_options', 'eseo_openai_model' );
        register_setting( 'eseo_ai_options', 'eseo_gemini_key' );
        register_setting( 'eseo_ai_options', 'eseo_gemini_model' );
        register_setting( 'eseo_ai_options', 'eseo_preferred_ai_engine' );
        register_setting( 'eseo_ai_options', 'eseo_modules_disabled', [
            'type' => 'array',
            'default' => []
        ]);

        register_setting( 'eseo_indexing_options', 'eseo_google_indexing_key' );
        register_setting( 'eseo_indexing_options', 'eseo_bing_api_key' );
    }

    public function render_dashboard() {
        global $wpdb;

        // Fetch dynamic stats safely
        $redirects_table = $wpdb->prefix . 'eseo_redirects';
        $links_table = $wpdb->prefix . 'eseo_links';

        $total_redirects = get_transient( 'eseo_dashboard_redirects_count' );
        if ( false === $total_redirects ) {
            $total_redirects = 0;
            if ( $wpdb->get_var("SHOW TABLES LIKE '$redirects_table'") === $redirects_table ) {
                $total_redirects = (int) $wpdb->get_var("SELECT SUM(hits) FROM $redirects_table");
            }
            set_transient( 'eseo_dashboard_redirects_count', $total_redirects, 12 * HOUR_IN_SECONDS );
        }

        $total_links = get_transient( 'eseo_dashboard_links_count' );
        if ( false === $total_links ) {
            $total_links = 0;
            if ( $wpdb->get_var("SHOW TABLES LIKE '$links_table'") === $links_table ) {
                $total_links = (int) $wpdb->get_var("SELECT COUNT(*) FROM $links_table");
            }
            set_transient( 'eseo_dashboard_links_count', $total_links, 12 * HOUR_IN_SECONDS );
        }

        // Calculate SEO Scores
        $seo_scores = get_transient( 'eseo_dashboard_scores' );
        if ( false === $seo_scores ) {
            $seo_scores = [ 'good' => 0, 'ok' => 0, 'needs_improvement' => 0, 'not_analyzed' => 0 ];
            $total_posts = (int) $wpdb->get_var( "SELECT COUNT(ID) FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type NOT IN ('revision', 'nav_menu_item', 'attachment')" );
            
            $meta_data = $wpdb->get_results( "
                SELECT post_id, meta_key 
                FROM {$wpdb->postmeta} 
                WHERE meta_key IN ('_eseo_meta_title', '_eseo_meta_description', '_eseo_focus_keyword') 
                AND meta_value != ''
            " );
            
            $post_scores = [];
            foreach ( $meta_data as $row ) {
                if ( ! isset( $post_scores[ $row->post_id ] ) ) {
                    $post_scores[ $row->post_id ] = 0;
                }
                $post_scores[ $row->post_id ]++;
            }

            foreach ( $post_scores as $post_id => $score ) {
                if ( $score == 3 ) $seo_scores['good']++;
                elseif ( $score == 2 ) $seo_scores['ok']++;
                else $seo_scores['needs_improvement']++;
            }

            $analyzed_count = count( $post_scores );
            $seo_scores['not_analyzed'] = max( 0, $total_posts - $analyzed_count );
            
            set_transient( 'eseo_dashboard_scores', $seo_scores, 12 * HOUR_IN_SECONDS );
        }

        ?>
        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <style>
            .eseo-wrap {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                margin: 20px 20px 0 0;
            }
            .eseo-header {
                margin-bottom: 25px;
            }
            .eseo-header h1 {
                font-size: 24px;
                font-weight: 600;
                color: #1d2327;
                margin: 0 0 5px 0;
            }
            .eseo-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
                margin-bottom: 20px;
            }
            .eseo-card {
                background: #fff;
                border: 1px solid #e2e4e7;
                border-radius: 8px;
                box-shadow: 0 2px 4px rgba(0,0,0,0.02);
                padding: 24px;
                box-sizing: border-box;
            }
            .eseo-card h2 {
                font-size: 16px;
                font-weight: 600;
                margin: 0 0 20px 0;
                color: #1d2327;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .eseo-card h2 .info-icon {
                color: #a7aaad;
                font-weight: normal;
                cursor: pointer;
            }
            /* 2x2 Metric Grid inside Card */
            .eseo-metric-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }
            .eseo-metric {
                text-align: center;
                padding: 15px;
                position: relative;
            }
            .eseo-metric:nth-child(1) { border-right: 1px solid #e2e4e7; border-bottom: 1px solid #e2e4e7; }
            .eseo-metric:nth-child(2) { border-bottom: 1px solid #e2e4e7; }
            .eseo-metric:nth-child(3) { border-right: 1px solid #e2e4e7; }
            
            .eseo-metric-value {
                font-size: 28px;
                font-weight: 700;
                color: #101517;
                margin-bottom: 5px;
            }
            .eseo-metric-label {
                font-size: 13px;
                color: #50575e;
                margin-bottom: 5px;
            }
            .eseo-metric-trend {
                font-size: 12px;
                font-weight: 600;
                color: #00a32a;
            }
            
            /* Table Styles */
            .eseo-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 13px;
            }
            .eseo-table th {
                text-align: left;
                padding: 10px 5px;
                color: #1d2327;
                font-weight: 600;
                border-bottom: 1px solid #e2e4e7;
            }
            .eseo-table td {
                padding: 12px 5px;
                color: #50575e;
                border-bottom: 1px solid #f0f0f1;
            }
            .eseo-table tr:last-child td {
                border-bottom: none;
            }
            
            /* SEO Scores specific */
            .eseo-scores-container {
                display: flex;
                align-items: center;
                gap: 40px;
            }
            .eseo-scores-list {
                flex: 1;
                list-style: none;
                margin: 0;
                padding: 0;
            }
            .eseo-scores-list li {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 10px 0;
                border-bottom: 1px solid #f0f0f1;
            }
            .eseo-scores-list li:last-child {
                border-bottom: none;
            }
            .eseo-score-label {
                display: flex;
                align-items: center;
                font-weight: 500;
                color: #1d2327;
            }
            .eseo-score-dot {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                margin-right: 10px;
            }
            .eseo-score-count {
                background: #e2e4e7;
                padding: 2px 8px;
                border-radius: 12px;
                font-size: 12px;
                font-weight: 600;
                margin-left: 10px;
            }
            .eseo-score-view {
                border: 1px solid #ccd0d4;
                background: #f8f9fa;
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 12px;
                text-decoration: none;
                color: #1d2327;
            }
            .eseo-score-view:hover {
                background: #fff;
            }
            .eseo-chart-container {
                width: 180px;
                height: 180px;
                position: relative;
            }
        </style>

        <div class="eseo-wrap">
            <div class="eseo-header">
                <h1>Mero Afno Premium SEO Dashboard</h1>
                <p style="color: #50575e; margin: 0;">Monitor your search performance and site health.</p>
            </div>

            <div class="eseo-grid">
                <!-- Metrics Card -->
                <div class="eseo-card">
                    <h2>Search Performance (Mock Data) <span class="info-icon">ⓘ</span></h2>
                    <div class="eseo-metric-grid">
                        <div class="eseo-metric">
                            <div class="eseo-metric-value">18</div>
                            <div class="eseo-metric-label">Impressions</div>
                            <div class="eseo-metric-trend">+100.00%</div>
                        </div>
                        <div class="eseo-metric">
                            <div class="eseo-metric-value">2</div>
                            <div class="eseo-metric-label">Clicks</div>
                            <div class="eseo-metric-trend">+100.00%</div>
                        </div>
                        <div class="eseo-metric">
                            <div class="eseo-metric-value">11.11%</div>
                            <div class="eseo-metric-label">Average CTR</div>
                        </div>
                        <div class="eseo-metric">
                            <div class="eseo-metric-value">14.83</div>
                            <div class="eseo-metric-label">Average position</div>
                        </div>
                    </div>
                </div>

                <!-- Sessions Chart Card -->
                <div class="eseo-card">
                    <h2>Organic sessions (Mock Data) <span class="info-icon">ⓘ</span></h2>
                    <div style="font-size: 24px; font-weight: bold; margin-bottom: 5px;">16</div>
                    <div style="font-size: 12px; color: #50575e; margin-bottom: 15px;">Last 28 days</div>
                    <div style="height: 150px;">
                        <canvas id="organicSessionsChart"></canvas>
                    </div>
                </div>
            </div>

            <div class="eseo-grid">
                <!-- Top Content Card -->
                <div class="eseo-card">
                    <h2>Top 5 most popular content (Mock Data) <span class="info-icon">ⓘ</span></h2>
                    <table class="eseo-table">
                        <thead>
                            <tr>
                                <th>Landing page</th>
                                <th style="text-align:right;">Clicks</th>
                                <th style="text-align:right;">Impressions</th>
                                <th style="text-align:right;">CTR</th>
                                <th style="text-align:right;">Position</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>/</td>
                                <td style="text-align:right;">1</td>
                                <td style="text-align:right;">1</td>
                                <td style="text-align:right;">100.00%</td>
                                <td style="text-align:right;">1.00</td>
                            </tr>
                            <tr>
                                <td>/country/brunei-darussalam/</td>
                                <td style="text-align:right;">1</td>
                                <td style="text-align:right;">1</td>
                                <td style="text-align:right;">100.00%</td>
                                <td style="text-align:right;">10.00</td>
                            </tr>
                            <tr>
                                <td>/country/cote-divoire/</td>
                                <td style="text-align:right;">0</td>
                                <td style="text-align:right;">2</td>
                                <td style="text-align:right;">0.00%</td>
                                <td style="text-align:right;">8.00</td>
                            </tr>
                            <tr>
                                <td>/country/dominica/</td>
                                <td style="text-align:right;">0</td>
                                <td style="text-align:right;">2</td>
                                <td style="text-align:right;">0.00%</td>
                                <td style="text-align:right;">6.00</td>
                            </tr>
                            <tr>
                                <td>/radio_station/gumbo-94-9-fm/</td>
                                <td style="text-align:right;">0</td>
                                <td style="text-align:right;">12</td>
                                <td style="text-align:right;">0.00%</td>
                                <td style="text-align:right;">18.42</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Top Queries Card -->
                <div class="eseo-card">
                    <h2>Top 5 search queries (Mock Data) <span class="info-icon">ⓘ</span></h2>
                    <table class="eseo-table">
                        <thead>
                            <tr>
                                <th>Query</th>
                                <th style="text-align:right;">Clicks</th>
                                <th style="text-align:right;">Impressions</th>
                                <th style="text-align:right;">CTR</th>
                                <th style="text-align:right;">Position</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>94.9 gumbo</td>
                                <td style="text-align:right;">0</td>
                                <td style="text-align:right;">1</td>
                                <td style="text-align:right;">0.00%</td>
                                <td style="text-align:right;">43.00</td>
                            </tr>
                            <tr>
                                <td>94.9 radio live</td>
                                <td style="text-align:right;">0</td>
                                <td style="text-align:right;">1</td>
                                <td style="text-align:right;">0.00%</td>
                                <td style="text-align:right;">63.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="eseo-grid" style="grid-template-columns: 1fr;">
                <!-- Real SEO Scores Card -->
                <div class="eseo-card">
                    <h2>SEO scores (Real Data)</h2>
                    <p style="color:#50575e; font-size:13px;">This analyzes the presence of your SEO Title, Meta Description, and Focus Keyword across all published content.</p>
                    <div class="eseo-scores-container" style="margin-top:20px;">
                        <ul class="eseo-scores-list">
                            <li>
                                <div class="eseo-score-label">
                                    <div class="eseo-score-dot" style="background:#7ad03a;"></div> Good 
                                    <span class="eseo-score-count"><?php echo esc_html($seo_scores['good']); ?></span>
                                </div>
                                <a href="<?php echo admin_url('edit.php'); ?>" class="eseo-score-view">View</a>
                            </li>
                            <li>
                                <div class="eseo-score-label">
                                    <div class="eseo-score-dot" style="background:#e88a31;"></div> OK 
                                    <span class="eseo-score-count"><?php echo esc_html($seo_scores['ok']); ?></span>
                                </div>
                                <a href="<?php echo admin_url('edit.php'); ?>" class="eseo-score-view">View</a>
                            </li>
                            <li>
                                <div class="eseo-score-label">
                                    <div class="eseo-score-dot" style="background:#dc3232;"></div> Needs improvement 
                                    <span class="eseo-score-count"><?php echo esc_html($seo_scores['needs_improvement']); ?></span>
                                </div>
                                <a href="<?php echo admin_url('edit.php'); ?>" class="eseo-score-view">View</a>
                            </li>
                            <li>
                                <div class="eseo-score-label">
                                    <div class="eseo-score-dot" style="background:#dcdde0;"></div> Not analyzed 
                                    <span class="eseo-score-count"><?php echo esc_html($seo_scores['not_analyzed']); ?></span>
                                </div>
                                <a href="<?php echo admin_url('edit.php'); ?>" class="eseo-score-view">View</a>
                            </li>
                        </ul>
                        <div class="eseo-chart-container">
                            <canvas id="seoScoresChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Organic Sessions Chart
            const ctx1 = document.getElementById('organicSessionsChart').getContext('2d');
            new Chart(ctx1, {
                type: 'line',
                data: {
                    labels: ['May 25', 'May 28', 'May 31', 'Jun 3', 'Jun 6', 'Jun 9', 'Jun 12', 'Jun 15', 'Jun 18', 'Jun 21'],
                    datasets: [{
                        label: 'Organic sessions',
                        data: [0, 0, 0, 0, 0, 0, 1, 3, 1, 4, 0, 2, 0, 3], // Mock points matching screenshot shape
                        borderColor: '#a32375',
                        backgroundColor: 'rgba(163, 35, 117, 0.1)',
                        borderWidth: 2,
                        pointBackgroundColor: '#a32375',
                        pointBorderColor: '#fff',
                        pointRadius: 4,
                        fill: true,
                        tension: 0.1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, border: {display: false}, grid: {color: '#f0f0f1'} },
                        x: { grid: {display: false} }
                    }
                }
            });

            // SEO Scores Donut Chart
            const ctx2 = document.getElementById('seoScoresChart').getContext('2d');
            const dataScores = [
                <?php echo (int) $seo_scores['good']; ?>,
                <?php echo (int) $seo_scores['ok']; ?>,
                <?php echo (int) $seo_scores['needs_improvement']; ?>,
                <?php echo (int) $seo_scores['not_analyzed']; ?>
            ];
            
            // If all zero, show a grey ring
            const total = dataScores.reduce((a,b) => a + b, 0);
            if (total === 0) {
                dataScores[3] = 1; // Fake one gray dot for empty state
            }

            new Chart(ctx2, {
                type: 'doughnut',
                data: {
                    labels: ['Good', 'OK', 'Needs improvement', 'Not analyzed'],
                    datasets: [{
                        data: dataScores,
                        backgroundColor: ['#7ad03a', '#e88a31', '#dc3232', '#dcdde0'],
                        borderWidth: 0,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    cutout: '75%',
                    plugins: { legend: { display: false } }
                }
            });
        });
        </script>
        <?php
    }

    public function render_ai_settings() {
        $disabled_modules = get_option( 'eseo_modules_disabled', [] );
        if ( ! is_array( $disabled_modules ) ) {
            $disabled_modules = [];
        }
        ?>
        <div class="wrap">
            <h1>Mero Afno Premium SEO Settings</h1>
            <p>Configure your API keys and toggle plugin features.</p>
            <form method="post" action="options.php">
                <?php settings_fields( 'eseo_ai_options' ); ?>
                <?php do_settings_sections( 'eseo_ai_options' ); ?>
                
                <h2>API Keys</h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Preferred AI Engine</th>
                        <td>
                            <?php $preferred = get_option('eseo_preferred_ai_engine', 'smart'); ?>
                            <select name="eseo_preferred_ai_engine">
                                <option value="smart" <?php selected($preferred, 'smart'); ?>>Smart Routing (Recommended)</option>
                                <option value="openai" <?php selected($preferred, 'openai'); ?>>OpenAI Only</option>
                                <option value="gemini" <?php selected($preferred, 'gemini'); ?>>Gemini Only</option>
                            </select>
                            <p class="description">Select which AI engine to use for generating content. Smart routing uses Gemini for keywords and OpenAI for descriptions.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">OpenAI API Key</th>
                        <td>
                            <input type="password" name="eseo_openai_key" value="<?php echo esc_attr( get_option('eseo_openai_key') ); ?>" class="regular-text" />
                            <p class="description">Required if OpenAI is selected.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">OpenAI Model</th>
                        <td>
                            <?php $o_model = get_option('eseo_openai_model', 'gpt-3.5-turbo'); ?>
                            <select name="eseo_openai_model">
                                <option value="gpt-3.5-turbo" <?php selected($o_model, 'gpt-3.5-turbo'); ?>>GPT-3.5 Turbo (Fast & Cheap)</option>
                                <option value="gpt-4o" <?php selected($o_model, 'gpt-4o'); ?>>GPT-4o (Most Powerful)</option>
                                <option value="gpt-4-turbo" <?php selected($o_model, 'gpt-4-turbo'); ?>>GPT-4 Turbo</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Gemini API Key</th>
                        <td>
                            <input type="password" name="eseo_gemini_key" value="<?php echo esc_attr( get_option('eseo_gemini_key') ); ?>" class="regular-text" />
                            <p class="description">Required if Gemini is selected.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Gemini Model</th>
                        <td>
                            <?php $g_model = get_option('eseo_gemini_model', 'gemini-3.5-flash'); ?>
                            <select name="eseo_gemini_model">
                                <option value="gemini-3.5-flash" <?php selected($g_model, 'gemini-3.5-flash'); ?>>Gemini 3.5 Flash (Latest & Fast)</option>
                                <option value="gemini-3.1-pro" <?php selected($g_model, 'gemini-3.1-pro'); ?>>Gemini 3.1 Pro (Latest & Powerful)</option>
                                <option value="gemini-2.0-flash" <?php selected($g_model, 'gemini-2.0-flash'); ?>>Gemini 2.0 Flash</option>
                                <option value="gemini-2.0-pro" <?php selected($g_model, 'gemini-2.0-pro'); ?>>Gemini 2.0 Pro</option>
                                <option value="gemini-1.5-flash" <?php selected($g_model, 'gemini-1.5-flash'); ?>>Gemini 1.5 Flash</option>
                                <option value="gemini-1.5-pro" <?php selected($g_model, 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <h2>Module Toggles</h2>
                <p>Check the box to <strong>disable</strong> a module you do not need.</p>
                <table class="form-table">
                    <?php 
                    $modules = [
                        'sitemap' => 'XML Sitemaps',
                        'schema' => 'JSON-LD Article Schema',
                        'redirects' => '404 Redirect Manager',
                        'content_audit' => 'Link Content Audit',
                        'social_seo' => 'Social SEO (OpenGraph)',
                        'local_seo' => 'Local SEO',
                        'woo_seo' => 'WooCommerce SEO',
                        'performance' => 'Performance Optimizer',
                        'indexing' => 'Indexing API',
                        'image_seo' => 'Image SEO Automation'
                    ];
                    
                    foreach ( $modules as $key => $label ) {
                        $checked = in_array( $key, $disabled_modules ) ? 'checked="checked"' : '';
                        echo '<tr valign="top">';
                        echo '<th scope="row">' . esc_html( $label ) . '</th>';
                        echo '<td><label><input type="checkbox" name="eseo_modules_disabled[]" value="' . esc_attr( $key ) . '" ' . $checked . ' /> Disable</label></td>';
                        echo '</tr>';
                    }
                    ?>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function render_indexing_settings() {
        ?>
        <div class="wrap">
            <h1>Indexing API Settings</h1>
            <p>Configure automatic fast indexing for Google and Bing. When you publish or update a post, this plugin will instantly ping search engines.</p>
            <form method="post" action="options.php">
                <?php settings_fields( 'eseo_indexing_options' ); ?>
                <?php do_settings_sections( 'eseo_indexing_options' ); ?>
                
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row">Google Indexing API (JSON Key)</th>
                        <td>
                            <textarea name="eseo_google_indexing_key" rows="8" class="large-text code"><?php echo esc_textarea( get_option('eseo_google_indexing_key') ); ?></textarea>
                            <p class="description">Paste the entire contents of your Google Cloud Service Account JSON file here.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Bing Webmaster API Key</th>
                        <td>
                            <input type="password" name="eseo_bing_api_key" value="<?php echo esc_attr( get_option('eseo_bing_api_key') ); ?>" class="regular-text" />
                            <p class="description">Get this from your Bing Webmaster Tools -> Settings -> API Access.</p>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Status</th>
                        <td>
                            <?php 
                            $last_google = get_option('eseo_last_google_ping');
                            $last_bing = get_option('eseo_last_bing_ping');
                            ?>
                            <p><strong>Last Google Ping:</strong> <?php echo $last_google ? date('Y-m-d H:i:s', $last_google) : 'Never'; ?></p>
                            <p><strong>Last Bing Ping:</strong> <?php echo $last_bing ? date('Y-m-d H:i:s', $last_bing) : 'Never'; ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}
