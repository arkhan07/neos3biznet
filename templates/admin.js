/* NeoBiz Storage Enhanced - Admin JavaScript */

(function($) {
    'use strict';
    
    const NBS = {
        syncRunning: false,
        syncAborted: false,
        
        init: function() {
            this.initTabs();
            this.initSync();
            this.initDiscover();
            this.initBucketSync();
            this.initProviderPresets();
            this.initConnectionTest();
        },
        
        // Provider presets for quick configuration
        initProviderPresets: function() {
            const presets = {
                'aws': {
                    endpoint: 'https://s3.amazonaws.com',
                    region: 'us-east-1',
                    pathStyle: false,
                    sslVerify: true
                },
                'do': {
                    endpoint: 'https://nyc3.digitaloceanspaces.com',
                    region: 'nyc3',
                    pathStyle: false,
                    sslVerify: true
                },
                'wasabi': {
                    endpoint: 'https://s3.wasabisys.com',
                    region: 'us-east-1',
                    pathStyle: false,
                    sslVerify: true
                },
                'backblaze': {
                    endpoint: 'https://s3.us-west-001.backblazeb2.com',
                    region: 'us-west-001',
                    pathStyle: false,
                    sslVerify: true
                },
                'custom': {
                    endpoint: 'http://localhost:9000',
                    region: 'us-east-1',
                    pathStyle: true,
                    sslVerify: false
                }
            };
            
            $('#nbs-provider').on('change', function() {
                const provider = $(this).val();
                const preset = presets[provider];
                
                if (preset && confirm('Auto-fill recommended settings for ' + $(this).find('option:selected').text() + '?')) {
                    $('#nbs-endpoint').val(preset.endpoint);
                    $('#nbs-region').val(preset.region);
                    
                    // Set path style checkbox
                    const $pathStyle = $('input[name="nbs_settings[use_path_style]"]');
                    $pathStyle.prop('checked', preset.pathStyle);
                    
                    // Set SSL verify
                    const $sslVerify = $('input[name="nbs_settings[disable_ssl_verify]"]');
                    $sslVerify.prop('checked', !preset.sslVerify);
                    
                    // Show notification
                    const providerName = $(this).find('option:selected').text();
                    NBS.showNotice('Preset values applied for ' + providerName + '. Please verify and adjust as needed.', 'info');
                }
            });
        },
        
        // Test connection to S3/MinIO
        initConnectionTest: function() {
            const self = this;
            
            // Test Connection button
            $(document).on('click', '#nbs-test-connection', function() {
                const $btn = $(this);
                const originalHtml = $btn.html();
                
                const connectionData = {
                    provider: $('#nbs-provider').val(),
                    access_key: $('#nbs-access-key').val(),
                    secret_key: $('#nbs-secret-key').val(),
                    region: $('#nbs-region').val(),
                    endpoint: $('#nbs-endpoint').val(),
                    bucket: $('#nbs-bucket').val(),
                    use_path_style: $('input[name="nbs_settings[use_path_style]"]').is(':checked')
                };
                
                if (!connectionData.access_key || !connectionData.secret_key || 
                    !connectionData.region || !connectionData.endpoint || !connectionData.bucket) {
                    alert('Please fill in all required connection fields first.');
                    return;
                }
                
                $btn.prop('disabled', true)
                    .html('<span class="dashicons dashicons-update nbs-spinning"></span> Testing...');
                
                $.ajax({
                    url: nbsAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'nbs_test_connection',
                        nonce: nbsAdmin.nonce,
                        connection: connectionData
                    },
                    success: function(response) {
                        if (response.success) {
                            self.showNotice('✓ Connection successful! Bucket is accessible.', 'success');
                            $btn.html('<span class="dashicons dashicons-yes"></span> Connected!').css('color', 'green');
                        } else {
                            self.showNotice('✗ Connection failed: ' + response.data.message, 'error');
                            $btn.html('<span class="dashicons dashicons-no"></span> Failed').css('color', 'red');
                        }
                        
                        setTimeout(function() {
                            $btn.html(originalHtml).css('color', '').prop('disabled', false);
                        }, 3000);
                    },
                    error: function() {
                        self.showNotice('Connection test failed', 'error');
                        $btn.html(originalHtml).prop('disabled', false);
                    }
                });
            });
            
            // List Buckets button
            $(document).on('click', '#nbs-list-buckets', function() {
                const $btn = $(this);
                const originalHtml = $btn.html();
                
                const connectionData = {
                    provider: $('#nbs-provider').val(),
                    access_key: $('#nbs-access-key').val(),
                    secret_key: $('#nbs-secret-key').val(),
                    region: $('#nbs-region').val(),
                    endpoint: $('#nbs-endpoint').val(),
                    use_path_style: $('input[name="nbs_settings[use_path_style]"]').is(':checked')
                };
                
                if (!connectionData.access_key || !connectionData.secret_key || 
                    !connectionData.region || !connectionData.endpoint) {
                    alert('Please fill in Access Key, Secret Key, Region, and Endpoint first.');
                    return;
                }
                
                $btn.prop('disabled', true)
                    .html('<span class="dashicons dashicons-update nbs-spinning"></span> Loading...');
                
                $.ajax({
                    url: nbsAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'nbs_list_buckets',
                        nonce: nbsAdmin.nonce,
                        connection: connectionData
                    },
                    success: function(response) {
                        if (response.success && response.data.buckets.length > 0) {
                            const buckets = response.data.buckets;
                            let html = '<div class="nbs-buckets-dropdown" style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-radius: 4px;">';
                            html += '<strong>Available Buckets (click to select):</strong><br>';
                            html += '<div style="margin-top: 8px;">';
                            
                            buckets.forEach(function(bucket) {
                                html += '<button type="button" class="button button-small nbs-select-bucket" data-bucket="' + bucket + '" style="margin: 3px;">' + bucket + '</button>';
                            });
                            
                            html += '</div></div>';
                            
                            $('#nbs-buckets-list').html(html).slideDown();
                            self.showNotice('Found ' + buckets.length + ' bucket(s). Click to select.', 'success');
                        } else {
                            self.showNotice('No buckets found or unable to list buckets.', 'warning');
                            $('#nbs-buckets-list').hide();
                        }
                        
                        $btn.html(originalHtml).prop('disabled', false);
                    },
                    error: function() {
                        self.showNotice('Failed to list buckets', 'error');
                        $btn.html(originalHtml).prop('disabled', false);
                    }
                });
            });
            
            // Select bucket from list
            $(document).on('click', '.nbs-select-bucket', function() {
                const bucketName = $(this).data('bucket');
                $('#nbs-bucket').val(bucketName);
                $('#nbs-buckets-list').slideUp();
                self.showNotice('Bucket selected: ' + bucketName, 'success');
            });
            
            // Detect Region button
            $(document).on('click', '#nbs-detect-region', function() {
                const $btn = $(this);
                const originalHtml = $btn.html();
                
                const connectionData = {
                    provider: $('#nbs-provider').val(),
                    access_key: $('#nbs-access-key').val(),
                    secret_key: $('#nbs-secret-key').val(),
                    region: $('#nbs-region').val() || 'us-east-1',
                    endpoint: $('#nbs-endpoint').val(),
                    bucket: $('#nbs-bucket').val(),
                    use_path_style: $('input[name="nbs_settings[use_path_style]"]').is(':checked')
                };
                
                if (!connectionData.access_key || !connectionData.secret_key || 
                    !connectionData.endpoint || !connectionData.bucket) {
                    alert('Please fill in credentials, endpoint, and bucket name first.');
                    return;
                }
                
                $btn.prop('disabled', true)
                    .html('<span class="dashicons dashicons-update nbs-spinning"></span> Detecting...');
                
                $.ajax({
                    url: nbsAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'nbs_detect_region',
                        nonce: nbsAdmin.nonce,
                        connection: connectionData
                    },
                    success: function(response) {
                        if (response.success) {
                            const region = response.data.region;
                            $('#nbs-region').val(region);
                            self.showNotice('✓ Region detected: ' + region, 'success');
                            $btn.html('<span class="dashicons dashicons-yes"></span> Detected!').css('color', 'green');
                        } else {
                            self.showNotice('Could not detect region: ' + response.data.message, 'warning');
                            $btn.html(originalHtml).css('color', 'orange');
                        }
                        
                        setTimeout(function() {
                            $btn.html(originalHtml).css('color', '').prop('disabled', false);
                        }, 3000);
                    },
                    error: function() {
                        self.showNotice('Failed to detect region', 'error');
                        $btn.html(originalHtml).prop('disabled', false);
                    }
                });
            });
        },
        
        // Tab switching
        initTabs: function() {
            $('.nbs-tab-btn').on('click', function() {
                const tab = $(this).data('tab');
                
                $('.nbs-tab-btn').removeClass('active');
                $(this).addClass('active');
                
                $('.nbs-tab-content').removeClass('active');
                $('#tab-' + tab).addClass('active');
            });
        },
        
        // Manual Sync functionality
        initSync: function() {
            const self = this;
            
            $('#nbs-start-sync').on('click', function() {
                if (self.syncRunning) return;
                
                const bucketId = $('#nbs-sync-bucket-select').val();
                const force = $('#nbs-force-resync').is(':checked');
                const deleteLocal = $('#nbs-delete-local').is(':checked');
                
                self.syncRunning = true;
                self.syncAborted = false;
                
                $('#nbs-sync-progress').show();
                $('#nbs-progress-title').text('Syncing attachments...');
                $('#nbs-progress-log').empty();
                $('#nbs-progress-success').text('0');
                $('#nbs-progress-failed').text('0');
                $('#nbs-stop-sync').show().prop('disabled', false).text('Stop');
                
                self.runSync(bucketId, force, deleteLocal, 0);
            });
            
            $('#nbs-stop-sync').on('click', function() {
                self.syncAborted = true;
                self.syncRunning = false;
                $(this).prop('disabled', true).text('Stopping...');
            });
        },
        
        runSync: function(bucketId, force, deleteLocal, offset) {
            const self = this;
            
            if (this.syncAborted) {
                this.logSync('Sync aborted by user', 'log-error');
                $('#nbs-stop-sync').text('Stop');
                return;
            }
            
            $.ajax({
                url: nbsAdmin.ajaxurl,
                type: 'POST',
                data: {
                    action: 'nbs_sync_attachments',
                    nonce: nbsAdmin.nonce,
                    bucket_id: bucketId,
                    force: force,
                    offset: offset
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        // Update progress
                        const progress = data.total > 0 ? (data.processed / data.total * 100).toFixed(1) : 100;
                        $('#nbs-progress-fill').css('width', progress + '%');
                        $('#nbs-progress-text').text(progress + '%');
                        $('#nbs-progress-files').text(data.processed + ' / ' + data.total);
                        
                        // Update stats
                        const currentSuccess = parseInt($('#nbs-progress-success').text() || '0') + data.success;
                        const currentFailed = parseInt($('#nbs-progress-failed').text() || '0') + data.failed;
                        $('#nbs-progress-success').text(currentSuccess);
                        $('#nbs-progress-failed').text(currentFailed);
                        
                        // Log items
                        data.items.forEach(function(item) {
                            const logClass = item.status === 'success' ? 'log-success' : 'log-error';
                            const icon = item.status === 'success' ? '✓' : '✗';
                            self.logSync(icon + ' ' + item.name + ' - ' + item.message, logClass);
                        });
                        
                        // Continue or finish
                        if (data.processed < data.total && !self.syncAborted) {
                            setTimeout(function() {
                                self.runSync(bucketId, force, deleteLocal, data.processed);
                            }, 500);
                        } else {
                            self.finishSync(currentSuccess, currentFailed);
                        }
                    } else {
                        self.logSync('Error: ' + (response.data.message || 'Unknown error'), 'log-error');
                        self.finishSync(0, 0);
                    }
                },
                error: function(xhr, status, error) {
                    self.logSync('AJAX error: ' + error, 'log-error');
                    self.finishSync(0, 0);
                }
            });
        },
        
        finishSync: function(success, failed) {
            $('#nbs-progress-title').text('Sync completed!');
            $('#nbs-stop-sync').hide();
            
            const message = `Sync completed: ${success} succeeded, ${failed} failed`;
            this.logSync(message, success > failed ? 'log-success' : 'log-error');
            
            // Show notification
            this.showNotice(message, success > failed ? 'success' : 'warning');
            
            // Reset sync state
            this.syncRunning = false;
            this.syncAborted = false;
        },
        
        logSync: function(message, className) {
            const $log = $('#nbs-progress-log');
            const timestamp = new Date().toLocaleTimeString();
            $log.append(
                $('<div>')
                    .addClass('log-entry')
                    .addClass(className || '')
                    .text('[' + timestamp + '] ' + message)
            );
            $log.scrollTop($log[0].scrollHeight);
        },
        
        // Auto Discover functionality
        initDiscover: function() {
            const self = this;
            
            $('#nbs-start-discover').on('click', function() {
                const bucketId = $('#nbs-discover-bucket-select').val();
                const skipExisting = $('#nbs-discover-skip-existing').is(':checked');
                
                if (!bucketId) {
                    alert('Please select a bucket');
                    return;
                }
                
                $(this).prop('disabled', true);
                const $btn = $(this);
                const originalText = $btn.html();
                $btn.html('<span class="dashicons dashicons-update nbs-spinning"></span> Discovering...');
                
                $('#nbs-sync-progress').show();
                $('#nbs-progress-title').text('Discovering files in bucket...');
                $('#nbs-progress-log').empty();
                self.logSync('Starting discovery...', 'log-success');
                
                $.ajax({
                    url: nbsAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'nbs_discover_bucket',
                        nonce: nbsAdmin.nonce,
                        bucket_id: bucketId,
                        skip_existing: skipExisting
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            
                            $('#nbs-progress-fill').css('width', '100%');
                            $('#nbs-progress-text').text('100%');
                            $('#nbs-progress-success').text(data.imported);
                            $('#nbs-progress-failed').text(0);
                            
                            self.logSync(`Found ${data.found} files`, 'log-success');
                            self.logSync(`Imported ${data.imported} new files`, 'log-success');
                            self.logSync(`Skipped ${data.skipped} existing files`, 'log-success');
                            
                            // Log imported items
                            data.items.forEach(function(item) {
                                self.logSync('✓ Imported: ' + item.name, 'log-success');
                            });
                            
                            const message = `Discovery completed: ${data.imported} files imported`;
                            self.showNotice(message, 'success');
                            
                            $('#nbs-progress-title').text('Discovery completed!');
                        } else {
                            self.logSync('Error: ' + response.data.message, 'log-error');
                            self.showNotice('Discovery failed: ' + response.data.message, 'error');
                        }
                        
                        $btn.html(originalText).prop('disabled', false);
                    },
                    error: function() {
                        self.logSync('AJAX error occurred', 'log-error');
                        self.showNotice('Discovery failed', 'error');
                        $btn.html(originalText).prop('disabled', false);
                    }
                });
            });
        },
        
        // Bucket sync button on dashboard
        initBucketSync: function() {
            const self = this;
            
            $('.nbs-sync-bucket-btn').on('click', function() {
                const bucketId = $(this).data('bucket-id');
                const $btn = $(this);
                const originalHtml = $btn.html();
                
                $btn.prop('disabled', true)
                    .html('<span class="dashicons dashicons-update nbs-spinning"></span> Syncing...');
                
                $.ajax({
                    url: nbsAdmin.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'nbs_sync_attachments',
                        nonce: nbsAdmin.nonce,
                        bucket_id: bucketId,
                        force: false,
                        offset: 0
                    },
                    success: function(response) {
                        if (response.success) {
                            const data = response.data;
                            self.showNotice(
                                `Synced ${data.success} files successfully`, 
                                'success'
                            );
                        } else {
                            self.showNotice(
                                'Sync failed: ' + response.data.message, 
                                'error'
                            );
                        }
                        $btn.html(originalHtml).prop('disabled', false);
                    },
                    error: function() {
                        self.showNotice('Sync failed', 'error');
                        $btn.html(originalHtml).prop('disabled', false);
                    }
                });
            });
        },
        
        // Show admin notice
        showNotice: function(message, type) {
            const noticeClass = 'notice-' + (type || 'info');
            const $notice = $('<div>')
                .addClass('notice ' + noticeClass + ' is-dismissible')
                .append($('<p>').text(message));
            
            $('.wrap h1').first().after($notice);
            
            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };
    
    // Initialize when document is ready
    $(document).ready(function() {
        NBS.init();
    });
    
})(jQuery);
