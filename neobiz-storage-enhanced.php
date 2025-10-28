<?php
/**
 * Plugin Name: NeoBiz Storage Enhanced
 * Description: Multi-bucket S3-compatible storage with modern UI, advanced sync, auto-discovery, and bucket tabs in media library.
 * Version: 0.4.0
 * Author: NeoBiz
 * License: GPLv2 or later
 * Text Domain: neobiz-storage
 */

if (!defined('ABSPATH')) exit;

// ===== Constants =====
define('NBS_VERSION', '0.4.0');
define('NBS_OPTION',  'nbs_settings');
define('NBS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('NBS_PLUGIN_URL', plugin_dir_url(__FILE__));

// ===== Activation checks =====
register_activation_hook(__FILE__, function(){
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('NeoBiz Storage requires PHP 7.4 or newer.');
    }
    nbs_create_tables();
    nbs_create_assets();
});

// ===== Database tables =====
function nbs_create_tables(){
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    
    // Buckets table
    $buckets_table = $wpdb->prefix . 'nbs_buckets';
    $sql_buckets = "CREATE TABLE IF NOT EXISTS $buckets_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        bucket_name varchar(255) NOT NULL,
        bucket_label varchar(255) NOT NULL,
        provider varchar(50) NOT NULL DEFAULT 'custom',
        access_key varchar(255) NOT NULL,
        secret_key varchar(255) NOT NULL,
        region varchar(100) NOT NULL,
        endpoint varchar(500) NOT NULL,
        path_prefix varchar(255) DEFAULT '',
        cdn_base varchar(500) DEFAULT '',
        use_path_style tinyint(1) DEFAULT 1,
        is_default tinyint(1) DEFAULT 0,
        is_active tinyint(1) DEFAULT 1,
        auto_sync tinyint(1) DEFAULT 0,
        last_sync datetime DEFAULT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY bucket_name (bucket_name)
    ) $charset;";
    
    // Sync log table
    $sync_table = $wpdb->prefix . 'nbs_sync_log';
    $sql_sync = "CREATE TABLE IF NOT EXISTS $sync_table (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        attachment_id bigint(20) NOT NULL,
        bucket_id bigint(20) NOT NULL,
        action varchar(50) NOT NULL,
        status varchar(20) NOT NULL,
        message text,
        synced_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY attachment_id (attachment_id),
        KEY bucket_id (bucket_id)
    ) $charset;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_buckets);
    dbDelta($sql_sync);
}

// ===== Default settings =====
function nbs_settings_default(){
    return [
        'provider' => 'custom',
        'access_key' => '',
        'secret_key' => '',
        'region' => '',
        'endpoint' => '',
        'bucket' => '',
        'path_prefix' => '',
        'cdn_base' => '',
        'use_path_style' => 1,
        'delete_local_after_offload' => 0,
        'disable_ssl_verify' => 0,
        'private_bucket' => 0,
        'signed_ttl' => 3600,
        'cache_control' => 'public, max-age=31536000, immutable',
        'storage_class' => '',
        'cron_enabled' => 0,
        'batch_size' => 25,
        'exclude_mime' => '',
        'enabled' => 1,
        'multi_bucket_enabled' => 1,
        'auto_discover_enabled' => 1,
        'sync_mode' => 'manual', // manual or auto
    ];
}

function nbs_get_settings(){
    $opt = get_option(NBS_OPTION, []);
    $defaults = nbs_settings_default();
    return array_merge($defaults, is_array($opt) ? $opt : []);
}

function nbs_sanitize_settings($input){
    $out = nbs_settings_default();
    foreach ($input as $key => $value) {
        if (array_key_exists($key, $out)) {
            if (is_bool($out[$key]) || in_array($key, ['enabled', 'delete_local_after_offload', 'disable_ssl_verify', 'private_bucket', 'use_path_style', 'cron_enabled', 'multi_bucket_enabled', 'auto_discover_enabled'])) {
                $out[$key] = !empty($value) ? 1 : 0;
            } elseif (in_array($key, ['endpoint', 'cdn_base'])) {
                $out[$key] = esc_url_raw($value);
            } elseif ($key === 'path_prefix') {
                $out[$key] = ltrim(trim($value), '/');
            } elseif (in_array($key, ['signed_ttl', 'batch_size'])) {
                $out[$key] = max(1, intval($value));
            } else {
                $out[$key] = sanitize_text_field($value);
            }
        }
    }
    return $out;
}

// ===== Bucket management functions =====
function nbs_get_buckets($active_only = false){
    global $wpdb;
    $table = $wpdb->prefix . 'nbs_buckets';
    $where = $active_only ? "WHERE is_active = 1" : "";
    return $wpdb->get_results("SELECT * FROM $table $where ORDER BY is_default DESC, bucket_label ASC", ARRAY_A);
}

function nbs_get_bucket($id){
    global $wpdb;
    $table = $wpdb->prefix . 'nbs_buckets';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id), ARRAY_A);
}

function nbs_get_bucket_by_name($bucket_name){
    global $wpdb;
    $table = $wpdb->prefix . 'nbs_buckets';
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE bucket_name = %s", $bucket_name), ARRAY_A);
}

function nbs_get_default_bucket(){
    global $wpdb;
    $table = $wpdb->prefix . 'nbs_buckets';
    $bucket = $wpdb->get_row("SELECT * FROM $table WHERE is_default = 1 AND is_active = 1 LIMIT 1", ARRAY_A);
    if (!$bucket) {
        $bucket = $wpdb->get_row("SELECT * FROM $table WHERE is_active = 1 LIMIT 1", ARRAY_A);
    }
    
    // Fallback to settings if no buckets in database
    if (!$bucket) {
        $s = nbs_get_settings();
        if (!empty($s['bucket']) && !empty($s['access_key']) && !empty($s['secret_key'])) {
            // Create virtual bucket from settings
            $bucket = [
                'id' => 0, // Virtual bucket
                'bucket_name' => $s['bucket'],
                'bucket_label' => 'Default (from settings)',
                'provider' => $s['provider'],
                'access_key' => $s['access_key'],
                'secret_key' => $s['secret_key'],
                'region' => $s['region'],
                'endpoint' => $s['endpoint'],
                'path_prefix' => $s['path_prefix'],
                'cdn_base' => $s['cdn_base'],
                'use_path_style' => $s['use_path_style'],
                'is_default' => 1,
                'is_active' => 1,
                'auto_sync' => 0,
            ];
        }
    }
    
    return $bucket;
}

function nbs_add_bucket($data){
    global $wpdb;
    $table = $wpdb->prefix . 'nbs_buckets';
    
    if (!empty($data['is_default'])) {
        $wpdb->update($table, ['is_default' => 0], ['is_default' => 1]);
    }
    
    return $wpdb->insert($table, [
        'bucket_name' => sanitize_text_field($data['bucket_name']),
        'bucket_label' => sanitize_text_field($data['bucket_label']),
        'provider' => sanitize_text_field($data['provider'] ?? 'custom'),
        'access_key' => $data['access_key'],
        'secret_key' => $data['secret_key'],
        'region' => sanitize_text_field($data['region']),
        'endpoint' => esc_url_raw($data['endpoint']),
        'path_prefix' => ltrim(trim($data['path_prefix'] ?? ''), '/'),
        'cdn_base' => untrailingslashit(esc_url_raw($data['cdn_base'] ?? '')),
        'use_path_style' => !empty($data['use_path_style']) ? 1 : 0,
        'is_default' => !empty($data['is_default']) ? 1 : 0,
        'is_active' => !empty($data['is_active']) ? 1 : 0,
        'auto_sync' => !empty($data['auto_sync']) ? 1 : 0,
    ]);
}

function nbs_update_bucket($id, $data){
    global $wpdb;
    $table = $wpdb->prefix . 'nbs_buckets';
    
    if (!empty($data['is_default'])) {
        $wpdb->update($table, ['is_default' => 0], ['is_default' => 1]);
    }
    
    $update_data = [
        'bucket_label' => sanitize_text_field($data['bucket_label']),
        'provider' => sanitize_text_field($data['provider'] ?? 'custom'),
        'access_key' => $data['access_key'],
        'secret_key' => $data['secret_key'],
        'region' => sanitize_text_field($data['region']),
        'endpoint' => esc_url_raw($data['endpoint']),
        'path_prefix' => ltrim(trim($data['path_prefix'] ?? ''), '/'),
        'cdn_base' => untrailingslashit(esc_url_raw($data['cdn_base'] ?? '')),
        'use_path_style' => !empty($data['use_path_style']) ? 1 : 0,
        'is_default' => !empty($data['is_default']) ? 1 : 0,
        'is_active' => !empty($data['is_active']) ? 1 : 0,
        'auto_sync' => !empty($data['auto_sync']) ? 1 : 0,
    ];
    
    return $wpdb->update($table, $update_data, ['id' => $id]);
}

function nbs_delete_bucket($id){
    global $wpdb;
    $table = $wpdb->prefix . 'nbs_buckets';
    return $wpdb->delete($table, ['id' => $id]);
}

function nbs_log_sync($attachment_id, $bucket_id, $action, $status, $message = ''){
    global $wpdb;
    $table = $wpdb->prefix . 'nbs_sync_log';
    return $wpdb->insert($table, [
        'attachment_id' => $attachment_id,
        'bucket_id' => $bucket_id,
        'action' => $action,
        'status' => $status,
        'message' => $message,
    ]);
}

// ===== S3 Client Helper =====
function nbs_get_s3_client($bucket_config = null){
    if (!file_exists(NBS_PLUGIN_PATH . 'vendor/autoload.php')) {
        return false;
    }
    
    require_once NBS_PLUGIN_PATH . 'vendor/autoload.php';
    
    if (!$bucket_config) {
        $s = nbs_get_settings();
        $bucket_config = [
            'access_key' => $s['access_key'],
            'secret_key' => $s['secret_key'],
            'region' => $s['region'],
            'endpoint' => $s['endpoint'],
            'use_path_style' => $s['use_path_style'],
        ];
    }
    
    $config = [
        'version' => 'latest',
        'region' => $bucket_config['region'] ?: 'us-east-1',
        'credentials' => [
            'key' => $bucket_config['access_key'],
            'secret' => $bucket_config['secret_key'],
        ],
    ];
    
    if (!empty($bucket_config['endpoint'])) {
        $config['endpoint'] = $bucket_config['endpoint'];
        $config['use_path_style_endpoint'] = !empty($bucket_config['use_path_style']);
    }
    
    try {
        return new Aws\S3\S3Client($config);
    } catch (Exception $e) {
        error_log('NBS S3 Client Error: ' . $e->getMessage());
        return false;
    }
}

// ===== Upload handler =====
function nbs_handle_upload($file, $bucket_name = null){
    $s = nbs_get_settings();
    if (!$s['enabled']) return $file;
    
    // Determine bucket to use
    if ($bucket_name) {
        $bucket = nbs_get_bucket_by_name($bucket_name);
    } else {
        $bucket = nbs_get_default_bucket();
    }
    
    if (!$bucket) return $file;
    
    $s3 = nbs_get_s3_client($bucket);
    if (!$s3) return $file;
    
    try {
        $upload_dir = wp_upload_dir();
        $relative = str_replace($upload_dir['basedir'] . '/', '', $file['file']);
        $key = $bucket['path_prefix'] ? $bucket['path_prefix'] . '/' . $relative : $relative;
        
        $args = [
            'Bucket' => $bucket['bucket_name'],
            'Key' => $key,
            'SourceFile' => $file['file'],
            'ACL' => !empty($s['private_bucket']) ? 'private' : 'public-read',
        ];
        
        if (!empty($s['cache_control'])) {
            $args['CacheControl'] = $s['cache_control'];
        }
        
        if (!empty($s['storage_class'])) {
            $args['StorageClass'] = $s['storage_class'];
        }
        
        $s3->putObject($args);
        
        // Store metadata for later reference
        $file['nbs_bucket'] = $bucket['bucket_name'];
        $file['nbs_bucket_id'] = $bucket['id'];
        $file['nbs_key'] = $key;
        $file['nbs_offloaded'] = true;
        
        if ($s['delete_local_after_offload']) {
            @unlink($file['file']);
        }
        
    } catch (Exception $e) {
        error_log('NBS Upload Error: ' . $e->getMessage());
    }
    
    return $file;
}

add_filter('wp_handle_upload', 'nbs_handle_upload', 10, 1);

// Store metadata when attachment is added
add_filter('wp_generate_attachment_metadata', function($metadata, $attachment_id){
    $file_data = get_post_meta($attachment_id, '_wp_attached_file', true);
    $upload_info = wp_upload_dir();
    
    // Check if we have offload info from upload
    if (isset($metadata['nbs_offloaded'])) {
        update_post_meta($attachment_id, '_nbs_offloaded', 1);
        update_post_meta($attachment_id, '_nbs_bucket', $metadata['nbs_bucket']);
        update_post_meta($attachment_id, '_nbs_bucket_id', $metadata['nbs_bucket_id']);
        update_post_meta($attachment_id, '_nbs_key', $metadata['nbs_key']);
        
        nbs_log_sync($attachment_id, $metadata['nbs_bucket_id'], 'upload', 'success');
    }
    
    return $metadata;
}, 10, 2);

// ===== URL Rewriting =====
add_filter('wp_get_attachment_url', function($url, $post_id){
    $s = nbs_get_settings();
    if (!$s['enabled']) return $url;
    
    $offloaded = get_post_meta($post_id, '_nbs_offloaded', true);
    if (!$offloaded) return $url;
    
    $bucket_id = get_post_meta($post_id, '_nbs_bucket_id', true);
    $key = get_post_meta($post_id, '_nbs_key', true);
    
    if (!$bucket_id || !$key) return $url;
    
    $bucket = nbs_get_bucket($bucket_id);
    if (!$bucket) return $url;
    
    if (!empty($bucket['cdn_base'])) {
        return trailingslashit($bucket['cdn_base']) . $key;
    }
    
    if (!empty($s['private_bucket'])) {
        $s3 = nbs_get_s3_client($bucket);
        if ($s3) {
            try {
                $cmd = $s3->getCommand('GetObject', [
                    'Bucket' => $bucket['bucket_name'],
                    'Key' => $key,
                ]);
                $request = $s3->createPresignedRequest($cmd, '+' . $s['signed_ttl'] . ' seconds');
                return (string) $request->getUri();
            } catch (Exception $e) {
                error_log('NBS Presigned URL Error: ' . $e->getMessage());
            }
        }
    }
    
    if ($bucket['use_path_style']) {
        return rtrim($bucket['endpoint'], '/') . '/' . $bucket['bucket_name'] . '/' . $key;
    }
    
    return rtrim($bucket['endpoint'], '/') . '/' . $key;
}, 10, 2);

// ===== Admin Menu =====
add_action('admin_menu', function(){
    add_menu_page(
        'NeoBiz Storage',
        'NeoBiz Storage',
        'manage_options',
        'neobiz-storage',
        'nbs_dashboard_page',
        'dashicons-cloud',
        100
    );
    
    add_submenu_page(
        'neobiz-storage',
        'Dashboard',
        'Dashboard',
        'manage_options',
        'neobiz-storage',
        'nbs_dashboard_page'
    );
    
    add_submenu_page(
        'neobiz-storage',
        'Manage Buckets',
        'Buckets',
        'manage_options',
        'neobiz-storage-buckets',
        'nbs_buckets_page'
    );
    
    add_submenu_page(
        'neobiz-storage',
        'Sync Manager',
        'Sync Manager',
        'manage_options',
        'neobiz-storage-sync',
        'nbs_sync_page'
    );
    
    add_submenu_page(
        'neobiz-storage',
        'Settings',
        'Settings',
        'manage_options',
        'neobiz-storage-settings',
        'nbs_settings_page'
    );
});

// ===== Admin Init =====
add_action('admin_init', function(){
    register_setting('nbs', NBS_OPTION, [
        'type' => 'array',
        'sanitize_callback' => 'nbs_sanitize_settings',
        'default' => nbs_settings_default(),
    ]);
});

// ===== Dashboard Page =====
function nbs_dashboard_page(){
    if (!current_user_can('manage_options')) return;
    
    $buckets = nbs_get_buckets();
    $active_buckets = nbs_get_buckets(true);
    $s = nbs_get_settings();
    
    global $wpdb;
    $total_attachments = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'");
    $offloaded_attachments = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_nbs_offloaded' AND meta_value = '1'");
    
    ?>
    <div class="wrap nbs-wrap">
        <h1 class="nbs-title">
            <span class="dashicons dashicons-cloud"></span>
            NeoBiz Storage Dashboard
        </h1>
        
        <div class="nbs-dashboard">
            <div class="nbs-stats-grid">
                <div class="nbs-stat-card">
                    <div class="nbs-stat-icon nbs-stat-icon-bucket">
                        <span class="dashicons dashicons-database"></span>
                    </div>
                    <div class="nbs-stat-content">
                        <div class="nbs-stat-value"><?php echo count($buckets); ?></div>
                        <div class="nbs-stat-label">Total Buckets</div>
                        <div class="nbs-stat-sublabel"><?php echo count($active_buckets); ?> active</div>
                    </div>
                </div>
                
                <div class="nbs-stat-card">
                    <div class="nbs-stat-icon nbs-stat-icon-files">
                        <span class="dashicons dashicons-media-default"></span>
                    </div>
                    <div class="nbs-stat-content">
                        <div class="nbs-stat-value"><?php echo number_format($total_attachments); ?></div>
                        <div class="nbs-stat-label">Total Attachments</div>
                        <div class="nbs-stat-sublabel"><?php echo number_format($offloaded_attachments); ?> offloaded</div>
                    </div>
                </div>
                
                <div class="nbs-stat-card">
                    <div class="nbs-stat-icon nbs-stat-icon-sync">
                        <span class="dashicons dashicons-update"></span>
                    </div>
                    <div class="nbs-stat-content">
                        <div class="nbs-stat-value"><?php echo ucfirst($s['sync_mode']); ?></div>
                        <div class="nbs-stat-label">Sync Mode</div>
                        <div class="nbs-stat-sublabel">
                            <?php echo $s['enabled'] ? '<span class="nbs-status-active">Active</span>' : '<span class="nbs-status-inactive">Inactive</span>'; ?>
                        </div>
                    </div>
                </div>
                
                <div class="nbs-stat-card">
                    <div class="nbs-stat-icon nbs-stat-icon-discover">
                        <span class="dashicons dashicons-search"></span>
                    </div>
                    <div class="nbs-stat-content">
                        <div class="nbs-stat-value"><?php echo $s['auto_discover_enabled'] ? 'On' : 'Off'; ?></div>
                        <div class="nbs-stat-label">Auto Discover</div>
                        <div class="nbs-stat-sublabel">Import from buckets</div>
                    </div>
                </div>
            </div>
            
            <div class="nbs-quick-actions">
                <h2>Quick Actions</h2>
                <div class="nbs-action-buttons">
                    <a href="<?php echo admin_url('admin.php?page=neobiz-storage-buckets'); ?>" class="button button-primary button-large">
                        <span class="dashicons dashicons-database"></span>
                        Manage Buckets
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=neobiz-storage-sync'); ?>" class="button button-secondary button-large">
                        <span class="dashicons dashicons-update"></span>
                        Sync Manager
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=neobiz-storage-settings'); ?>" class="button button-secondary button-large">
                        <span class="dashicons dashicons-admin-settings"></span>
                        Settings
                    </a>
                </div>
            </div>
            
            <?php if (!empty($active_buckets)): ?>
            <div class="nbs-buckets-overview">
                <h2>Active Buckets</h2>
                <div class="nbs-buckets-list">
                    <?php foreach ($active_buckets as $bucket): ?>
                    <div class="nbs-bucket-card">
                        <div class="nbs-bucket-header">
                            <h3>
                                <?php echo esc_html($bucket['bucket_label']); ?>
                                <?php if ($bucket['is_default']): ?>
                                    <span class="nbs-badge nbs-badge-default">Default</span>
                                <?php endif; ?>
                                <?php if ($bucket['auto_sync']): ?>
                                    <span class="nbs-badge nbs-badge-auto">Auto Sync</span>
                                <?php endif; ?>
                            </h3>
                        </div>
                        <div class="nbs-bucket-info">
                            <div class="nbs-bucket-detail">
                                <strong>Bucket:</strong> <?php echo esc_html($bucket['bucket_name']); ?>
                            </div>
                            <div class="nbs-bucket-detail">
                                <strong>Provider:</strong> <?php echo esc_html(ucfirst($bucket['provider'])); ?>
                            </div>
                            <div class="nbs-bucket-detail">
                                <strong>Region:</strong> <?php echo esc_html($bucket['region']); ?>
                            </div>
                            <?php if ($bucket['last_sync']): ?>
                            <div class="nbs-bucket-detail">
                                <strong>Last Sync:</strong> <?php echo date('Y-m-d H:i:s', strtotime($bucket['last_sync'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="nbs-bucket-actions">
                            <button class="button nbs-sync-bucket-btn" data-bucket-id="<?php echo $bucket['id']; ?>">
                                <span class="dashicons dashicons-update"></span>
                                Sync Now
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// ===== Buckets Management Page =====
function nbs_buckets_page(){
    if (!current_user_can('manage_options')) return;
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nbs_bucket_action'])) {
        check_admin_referer('nbs_bucket_action');
        
        $action = $_POST['nbs_bucket_action'];
        
        if ($action === 'add') {
            nbs_add_bucket($_POST['bucket']);
            echo '<div class="notice notice-success"><p>Bucket added successfully!</p></div>';
        } elseif ($action === 'edit' && isset($_POST['bucket_id'])) {
            nbs_update_bucket($_POST['bucket_id'], $_POST['bucket']);
            echo '<div class="notice notice-success"><p>Bucket updated successfully!</p></div>';
        } elseif ($action === 'delete' && isset($_POST['bucket_id'])) {
            nbs_delete_bucket($_POST['bucket_id']);
            echo '<div class="notice notice-success"><p>Bucket deleted successfully!</p></div>';
        }
    }
    
    $buckets = nbs_get_buckets();
    $editing = isset($_GET['edit']) ? intval($_GET['edit']) : 0;
    $edit_bucket = $editing ? nbs_get_bucket($editing) : null;
    
    ?>
    <div class="wrap nbs-wrap">
        <h1 class="nbs-title">
            <span class="dashicons dashicons-database"></span>
            Manage Buckets
        </h1>
        
        <div class="nbs-buckets-manager">
            <div class="nbs-bucket-form-container">
                <h2><?php echo $editing ? 'Edit Bucket' : 'Add New Bucket'; ?></h2>
                <form method="post" class="nbs-bucket-form">
                    <?php wp_nonce_field('nbs_bucket_action'); ?>
                    <input type="hidden" name="nbs_bucket_action" value="<?php echo $editing ? 'edit' : 'add'; ?>">
                    <?php if ($editing): ?>
                        <input type="hidden" name="bucket_id" value="<?php echo $editing; ?>">
                    <?php endif; ?>
                    
                    <div class="nbs-form-grid">
                        <div class="nbs-form-group">
                            <label>Bucket Label *</label>
                            <input type="text" name="bucket[bucket_label]" value="<?php echo $edit_bucket ? esc_attr($edit_bucket['bucket_label']) : ''; ?>" required>
                            <span class="description">Friendly name for this bucket</span>
                        </div>
                        
                        <div class="nbs-form-group">
                            <label>Bucket Name *</label>
                            <input type="text" name="bucket[bucket_name]" value="<?php echo $edit_bucket ? esc_attr($edit_bucket['bucket_name']) : ''; ?>" <?php echo $editing ? 'readonly' : 'required'; ?>>
                            <span class="description">Actual bucket name in S3</span>
                        </div>
                        
                        <div class="nbs-form-group">
                            <label>Provider</label>
                            <select name="bucket[provider]">
                                <option value="custom" <?php selected($edit_bucket['provider'] ?? 'custom', 'custom'); ?>>Custom/MinIO</option>
                                <option value="aws" <?php selected($edit_bucket['provider'] ?? '', 'aws'); ?>>AWS S3</option>
                                <option value="do" <?php selected($edit_bucket['provider'] ?? '', 'do'); ?>>DigitalOcean Spaces</option>
                                <option value="wasabi" <?php selected($edit_bucket['provider'] ?? '', 'wasabi'); ?>>Wasabi</option>
                            </select>
                        </div>
                        
                        <div class="nbs-form-group">
                            <label>Access Key *</label>
                            <input type="text" name="bucket[access_key]" value="<?php echo $edit_bucket ? esc_attr($edit_bucket['access_key']) : ''; ?>" required>
                        </div>
                        
                        <div class="nbs-form-group">
                            <label>Secret Key *</label>
                            <input type="password" name="bucket[secret_key]" value="<?php echo $edit_bucket ? esc_attr($edit_bucket['secret_key']) : ''; ?>" required>
                        </div>
                        
                        <div class="nbs-form-group">
                            <label>Region *</label>
                            <input type="text" name="bucket[region]" value="<?php echo $edit_bucket ? esc_attr($edit_bucket['region']) : 'us-east-1'; ?>" required>
                            <span class="description">e.g., us-east-1</span>
                        </div>
                        
                        <div class="nbs-form-group nbs-form-group-full">
                            <label>Endpoint URL *</label>
                            <input type="url" name="bucket[endpoint]" value="<?php echo $edit_bucket ? esc_attr($edit_bucket['endpoint']) : ''; ?>" required>
                            <span class="description">e.g., https://s3.amazonaws.com or http://localhost:9000</span>
                        </div>
                        
                        <div class="nbs-form-group">
                            <label>Path Prefix</label>
                            <input type="text" name="bucket[path_prefix]" value="<?php echo $edit_bucket ? esc_attr($edit_bucket['path_prefix']) : ''; ?>">
                            <span class="description">Optional folder path in bucket</span>
                        </div>
                        
                        <div class="nbs-form-group">
                            <label>CDN Base URL</label>
                            <input type="url" name="bucket[cdn_base]" value="<?php echo $edit_bucket ? esc_attr($edit_bucket['cdn_base']) : ''; ?>">
                            <span class="description">Optional CDN URL for serving files</span>
                        </div>
                    </div>
                    
                    <div class="nbs-form-checkboxes">
                        <label>
                            <input type="checkbox" name="bucket[use_path_style]" value="1" <?php checked($edit_bucket['use_path_style'] ?? 1, 1); ?>>
                            Use path-style addressing
                        </label>
                        <label>
                            <input type="checkbox" name="bucket[is_default]" value="1" <?php checked($edit_bucket['is_default'] ?? 0, 1); ?>>
                            Set as default bucket
                        </label>
                        <label>
                            <input type="checkbox" name="bucket[is_active]" value="1" <?php checked($edit_bucket['is_active'] ?? 1, 1); ?>>
                            Active (show in media library)
                        </label>
                        <label>
                            <input type="checkbox" name="bucket[auto_sync]" value="1" <?php checked($edit_bucket['auto_sync'] ?? 0, 1); ?>>
                            Auto sync uploads to this bucket
                        </label>
                    </div>
                    
                    <div class="nbs-form-actions">
                        <button type="submit" class="button button-primary button-large">
                            <?php echo $editing ? 'Update Bucket' : 'Add Bucket'; ?>
                        </button>
                        <?php if ($editing): ?>
                            <a href="<?php echo admin_url('admin.php?page=neobiz-storage-buckets'); ?>" class="button button-secondary">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <div class="nbs-buckets-list-container">
                <h2>Configured Buckets</h2>
                <?php if (empty($buckets)): ?>
                    <p class="nbs-empty-state">No buckets configured yet. Add your first bucket to get started!</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped nbs-buckets-table">
                        <thead>
                            <tr>
                                <th>Label</th>
                                <th>Bucket Name</th>
                                <th>Provider</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($buckets as $bucket): ?>
                            <tr>
                                <td>
                                    <strong><?php echo esc_html($bucket['bucket_label']); ?></strong>
                                    <?php if ($bucket['is_default']): ?>
                                        <span class="nbs-badge nbs-badge-default">Default</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html($bucket['bucket_name']); ?></td>
                                <td><?php echo esc_html(ucfirst($bucket['provider'])); ?></td>
                                <td>
                                    <?php if ($bucket['is_active']): ?>
                                        <span class="nbs-status-active">Active</span>
                                    <?php else: ?>
                                        <span class="nbs-status-inactive">Inactive</span>
                                    <?php endif; ?>
                                    <?php if ($bucket['auto_sync']): ?>
                                        <span class="nbs-badge nbs-badge-auto">Auto</span>
                                    <?php endif; ?>
                                </td>
                                <td class="nbs-table-actions">
                                    <a href="<?php echo admin_url('admin.php?page=neobiz-storage-buckets&edit=' . $bucket['id']); ?>" class="button button-small">Edit</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this bucket? This will not delete files from the bucket.');">
                                        <?php wp_nonce_field('nbs_bucket_action'); ?>
                                        <input type="hidden" name="nbs_bucket_action" value="delete">
                                        <input type="hidden" name="bucket_id" value="<?php echo $bucket['id']; ?>">
                                        <button type="submit" class="button button-small button-link-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

// ===== Sync Manager Page =====
function nbs_sync_page(){
    if (!current_user_can('manage_options')) return;
    
    $buckets = nbs_get_buckets(true);
    $s = nbs_get_settings();
    
    ?>
    <div class="wrap nbs-wrap">
        <h1 class="nbs-title">
            <span class="dashicons dashicons-update"></span>
            Sync Manager
        </h1>
        
        <div class="nbs-sync-manager">
            <div class="nbs-sync-controls">
                <div class="nbs-card">
                    <h2>Manual Sync</h2>
                    <p>Sync all attachments to selected bucket(s). This will upload unsynced files.</p>
                    
                    <div class="nbs-form-group">
                        <label>Select Bucket</label>
                        <select id="nbs-sync-bucket-select" class="nbs-select-large">
                            <option value="">All Active Buckets</option>
                            <?php foreach ($buckets as $bucket): ?>
                                <option value="<?php echo $bucket['id']; ?>">
                                    <?php echo esc_html($bucket['bucket_label']); ?>
                                    <?php echo $bucket['is_default'] ? ' (Default)' : ''; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="nbs-sync-options">
                        <label>
                            <input type="checkbox" id="nbs-force-resync" value="1">
                            Force re-sync already synced files
                        </label>
                        <label>
                            <input type="checkbox" id="nbs-delete-local" value="1">
                            Delete local files after sync
                        </label>
                    </div>
                    
                    <button id="nbs-start-sync" class="button button-primary button-hero">
                        <span class="dashicons dashicons-update"></span>
                        Start Sync
                    </button>
                </div>
                
                <div class="nbs-card">
                    <h2>Auto Discover</h2>
                    <p>Scan buckets and import existing files to media library.</p>
                    
                    <div class="nbs-form-group">
                        <label>Select Bucket</label>
                        <select id="nbs-discover-bucket-select" class="nbs-select-large">
                            <?php foreach ($buckets as $bucket): ?>
                                <option value="<?php echo $bucket['id']; ?>">
                                    <?php echo esc_html($bucket['bucket_label']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="nbs-discover-options">
                        <label>
                            <input type="checkbox" id="nbs-discover-skip-existing" value="1" checked>
                            Skip files already in media library
                        </label>
                    </div>
                    
                    <button id="nbs-start-discover" class="button button-secondary button-hero">
                        <span class="dashicons dashicons-search"></span>
                        Start Discovery
                    </button>
                </div>
            </div>
            
            <div id="nbs-sync-progress" class="nbs-sync-progress" style="display:none;">
                <div class="nbs-card">
                    <h3 id="nbs-progress-title">Syncing...</h3>
                    <div class="nbs-progress-bar">
                        <div id="nbs-progress-fill" class="nbs-progress-fill"></div>
                    </div>
                    <div class="nbs-progress-stats">
                        <div class="nbs-progress-stat">
                            <span class="nbs-progress-label">Progress:</span>
                            <span id="nbs-progress-text">0%</span>
                        </div>
                        <div class="nbs-progress-stat">
                            <span class="nbs-progress-label">Files:</span>
                            <span id="nbs-progress-files">0 / 0</span>
                        </div>
                        <div class="nbs-progress-stat">
                            <span class="nbs-progress-label">Success:</span>
                            <span id="nbs-progress-success">0</span>
                        </div>
                        <div class="nbs-progress-stat">
                            <span class="nbs-progress-label">Failed:</span>
                            <span id="nbs-progress-failed">0</span>
                        </div>
                    </div>
                    <div id="nbs-progress-log" class="nbs-progress-log"></div>
                    <button id="nbs-stop-sync" class="button button-secondary">Stop</button>
                </div>
            </div>
            
            <div class="nbs-sync-history">
                <h2>Recent Sync Activity</h2>
                <div id="nbs-sync-log">
                    <p class="nbs-empty-state">No sync activity yet.</p>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// ===== Settings Page =====
function nbs_settings_page(){
    if (!current_user_can('manage_options')) return;
    
    $s = nbs_get_settings();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['nbs_settings'])) {
        check_admin_referer('nbs_settings');
        update_option(NBS_OPTION, nbs_sanitize_settings($_POST['nbs_settings']));
        echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        $s = nbs_get_settings();
    }
    
    ?>
    <div class="wrap nbs-wrap">
        <h1 class="nbs-title">
            <span class="dashicons dashicons-admin-settings"></span>
            General Settings
        </h1>
        
        <form method="post" class="nbs-settings-form">
            <?php wp_nonce_field('nbs_settings'); ?>
            
            <div class="nbs-settings-tabs">
                <button type="button" class="nbs-tab-btn active" data-tab="connection">Connection</button>
                <button type="button" class="nbs-tab-btn" data-tab="general">General</button>
                <button type="button" class="nbs-tab-btn" data-tab="sync">Sync Options</button>
                <button type="button" class="nbs-tab-btn" data-tab="advanced">Advanced</button>
            </div>
            
            <div class="nbs-tab-content active" id="tab-connection">
                <div class="nbs-card">
                    <h2>Default Bucket Connection</h2>
                    <p class="description" style="margin-bottom: 20px;">
                        Configure default bucket credentials. For multi-bucket setup, use 
                        <a href="<?php echo admin_url('admin.php?page=neobiz-storage-buckets'); ?>">Buckets Manager</a>.
                    </p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="nbs-provider">Provider</label>
                            </th>
                            <td>
                                <select name="nbs_settings[provider]" id="nbs-provider" class="regular-text">
                                    <option value="custom" <?php selected($s['provider'], 'custom'); ?>>Custom/MinIO</option>
                                    <option value="aws" <?php selected($s['provider'], 'aws'); ?>>AWS S3</option>
                                    <option value="do" <?php selected($s['provider'], 'do'); ?>>DigitalOcean Spaces</option>
                                    <option value="wasabi" <?php selected($s['provider'], 'wasabi'); ?>>Wasabi</option>
                                    <option value="backblaze" <?php selected($s['provider'], 'backblaze'); ?>>Backblaze B2</option>
                                </select>
                                <p class="description">Select your storage provider or use custom for S3-compatible services</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="nbs-access-key">Access Key *</label>
                            </th>
                            <td>
                                <input type="text" name="nbs_settings[access_key]" id="nbs-access-key" 
                                       value="<?php echo esc_attr($s['access_key']); ?>" class="regular-text" required>
                                <p class="description">Your S3 Access Key ID or equivalent</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="nbs-secret-key">Secret Key *</label>
                            </th>
                            <td>
                                <input type="password" name="nbs_settings[secret_key]" id="nbs-secret-key" 
                                       value="<?php echo esc_attr($s['secret_key']); ?>" class="regular-text" required 
                                       autocomplete="new-password">
                                <p class="description">Your S3 Secret Access Key or equivalent</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="nbs-region">Region *</label>
                            </th>
                            <td>
                                <input type="text" name="nbs_settings[region]" id="nbs-region" 
                                       value="<?php echo esc_attr($s['region']); ?>" class="regular-text" 
                                       placeholder="us-east-1" required>
                                <p class="description">Bucket region (e.g., us-east-1, eu-west-1, ap-southeast-1)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="nbs-endpoint">Endpoint URL *</label>
                            </th>
                            <td>
                                <input type="url" name="nbs_settings[endpoint]" id="nbs-endpoint" 
                                       value="<?php echo esc_attr($s['endpoint']); ?>" class="large-text" 
                                       placeholder="https://s3.amazonaws.com" required>
                                <button type="button" id="nbs-test-connection" class="button button-secondary" style="margin-left: 10px;">
                                    <span class="dashicons dashicons-yes"></span> Test Connection
                                </button>
                                <p class="description">
                                    S3 endpoint URL<br>
                                    <strong>Examples:</strong><br>
                                    • AWS S3: <code>https://s3.amazonaws.com</code> or <code>https://s3.[region].amazonaws.com</code><br>
                                    • MinIO: <code>http://localhost:9000</code> or <code>https://minio.yourdomain.com</code><br>
                                    • DigitalOcean: <code>https://[region].digitaloceanspaces.com</code> (e.g., nyc3, sgp1)<br>
                                    • Wasabi: <code>https://s3.wasabisys.com</code> or <code>https://s3.[region].wasabisys.com</code>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="nbs-bucket">Bucket Name *</label>
                            </th>
                            <td>
                                <input type="text" name="nbs_settings[bucket]" id="nbs-bucket" 
                                       value="<?php echo esc_attr($s['bucket']); ?>" class="regular-text" 
                                       placeholder="my-wordpress-bucket" required>
                                <button type="button" id="nbs-list-buckets" class="button button-secondary" style="margin-left: 10px;">
                                    <span class="dashicons dashicons-database"></span> List Buckets
                                </button>
                                <button type="button" id="nbs-detect-region" class="button button-secondary" style="margin-left: 5px;">
                                    <span class="dashicons dashicons-location"></span> Detect Region
                                </button>
                                <p class="description">The name of your S3 bucket (must already exist). Click "List Buckets" to auto-load available buckets.</p>
                                <div id="nbs-buckets-list" style="display:none; margin-top: 10px;"></div>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="nbs-path-prefix">Path Prefix</label>
                            </th>
                            <td>
                                <input type="text" name="nbs_settings[path_prefix]" id="nbs-path-prefix" 
                                       value="<?php echo esc_attr($s['path_prefix']); ?>" class="regular-text" 
                                       placeholder="wp-uploads/">
                                <p class="description">Optional folder path within bucket (e.g., wp-uploads/ or site-name/)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="nbs-cdn-base">CDN Base URL</label>
                            </th>
                            <td>
                                <input type="url" name="nbs_settings[cdn_base]" id="nbs-cdn-base" 
                                       value="<?php echo esc_attr($s['cdn_base']); ?>" class="large-text" 
                                       placeholder="https://cdn.yourdomain.com">
                                <p class="description">Optional CDN URL for serving files (e.g., CloudFront, CloudFlare, Spaces CDN)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Path-Style Addressing</th>
                            <td>
                                <label class="nbs-toggle">
                                    <input type="checkbox" name="nbs_settings[use_path_style]" value="1" <?php checked($s['use_path_style'], 1); ?>>
                                    <span class="nbs-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    Enable for MinIO and some S3-compatible services<br>
                                    <strong>Path-style:</strong> <code>https://endpoint/bucket/key</code><br>
                                    <strong>Virtual-hosted:</strong> <code>https://bucket.endpoint/key</code>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Disable SSL Verify</th>
                            <td>
                                <label class="nbs-toggle">
                                    <input type="checkbox" name="nbs_settings[disable_ssl_verify]" value="1" <?php checked($s['disable_ssl_verify'], 1); ?>>
                                    <span class="nbs-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    <strong style="color: #d63638;">⚠️ For development only!</strong> 
                                    Disable SSL certificate verification (for self-signed certificates)
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div style="margin-top: 20px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1; border-radius: 4px;">
                        <strong>💡 Quick Setup Tips:</strong>
                        <ul style="margin: 10px 0 0 20px;">
                            <li><strong>AWS S3:</strong> Use region-specific endpoint (e.g., https://s3.us-east-1.amazonaws.com)</li>
                            <li><strong>MinIO:</strong> Enable "Path-Style Addressing" and use http://localhost:9000 for local</li>
                            <li><strong>DigitalOcean:</strong> Endpoint format: https://[region].digitaloceanspaces.com</li>
                            <li><strong>Multi-Bucket:</strong> Configure multiple buckets in <a href="<?php echo admin_url('admin.php?page=neobiz-storage-buckets'); ?>">Buckets Manager</a></li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="nbs-tab-content" id="tab-general">
                <div class="nbs-card">
                    <h2>General Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th>Enable Plugin</th>
                            <td>
                                <label class="nbs-toggle">
                                    <input type="checkbox" name="nbs_settings[enabled]" value="1" <?php checked($s['enabled'], 1); ?>>
                                    <span class="nbs-toggle-slider"></span>
                                </label>
                                <p class="description">Turn on/off offloading functionality</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th>Multi-Bucket Mode</th>
                            <td>
                                <label class="nbs-toggle">
                                    <input type="checkbox" name="nbs_settings[multi_bucket_enabled]" value="1" <?php checked($s['multi_bucket_enabled'], 1); ?>>
                                    <span class="nbs-toggle-slider"></span>
                                </label>
                                <p class="description">Show bucket tabs in media library</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th>Auto Discover</th>
                            <td>
                                <label class="nbs-toggle">
                                    <input type="checkbox" name="nbs_settings[auto_discover_enabled]" value="1" <?php checked($s['auto_discover_enabled'], 1); ?>>
                                    <span class="nbs-toggle-slider"></span>
                                </label>
                                <p class="description">Automatically discover and import files from buckets</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th>Delete Local Files</th>
                            <td>
                                <label class="nbs-toggle">
                                    <input type="checkbox" name="nbs_settings[delete_local_after_offload]" value="1" <?php checked($s['delete_local_after_offload'], 1); ?>>
                                    <span class="nbs-toggle-slider"></span>
                                </label>
                                <p class="description">Delete files from server after successful upload</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="nbs-tab-content" id="tab-sync">
                <div class="nbs-card">
                    <h2>Sync Options</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th>Sync Mode</th>
                            <td>
                                <select name="nbs_settings[sync_mode]">
                                    <option value="manual" <?php selected($s['sync_mode'], 'manual'); ?>>Manual</option>
                                    <option value="auto" <?php selected($s['sync_mode'], 'auto'); ?>>Automatic</option>
                                </select>
                                <p class="description">Manual: Use sync manager. Auto: Upload on attachment creation</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th>Cron Sync</th>
                            <td>
                                <label class="nbs-toggle">
                                    <input type="checkbox" name="nbs_settings[cron_enabled]" value="1" <?php checked($s['cron_enabled'], 1); ?>>
                                    <span class="nbs-toggle-slider"></span>
                                </label>
                                <p class="description">Enable scheduled background sync</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th>Batch Size</th>
                            <td>
                                <input type="number" name="nbs_settings[batch_size]" value="<?php echo esc_attr($s['batch_size']); ?>" min="1" max="200">
                                <p class="description">Number of files to process per batch</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th>Exclude MIME Types</th>
                            <td>
                                <input type="text" name="nbs_settings[exclude_mime]" value="<?php echo esc_attr($s['exclude_mime']); ?>" class="regular-text">
                                <p class="description">Comma-separated list (e.g., video/mp4,application/pdf)</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="nbs-tab-content" id="tab-advanced">
                <div class="nbs-card">
                    <h2>Advanced Settings</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th>Private Bucket</th>
                            <td>
                                <label class="nbs-toggle">
                                    <input type="checkbox" name="nbs_settings[private_bucket]" value="1" <?php checked($s['private_bucket'], 1); ?>>
                                    <span class="nbs-toggle-slider"></span>
                                </label>
                                <p class="description">Use presigned URLs for private buckets</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th>Presigned URL TTL</th>
                            <td>
                                <input type="number" name="nbs_settings[signed_ttl]" value="<?php echo esc_attr($s['signed_ttl']); ?>" min="60">
                                <p class="description">Time in seconds (default: 3600)</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th>Cache Control</th>
                            <td>
                                <input type="text" name="nbs_settings[cache_control]" value="<?php echo esc_attr($s['cache_control']); ?>" class="regular-text">
                                <p class="description">e.g., public, max-age=31536000, immutable</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th>Storage Class</th>
                            <td>
                                <select name="nbs_settings[storage_class]">
                                    <option value="">Default</option>
                                    <option value="STANDARD" <?php selected($s['storage_class'], 'STANDARD'); ?>>Standard</option>
                                    <option value="REDUCED_REDUNDANCY" <?php selected($s['storage_class'], 'REDUCED_REDUNDANCY'); ?>>Reduced Redundancy</option>
                                    <option value="STANDARD_IA" <?php selected($s['storage_class'], 'STANDARD_IA'); ?>>Standard-IA</option>
                                    <option value="ONEZONE_IA" <?php selected($s['storage_class'], 'ONEZONE_IA'); ?>>One Zone-IA</option>
                                    <option value="INTELLIGENT_TIERING" <?php selected($s['storage_class'], 'INTELLIGENT_TIERING'); ?>>Intelligent-Tiering</option>
                                    <option value="GLACIER" <?php selected($s['storage_class'], 'GLACIER'); ?>>Glacier</option>
                                </select>
                            </td>
                        </tr>
                        
                        <tr>
                            <th>Disable SSL Verify</th>
                            <td>
                                <label class="nbs-toggle">
                                    <input type="checkbox" name="nbs_settings[disable_ssl_verify]" value="1" <?php checked($s['disable_ssl_verify'], 1); ?>>
                                    <span class="nbs-toggle-slider"></span>
                                </label>
                                <p class="description">For development/self-signed certificates only</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <div class="nbs-form-actions">
                <button type="submit" class="button button-primary button-large">Save Settings</button>
            </div>
        </form>
    </div>
    <?php
}

// ===== AJAX Handlers =====
add_action('wp_ajax_nbs_sync_attachments', function(){
    check_ajax_referer('nbs_ajax', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $bucket_id = isset($_POST['bucket_id']) ? intval($_POST['bucket_id']) : 0;
    $force = isset($_POST['force']) && $_POST['force'] === 'true';
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $limit = 10;
    
    // Get attachments to sync
    $args = [
        'post_type' => 'attachment',
        'post_status' => 'inherit',
        'posts_per_page' => $limit,
        'offset' => $offset,
        'fields' => 'ids',
    ];
    
    if (!$force) {
        $args['meta_query'] = [
            'relation' => 'OR',
            [
                'key' => '_nbs_offloaded',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_nbs_offloaded',
                'value' => '1',
                'compare' => '!=',
            ],
        ];
    }
    
    $query = new WP_Query($args);
    $total = $query->found_posts;
    $attachments = $query->posts;
    
    $results = [
        'total' => $total,
        'processed' => $offset + count($attachments),
        'success' => 0,
        'failed' => 0,
        'items' => [],
    ];
    
    foreach ($attachments as $attachment_id) {
        $file_path = get_attached_file($attachment_id);
        $filename = basename($file_path);
        
        if (!file_exists($file_path)) {
            $results['failed']++;
            $results['items'][] = [
                'id' => $attachment_id,
                'name' => $filename,
                'status' => 'failed',
                'message' => 'File not found',
            ];
            continue;
        }
        
        // Attempt to offload
        try {
            $bucket = $bucket_id ? nbs_get_bucket($bucket_id) : nbs_get_default_bucket();
            
            if (!$bucket) {
                throw new Exception('No bucket configured');
            }
            
            $s3 = nbs_get_s3_client($bucket);
            if (!$s3) {
                throw new Exception('Could not create S3 client');
            }
            
            $upload_dir = wp_upload_dir();
            $relative = str_replace($upload_dir['basedir'] . '/', '', $file_path);
            $key = $bucket['path_prefix'] ? $bucket['path_prefix'] . '/' . $relative : $relative;
            
            $s3->putObject([
                'Bucket' => $bucket['bucket_name'],
                'Key' => $key,
                'SourceFile' => $file_path,
                'ACL' => 'public-read',
            ]);
            
            update_post_meta($attachment_id, '_nbs_offloaded', 1);
            update_post_meta($attachment_id, '_nbs_bucket', $bucket['bucket_name']);
            update_post_meta($attachment_id, '_nbs_bucket_id', $bucket['id']);
            update_post_meta($attachment_id, '_nbs_key', $key);
            
            nbs_log_sync($attachment_id, $bucket['id'], 'manual_sync', 'success');
            
            $results['success']++;
            $results['items'][] = [
                'id' => $attachment_id,
                'name' => $filename,
                'status' => 'success',
                'message' => 'Synced successfully',
            ];
            
        } catch (Exception $e) {
            $results['failed']++;
            $results['items'][] = [
                'id' => $attachment_id,
                'name' => $filename,
                'status' => 'failed',
                'message' => $e->getMessage(),
            ];
            nbs_log_sync($attachment_id, $bucket_id, 'manual_sync', 'failed', $e->getMessage());
        }
    }
    
    wp_send_json_success($results);
});

add_action('wp_ajax_nbs_discover_bucket', function(){
    check_ajax_referer('nbs_ajax', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $bucket_id = isset($_POST['bucket_id']) ? intval($_POST['bucket_id']) : 0;
    $skip_existing = isset($_POST['skip_existing']) && $_POST['skip_existing'] === 'true';
    
    if (!$bucket_id) {
        wp_send_json_error(['message' => 'Bucket ID required']);
    }
    
    $bucket = nbs_get_bucket($bucket_id);
    if (!$bucket) {
        wp_send_json_error(['message' => 'Bucket not found']);
    }
    
    try {
        $s3 = nbs_get_s3_client($bucket);
        if (!$s3) {
            throw new Exception('Could not create S3 client');
        }
        
        $objects = $s3->listObjectsV2([
            'Bucket' => $bucket['bucket_name'],
            'Prefix' => $bucket['path_prefix'],
            'MaxKeys' => 100,
        ]);
        
        $results = [
            'found' => 0,
            'imported' => 0,
            'skipped' => 0,
            'items' => [],
        ];
        
        if (isset($objects['Contents'])) {
            foreach ($objects['Contents'] as $object) {
                $key = $object['Key'];
                $filename = basename($key);
                
                $results['found']++;
                
                // Check if already exists
                if ($skip_existing) {
                    $existing = get_posts([
                        'post_type' => 'attachment',
                        'meta_key' => '_nbs_key',
                        'meta_value' => $key,
                        'posts_per_page' => 1,
                    ]);
                    
                    if (!empty($existing)) {
                        $results['skipped']++;
                        continue;
                    }
                }
                
                // Create attachment record
                $attachment_id = wp_insert_attachment([
                    'post_title' => $filename,
                    'post_content' => '',
                    'post_status' => 'inherit',
                    'post_mime_type' => wp_check_filetype($filename)['type'],
                ]);
                
                if ($attachment_id) {
                    update_post_meta($attachment_id, '_nbs_offloaded', 1);
                    update_post_meta($attachment_id, '_nbs_bucket', $bucket['bucket_name']);
                    update_post_meta($attachment_id, '_nbs_bucket_id', $bucket['id']);
                    update_post_meta($attachment_id, '_nbs_key', $key);
                    update_post_meta($attachment_id, '_nbs_discovered', 1);
                    
                    $results['imported']++;
                    $results['items'][] = [
                        'id' => $attachment_id,
                        'name' => $filename,
                        'status' => 'imported',
                    ];
                    
                    nbs_log_sync($attachment_id, $bucket_id, 'discover', 'success');
                }
            }
        }
        
        // Update last sync time
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'nbs_buckets',
            ['last_sync' => current_time('mysql')],
            ['id' => $bucket_id]
        );
        
        wp_send_json_success($results);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => $e->getMessage()]);
    }
});

// AJAX handler for listing buckets
add_action('wp_ajax_nbs_list_buckets', function(){
    check_ajax_referer('nbs_ajax', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $connection = $_POST['connection'] ?? [];
    
    if (empty($connection['access_key']) || empty($connection['secret_key']) || 
        empty($connection['endpoint'])) {
        wp_send_json_error(['message' => 'Missing required connection parameters']);
    }
    
    try {
        $s3 = nbs_get_s3_client([
            'access_key' => $connection['access_key'],
            'secret_key' => $connection['secret_key'],
            'region' => $connection['region'] ?? 'us-east-1',
            'endpoint' => $connection['endpoint'],
            'use_path_style' => !empty($connection['use_path_style'])
        ]);
        
        if (!$s3) {
            throw new Exception('Failed to create S3 client');
        }
        
        // List all buckets
        $result = $s3->listBuckets();
        $buckets = [];
        
        if (isset($result['Buckets'])) {
            foreach ($result['Buckets'] as $bucket) {
                $buckets[] = $bucket['Name'];
            }
        }
        
        wp_send_json_success([
            'buckets' => $buckets,
            'count' => count($buckets)
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error listing buckets: ' . $e->getMessage()]);
    }
});

// AJAX handler for detecting region
add_action('wp_ajax_nbs_detect_region', function(){
    check_ajax_referer('nbs_ajax', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $connection = $_POST['connection'] ?? [];
    
    if (empty($connection['access_key']) || empty($connection['secret_key']) || 
        empty($connection['endpoint']) || empty($connection['bucket'])) {
        wp_send_json_error(['message' => 'Missing required connection parameters']);
    }
    
    try {
        // First try with provided region or default
        $s3 = nbs_get_s3_client([
            'access_key' => $connection['access_key'],
            'secret_key' => $connection['secret_key'],
            'region' => $connection['region'] ?? 'us-east-1',
            'endpoint' => $connection['endpoint'],
            'use_path_style' => !empty($connection['use_path_style'])
        ]);
        
        if (!$s3) {
            throw new Exception('Failed to create S3 client');
        }
        
        try {
            // Try to get bucket location
            $result = $s3->getBucketLocation([
                'Bucket' => $connection['bucket']
            ]);
            
            $region = $result['LocationConstraint'] ?? 'us-east-1';
            
            // AWS returns null for us-east-1
            if (empty($region) || $region === 'null') {
                $region = 'us-east-1';
            }
            
            wp_send_json_success([
                'region' => $region,
                'message' => 'Region detected successfully'
            ]);
            
        } catch (Aws\S3\Exception\S3Exception $e) {
            // If GetBucketLocation fails, fallback to default region
            // This is common for S3-compatible services like MinIO
            $region = $connection['region'] ?? 'us-east-1';
            
            wp_send_json_success([
                'region' => $region,
                'message' => 'Using default region (GetBucketLocation not supported)',
                'fallback' => true
            ]);
        }
        
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error detecting region: ' . $e->getMessage()]);
    }
});

// AJAX handler for connection testing
add_action('wp_ajax_nbs_test_connection', function(){
    check_ajax_referer('nbs_ajax', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Unauthorized']);
    }
    
    $connection = $_POST['connection'] ?? [];
    
    if (empty($connection['access_key']) || empty($connection['secret_key']) || 
        empty($connection['endpoint']) || empty($connection['bucket'])) {
        wp_send_json_error(['message' => 'Missing required connection parameters']);
    }
    
    try {
        $s3 = nbs_get_s3_client([
            'access_key' => $connection['access_key'],
            'secret_key' => $connection['secret_key'],
            'region' => $connection['region'] ?? 'us-east-1',
            'endpoint' => $connection['endpoint'],
            'use_path_style' => !empty($connection['use_path_style'])
        ]);
        
        if (!$s3) {
            throw new Exception('Failed to create S3 client');
        }
        
        // Test by checking if bucket exists
        $result = $s3->headBucket([
            'Bucket' => $connection['bucket']
        ]);
        
        wp_send_json_success([
            'message' => 'Connection successful! Bucket is accessible.',
            'bucket' => $connection['bucket']
        ]);
        
    } catch (Aws\S3\Exception\S3Exception $e) {
        $error = $e->getAwsErrorCode();
        $message = $e->getMessage();
        
        if ($error === 'NoSuchBucket') {
            wp_send_json_error(['message' => 'Bucket does not exist']);
        } elseif ($error === 'AccessDenied' || $error === '403') {
            wp_send_json_error(['message' => 'Access denied - check credentials and bucket permissions']);
        } elseif ($error === 'InvalidAccessKeyId') {
            wp_send_json_error(['message' => 'Invalid access key']);
        } elseif ($error === 'SignatureDoesNotMatch') {
            wp_send_json_error(['message' => 'Invalid secret key']);
        } else {
            wp_send_json_error(['message' => 'Connection error: ' . $message]);
        }
    } catch (Exception $e) {
        wp_send_json_error(['message' => 'Error: ' . $e->getMessage()]);
    }
});

// ===== Enqueue admin assets =====
add_action('admin_enqueue_scripts', function($hook){
    if (strpos($hook, 'neobiz-storage') === false && $hook !== 'upload.php') {
        return;
    }
    
    wp_enqueue_style('nbs-admin', NBS_PLUGIN_URL . 'assets/admin.css', [], NBS_VERSION);
    wp_enqueue_script('nbs-admin', NBS_PLUGIN_URL . 'assets/admin.js', ['jquery'], NBS_VERSION, true);
    
    wp_localize_script('nbs-admin', 'nbsAdmin', [
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('nbs_ajax'),
        'buckets' => nbs_get_buckets(true),
    ]);
});

// ===== Create assets on activation =====
function nbs_create_assets(){
    $assets_dir = NBS_PLUGIN_PATH . 'assets';
    if (!file_exists($assets_dir)) {
        mkdir($assets_dir, 0755, true);
    }
    
    // Create CSS file
    $css_file = $assets_dir . '/admin.css';
    if (!file_exists($css_file)) {
        $css = file_get_contents(NBS_PLUGIN_PATH . 'templates/admin.css');
        file_put_contents($css_file, $css);
    }
    
    // Create JS file
    $js_file = $assets_dir . '/admin.js';
    if (!file_exists($js_file)) {
        $js = file_get_contents(NBS_PLUGIN_PATH . 'templates/admin.js');
        file_put_contents($js_file, $js);
    }
}

// ===== Load Media Library Tabs =====
if (file_exists(NBS_PLUGIN_PATH . 'media-library-tabs.php')) {
    require_once NBS_PLUGIN_PATH . 'media-library-tabs.php';
}
