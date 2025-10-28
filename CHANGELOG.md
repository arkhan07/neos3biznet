# Changelog

All notable changes to NeoBiz Storage Enhanced will be documented in this file.

## [0.4.0] - 2024-01-XX

### 🎉 Major Release - Complete Redesign

#### Added
- **Modern Dashboard UI**
  - Statistics cards dengan visualisasi menarik
  - Quick actions buttons
  - Real-time bucket overview
  - Status indicators untuk setiap metric

- **Multi-Bucket Management System**
  - Support multiple S3/MinIO buckets sekaligus
  - Per-bucket configuration (credentials, region, endpoint, CDN)
  - Set default bucket untuk auto-upload
  - Active/inactive toggle per bucket
  - Auto-sync enable per bucket
  - Last sync timestamp tracking

- **Advanced Sync Manager**
  - Manual sync dengan progress bar real-time
  - Batch processing dengan configurable batch size
  - Force re-sync option untuk files yang sudah di-upload
  - Delete local files after sync option
  - Detailed logs dengan timestamp
  - Success/failed counters
  - Stop/abort sync functionality

- **Auto Discovery Feature**
  - Scan bucket dan detect existing files
  - Import files ke WordPress media library
  - Skip existing files option
  - Batch import dengan progress tracking
  - Automatic metadata creation
  - Support untuk files yang di-upload manual ke bucket

- **Media Library Enhancements**
  - Bucket tabs di list view
  - Bucket column showing storage location
  - Bucket filter dropdown
  - Single attachment sync button
  - Bucket selector saat edit attachment
  - Re-sync ke bucket berbeda
  - Visual indicators (Local/Cloud badges)

- **Enhanced Settings Page**
  - Tab-based navigation (General, Sync Options, Advanced)
  - Modern toggle switches
  - Better form layout dengan grid system
  - Real-time validation feedback
  - Help text untuk setiap setting

- **Database Improvements**
  - New table: `wp_nbs_buckets` untuk multi-bucket config
  - New table: `wp_nbs_sync_log` untuk activity logging
  - Additional metadata fields (last_sync, is_active, auto_sync)
  - Better indexing untuk performance

- **AJAX Operations**
  - Non-blocking sync operations
  - Real-time progress updates
  - Better error handling
  - User-friendly notifications

#### Changed
- Complete UI redesign dengan modern CSS
- Improved form validation
- Better error messages
- Enhanced security dengan nonce verification
- Optimized database queries
- Better code organization

#### Improved
- Performance optimization untuk large media libraries
- Better memory management
- Faster batch processing
- Improved error recovery
- Better logging system

#### Developer Features
- Clean code structure
- Comprehensive documentation
- Hook system untuk extensions
- Filter untuk custom modifications
- Better debugging capabilities

### Technical Details

#### New Files
- `neobiz-storage-enhanced.php` - Main plugin file
- `media-library-tabs.php` - Media library enhancements
- `templates/admin.css` - Modern styling
- `templates/admin.js` - AJAX handlers
- `README-ENHANCED.md` - Complete documentation

#### Database Schema
```sql
-- Buckets table
CREATE TABLE wp_nbs_buckets (
    id bigint(20) AUTO_INCREMENT PRIMARY KEY,
    bucket_name varchar(255) NOT NULL UNIQUE,
    bucket_label varchar(255) NOT NULL,
    provider varchar(50) DEFAULT 'custom',
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
    created_at datetime DEFAULT CURRENT_TIMESTAMP
);

-- Sync log table
CREATE TABLE wp_nbs_sync_log (
    id bigint(20) AUTO_INCREMENT PRIMARY KEY,
    attachment_id bigint(20) NOT NULL,
    bucket_id bigint(20) NOT NULL,
    action varchar(50) NOT NULL,
    status varchar(20) NOT NULL,
    message text,
    synced_at datetime DEFAULT CURRENT_TIMESTAMP,
    KEY attachment_id (attachment_id),
    KEY bucket_id (bucket_id)
);
```

#### AJAX Actions
- `nbs_sync_attachments` - Manual batch sync
- `nbs_discover_bucket` - Auto discover files
- `nbs_check_bucket` - Check attachment bucket
- `nbs_sync_single_attachment` - Sync single file

#### Filters & Hooks
- `nbs_before_upload` - Before file upload
- `nbs_after_upload` - After successful upload
- `nbs_sync_batch_size` - Modify batch size
- `nbs_bucket_url` - Customize bucket URLs
- `nbs_presigned_ttl` - Modify presigned URL TTL

---

## [0.3.0] - Previous Version

### Added
- Basic multi-bucket support
- Simple sync functionality
- Bucket auto-discovery
- MinIO preset

### Changed
- Improved credential management
- Better error handling

---

## [0.2.1] - Initial Version

### Added
- Basic S3 upload functionality
- URL rewriting
- Private bucket support
- Cache control headers
- WP-CLI commands

---

## Upgrade Guide

### From 0.2.x to 0.4.0

1. **Backup Database**
   ```bash
   wp db export backup-before-upgrade.sql
   ```

2. **Update Plugin**
   ```bash
   cd wp-content/plugins/neobiz-storage
   git pull origin main
   composer update
   ```

3. **Run Migration** (automatic on activation)
   - New tables will be created
   - Existing settings will be migrated
   - No data loss

4. **Configure Buckets**
   - Go to NeoBiz Storage > Buckets
   - Add your existing bucket configuration
   - Set as default
   - Test sync

5. **Optional: Run Auto Discover**
   - Go to Sync Manager
   - Select bucket
   - Click "Start Discovery"
   - Import existing files

### Breaking Changes
None. Fully backward compatible.

---

## Roadmap

### v0.5.0 (Q1 2025)
- [ ] Bucket tabs dalam media uploader modal
- [ ] Drag & drop upload ke specific bucket
- [ ] Bulk operations di media library
- [ ] Advanced filtering options
- [ ] CDN purge integration
- [ ] Migration wizard (bucket to bucket)

### v0.6.0 (Q2 2025)
- [ ] Multi-site support
- [ ] Advanced analytics dashboard
- [ ] Storage usage graphs
- [ ] Cost calculator
- [ ] Backup & restore system
- [ ] Custom object metadata

### v1.0.0 (Q3 2025)
- [ ] Image optimization pre-upload
- [ ] Video transcoding integration
- [ ] Advanced caching strategies
- [ ] Custom CDN providers
- [ ] Enterprise features
- [ ] SLA monitoring

---

## Support & Feedback

Found a bug? Have a feature request?

- GitHub Issues: [Create Issue]
- Documentation: [Wiki]
- Support Forum: [WordPress.org]
- Email: support@neobiz.id

## Contributing

We welcome contributions! Please see CONTRIBUTING.md for details.

## License

GPL-2.0-or-later - See LICENSE file for details.
