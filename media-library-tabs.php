<?php
/**
 * Media Library Bucket Tabs
 * Add bucket tabs to WordPress media library
 */

if (!defined('ABSPATH')) exit;

// Add bucket tabs to media library
add_action('admin_footer-upload.php', function(){
    $s = nbs_get_settings();
    
    if (!$s['enabled'] || !$s['multi_bucket_enabled']) {
        return;
    }
    
    $buckets = nbs_get_buckets(true);
    
    if (empty($buckets)) {
        return;
    }
    
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Create bucket tabs
        var $mediaFrame = $('.media-frame');
        if ($mediaFrame.length === 0) return;
        
        var buckets = <?php echo json_encode($buckets); ?>;
        var $tabsContainer = $('<div class="nbs-bucket-tabs"></div>');
        
        // Add "All" tab
        var $allTab = $('<button class="nbs-bucket-tab active" data-bucket="">All Files</button>');
        $tabsContainer.append($allTab);
        
        // Add bucket tabs
        buckets.forEach(function(bucket) {
            var $tab = $('<button class="nbs-bucket-tab" data-bucket="' + bucket.id + '">' + 
                         bucket.bucket_label + 
                         '</button>');
            $tabsContainer.append($tab);
        });
        
        // Insert tabs before media library
        $('.attachments-browser .media-toolbar').first().before($tabsContainer);
        
        // Handle tab clicks
        $('.nbs-bucket-tab').on('click', function() {
            $('.nbs-bucket-tab').removeClass('active');
            $(this).addClass('active');
            
            var bucketId = $(this).data('bucket');
            filterByBucket(bucketId);
        });
        
        // Filter function
        function filterByBucket(bucketId) {
            var $attachments = $('.attachments .attachment');
            
            if (!bucketId) {
                // Show all
                $attachments.show();
            } else {
                // Filter by bucket
                $attachments.each(function() {
                    var attachmentId = $(this).data('id');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        async: false,
                        data: {
                            action: 'nbs_check_bucket',
                            nonce: '<?php echo wp_create_nonce('nbs_ajax'); ?>',
                            attachment_id: attachmentId,
                            bucket_id: bucketId
                        },
                        success: function(response) {
                            if (response.success && response.data.match) {
                                $(this).show();
                            } else {
                                $(this).hide();
                            }
                        }.bind(this)
                    });
                });
            }
        }
    });
    </script>
    
    <style>
    .nbs-bucket-tabs {
        display: flex;
        gap: 5px;
        margin-bottom: 15px;
        padding: 10px;
        background: #f0f0f1;
        border-bottom: 1px solid #dcdcde;
    }
    
    .nbs-bucket-tab {
        padding: 8px 16px;
        background: white;
        border: 1px solid #dcdcde;
        border-radius: 4px;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        color: #50575e;
        transition: all 0.3s ease;
    }
    
    .nbs-bucket-tab:hover {
        background: #f6f7f7;
        border-color: #2271b1;
        color: #2271b1;
    }
    
    .nbs-bucket-tab.active {
        background: #2271b1;
        border-color: #2271b1;
        color: white;
    }
    </style>
    <?php
});

// AJAX handler to check if attachment belongs to bucket
add_action('wp_ajax_nbs_check_bucket', function(){
    check_ajax_referer('nbs_ajax', 'nonce');
    
    $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
    $bucket_id = isset($_POST['bucket_id']) ? intval($_POST['bucket_id']) : 0;
    
    if (!$attachment_id || !$bucket_id) {
        wp_send_json_error(['message' => 'Invalid parameters']);
    }
    
    $attachment_bucket_id = get_post_meta($attachment_id, '_nbs_bucket_id', true);
    
    wp_send_json_success([
        'match' => $attachment_bucket_id == $bucket_id
    ]);
});

// Add bucket selection when uploading via media modal
add_filter('attachment_fields_to_edit', function($form_fields, $post){
    $s = nbs_get_settings();
    
    if (!$s['enabled'] || !$s['multi_bucket_enabled']) {
        return $form_fields;
    }
    
    $buckets = nbs_get_buckets(true);
    $current_bucket = get_post_meta($post->ID, '_nbs_bucket_id', true);
    
    $options = '<option value="">Select Bucket</option>';
    foreach ($buckets as $bucket) {
        $selected = $current_bucket == $bucket['id'] ? 'selected' : '';
        $options .= sprintf(
            '<option value="%d" %s>%s</option>',
            $bucket['id'],
            $selected,
            esc_html($bucket['bucket_label'])
        );
    }
    
    $form_fields['nbs_bucket'] = [
        'label' => 'Storage Bucket',
        'input' => 'html',
        'html' => sprintf(
            '<select name="attachments[%d][nbs_bucket]">%s</select>
            <p class="description">Change bucket for this attachment</p>',
            $post->ID,
            $options
        ),
    ];
    
    return $form_fields;
}, 10, 2);

// Save bucket selection
add_filter('attachment_fields_to_save', function($post, $attachment){
    if (isset($attachment['nbs_bucket']) && !empty($attachment['nbs_bucket'])) {
        $bucket_id = intval($attachment['nbs_bucket']);
        $current_bucket = get_post_meta($post['ID'], '_nbs_bucket_id', true);
        
        if ($bucket_id != $current_bucket) {
            // Re-sync to new bucket
            $bucket = nbs_get_bucket($bucket_id);
            if ($bucket) {
                try {
                    $file_path = get_attached_file($post['ID']);
                    if (file_exists($file_path)) {
                        $s3 = nbs_get_s3_client($bucket);
                        if ($s3) {
                            $upload_dir = wp_upload_dir();
                            $relative = str_replace($upload_dir['basedir'] . '/', '', $file_path);
                            $key = $bucket['path_prefix'] ? $bucket['path_prefix'] . '/' . $relative : $relative;
                            
                            $s3->putObject([
                                'Bucket' => $bucket['bucket_name'],
                                'Key' => $key,
                                'SourceFile' => $file_path,
                                'ACL' => 'public-read',
                            ]);
                            
                            update_post_meta($post['ID'], '_nbs_bucket', $bucket['bucket_name']);
                            update_post_meta($post['ID'], '_nbs_bucket_id', $bucket['id']);
                            update_post_meta($post['ID'], '_nbs_key', $key);
                            
                            nbs_log_sync($post['ID'], $bucket['id'], 'bucket_change', 'success');
                        }
                    }
                } catch (Exception $e) {
                    error_log('NBS Bucket Change Error: ' . $e->getMessage());
                }
            }
        }
    }
    
    return $post;
}, 10, 2);

// Add bucket column to media library list view
add_filter('manage_media_columns', function($columns){
    $s = nbs_get_settings();
    
    if (!$s['enabled'] || !$s['multi_bucket_enabled']) {
        return $columns;
    }
    
    $new_columns = [];
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        
        if ($key === 'author') {
            $new_columns['nbs_bucket'] = 'Storage Bucket';
        }
    }
    
    return $new_columns;
});

add_action('manage_media_custom_column', function($column_name, $post_id){
    if ($column_name === 'nbs_bucket') {
        $offloaded = get_post_meta($post_id, '_nbs_offloaded', true);
        
        if ($offloaded) {
            $bucket_id = get_post_meta($post_id, '_nbs_bucket_id', true);
            if ($bucket_id) {
                $bucket = nbs_get_bucket($bucket_id);
                if ($bucket) {
                    echo '<span class="nbs-badge nbs-badge-bucket" style="background: #2271b1; color: white; padding: 4px 8px; border-radius: 4px; font-size: 11px;">';
                    echo esc_html($bucket['bucket_label']);
                    echo '</span>';
                    return;
                }
            }
        }
        
        echo '<span style="color: #999;">Local</span>';
    }
}, 10, 2);

// Add bucket filter dropdown
add_action('restrict_manage_posts', function(){
    $s = nbs_get_settings();
    
    if (!$s['enabled'] || !$s['multi_bucket_enabled']) {
        return;
    }
    
    if (get_current_screen()->id !== 'upload') {
        return;
    }
    
    $buckets = nbs_get_buckets(true);
    $current_bucket = isset($_GET['nbs_bucket_filter']) ? intval($_GET['nbs_bucket_filter']) : 0;
    
    ?>
    <select name="nbs_bucket_filter">
        <option value="">All Buckets</option>
        <option value="local" <?php selected($current_bucket, 'local'); ?>>Local Only</option>
        <?php foreach ($buckets as $bucket): ?>
            <option value="<?php echo $bucket['id']; ?>" <?php selected($current_bucket, $bucket['id']); ?>>
                <?php echo esc_html($bucket['bucket_label']); ?>
            </option>
        <?php endforeach; ?>
    </select>
    <?php
});

// Apply bucket filter
add_action('pre_get_posts', function($query){
    if (!is_admin() || !$query->is_main_query()) {
        return;
    }
    
    if (get_current_screen()->id !== 'upload') {
        return;
    }
    
    if (!isset($_GET['nbs_bucket_filter']) || empty($_GET['nbs_bucket_filter'])) {
        return;
    }
    
    $filter = $_GET['nbs_bucket_filter'];
    
    if ($filter === 'local') {
        $query->set('meta_query', [
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
        ]);
    } else {
        $bucket_id = intval($filter);
        $query->set('meta_query', [
            [
                'key' => '_nbs_bucket_id',
                'value' => $bucket_id,
                'compare' => '=',
            ],
        ]);
    }
});

// Add sync button to media row actions
add_filter('media_row_actions', function($actions, $post){
    $s = nbs_get_settings();
    
    if (!$s['enabled']) {
        return $actions;
    }
    
    $buckets = nbs_get_buckets(true);
    if (empty($buckets)) {
        return $actions;
    }
    
    $offloaded = get_post_meta($post->ID, '_nbs_offloaded', true);
    
    if (!$offloaded) {
        $actions['nbs_sync'] = sprintf(
            '<a href="#" class="nbs-sync-single" data-attachment-id="%d">Sync to Cloud</a>',
            $post->ID
        );
    } else {
        $actions['nbs_resync'] = sprintf(
            '<a href="#" class="nbs-sync-single" data-attachment-id="%d">Re-sync</a>',
            $post->ID
        );
    }
    
    return $actions;
}, 10, 2);

// Add inline script for single attachment sync
add_action('admin_footer-upload.php', function(){
    ?>
    <script type="text/javascript">
    jQuery(document).ready(function($) {
        $(document).on('click', '.nbs-sync-single', function(e) {
            e.preventDefault();
            
            var $link = $(this);
            var attachmentId = $link.data('attachment-id');
            var originalText = $link.text();
            
            $link.text('Syncing...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'nbs_sync_single_attachment',
                    nonce: '<?php echo wp_create_nonce('nbs_ajax'); ?>',
                    attachment_id: attachmentId
                },
                success: function(response) {
                    if (response.success) {
                        $link.text('✓ Synced').css('color', 'green');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $link.text('✗ Failed').css('color', 'red');
                        alert('Sync failed: ' + response.data.message);
                    }
                },
                error: function() {
                    $link.text(originalText);
                    alert('Sync failed');
                }
            });
        });
    });
    </script>
    <?php
});

// AJAX handler for single attachment sync
add_action('wp_ajax_nbs_sync_single_attachment', function(){
    check_ajax_referer('nbs_ajax', 'nonce');
    
    $attachment_id = isset($_POST['attachment_id']) ? intval($_POST['attachment_id']) : 0;
    
    if (!$attachment_id) {
        wp_send_json_error(['message' => 'Invalid attachment ID']);
    }
    
    $bucket = nbs_get_default_bucket();
    if (!$bucket) {
        wp_send_json_error(['message' => 'No default bucket configured']);
    }
    
    try {
        $s3 = nbs_get_s3_client($bucket);
        if (!$s3) {
            throw new Exception('Could not create S3 client');
        }
        
        $file_path = get_attached_file($attachment_id);
        if (!file_exists($file_path)) {
            throw new Exception('File not found');
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
        
        nbs_log_sync($attachment_id, $bucket['id'], 'single_sync', 'success');
        
        wp_send_json_success(['message' => 'Synced successfully']);
        
    } catch (Exception $e) {
        nbs_log_sync($attachment_id, $bucket['id'] ?? 0, 'single_sync', 'failed', $e->getMessage());
        wp_send_json_error(['message' => $e->getMessage()]);
    }
});
