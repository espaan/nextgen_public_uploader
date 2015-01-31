<?php
/*
Is there any way we can handle the uploading more gracefully?
Does NGG rely on not uploading to the media library, making the functions available there not applicable?
 */

// Get NextGEN Gallery Functions
require_once ( NGGALLERY_ABSPATH . '/admin/functions.php' );

class UploaderNggAdmin extends nggAdmin {

    // Public Variables
    public $arrImageIds      = array();
    public $arrImageNames    = array();
    public $arrThumbReturn   = array();
    public $arrEXIF          = array();
    public $arrErrorMsg      = array();
    public $arrErrorMsg_widg = array();
    public $strFileName      = '';
    public $strGalleryPath   = '';
    public $blnRedirectPage  = false;

    function upload_images() {
        global $wpdb;
        // Image Array
        $imageslist = array();
        // Get Gallery ID
        $galleryID = (int) $_POST['galleryselect'];
        if ( $galleryID == 0 ) {
            if( get_option( 'npu_default_gallery' ) ) {
                $galleryID = get_option( 'npu_default_gallery' );
            } else {
                self::show_error( __( 'No gallery selected.', 'nextgen-public-uploader' ) );
                return;
            }
        }
        // Get Gallery Path
        $gallerypath = $wpdb->get_var( "SELECT path FROM $wpdb->nggallery WHERE gid = '$galleryID' " );
        if ( ! $gallerypath ) {
            self::show_error( __( 'Failure in database, no gallery path set.', 'nextgen-public-uploader' ) );
            return;
        }
        // Read Image List
        $dirlist = $this->scandir( ABSPATH . $gallerypath );
        foreach( $_FILES as $key => $value ) {
            if ( $_FILES[$key]['error'] == 0 ) {
                $temp_file = $_FILES[$key]['tmp_name'];
                $filepart = pathinfo( strtolower( $_FILES[$key]['name'] ) );
                // Required Until PHP 5.2.0
                $filepart['filename'] = substr( $filepart["basename"], 0, strlen( $filepart["basename"] ) - ( strlen( $filepart["extension"] ) + 1 ) );
                // Only when files need approval and no roles being used add the hash
                if ( get_option( 'npu_use_hash' ) == 'Enabled' ) {
                    // Random hash generation added by [http://www.linus-neumann.de/2011/04/19/ngg_pu_patch]
                    $randPool = '0123456789abcdefghijklmnopqrstuvwxyz';
                    $entropy = '';
                    for( $i = 0; $i<20; $i++ )
                        $entropy .= $randPool[ mt_rand( 0, strlen( $randPool )-1 ) ];
                    $filename = sanitize_title( $filepart['filename'] ) . '-' . sha1( md5( $entropy ) ) . '.' . $filepart['extension'];
                } else {
                    $filename = sanitize_title( $filepart['filename'] ) . '.' . $filepart['extension'];
                }
                // Allowed Extensions
                $ext = array( 'jpeg', 'jpg', 'png', 'gif' );
                $size = @getimagesize($temp_file);
                if ( !in_array( $filepart['extension'], $ext ) || !$size ) {
                    self::show_error( '<strong>' . $_FILES[$key]['name'] . '</strong> ' . __( 'is not a valid file.', 'nextgen-public-uploader' ) );
                    continue;
                }

                // defaults 0 to ignore these checks
                $minWidth = get_option( 'npu_min_width', 0);
                $maxWidth = get_option( 'npu_max_width', 0);
                $minHeight = get_option( 'npu_min_height', 0);
                $maxHeight = get_option( 'npu_max_height', 0);
                $maxSize = get_option( 'npu_max_filesize', 0);

                // Check for min/max width and height
                if ($minWidth > 0 && $size[0] < $minWidth) {
                    self::show_error( '<strong>' . $_FILES[$key]['name'] . '</strong> ' . __( 'width in pixels is too small. Minimum ', 'nextgen-public-uploader' ) . $minWidth . 'px');
                    continue;
                }
                if ($maxWidth > 0 && $size[0] > $maxWidth) {
                    self::show_error( '<strong>' . $_FILES[$key]['name'] . '</strong> ' . __( 'width in pixels is too large. Maximum ', 'nextgen-public-uploader' ) . $maxWidth . 'px');
                    continue;
                }
                if ($minHeight > 0 && $size[0] < $minHeight) {
                    self::show_error( '<strong>' . $_FILES[$key]['name'] . '</strong> ' . __( 'height in pixels is too small. Minimum ', 'nextgen-public-uploader' ) . $minHeight . 'px');
                    continue;
                }
                if ($maxHeight > 0 && $size[1] > $maxHeight) {
                    self::show_error( '<strong>' . $_FILES[$key]['name'] . '</strong> ' . __( 'height in pixels is too large. Maximum ', 'nextgen-public-uploader' ) . $maxHeight . 'px');
                    continue;
                }
                // check for max filesize, option is in kB, $_FILES[][size] in Bytes
                if ($maxSize > 0 && $_FILES[$key]['size'] > $maxSize*1024) {
                    self::show_error( '<strong>' . $_FILES[$key]['name'] . '</strong> ' . __( 'filesize is too large. Maximum ', 'nextgen-public-uploader' ) . $maxSize . 'kB');
                    continue;
                }

                // Check If File Exists
                $i = 0;
                while ( in_array( $filename, $dirlist ) ) {
                    $filename = sanitize_title( $filepart['filename'] ) . '_' . $i++ . '.' . $filepart['extension'];
                }
                $dest_file = ABSPATH . $gallerypath . '/' . $filename;
                // Check Folder Permissions
                if ( !is_writeable( ABSPATH . $gallerypath ) ) {
                    $message = sprintf( __( 'Unable to write to directory %s. Is this directory writable by the server?', 'nextgen-public-uploader' ), ABSPATH . $gallerypath );
                    self::show_error( $message );
                    return;
                }
                // Save Temporary File
                if ( !@move_uploaded_file( $_FILES[$key]['tmp_name'], $dest_file ) ) {
                    self::show_error( __( 'Error, the file could not moved to: ', 'nextgen-public-uploader' ) . $dest_file );
                    $this->check_safemode( ABSPATH.$gallerypath );
                    continue;
                }
                if ( ! $this->chmod( $dest_file ) ) {
                    self::show_error( __( 'Error, the file permissions could not set.', 'nextgen-public-uploader' ) );
                    continue;
                }
                // Add to Image and Dir List
                $imageslist[] = $filename;
                $dirlist[] = $filename;
            }
        }
        if ( count( $imageslist ) > 0 ) {
            if ( ! get_option( 'npu_exclude_select' ) ) {
                $npu_exclude_id = 0;
            } else {
                $npu_exclude_id = 1;
            }
            // Add Images to Database
            $image_ids = $this->add_Images( $galleryID, $imageslist );
            $this->arrThumbReturn = array();
            foreach ( $image_ids as $pid ) {
                $wpdb->query( "UPDATE $wpdb->nggpictures SET exclude = '$npu_exclude_id' WHERE pid = '$pid'" );
                $this->arrThumbReturn[] = $this->create_thumbnail( $pid );
            }


            /*
             * NextGen 2.x pictures table for reference
             *
            CREATE TABLE `wpfoto_ngg_pictures` (
              `pid` bigint(20) NOT NULL AUTO_INCREMENT,
              `image_slug` varchar(255) NOT NULL,
              `post_id` bigint(20) NOT NULL DEFAULT '0',
              `galleryid` bigint(20) NOT NULL DEFAULT '0',
              `filename` varchar(255) NOT NULL,
              `description` mediumtext,
              `alttext` mediumtext,
              `imagedate` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
              `exclude` tinyint(4) DEFAULT '0',
              `sortorder` bigint(20) NOT NULL DEFAULT '0',
              `meta_data` longtext,
              `extras_post_id` bigint(20) NOT NULL DEFAULT '0',
              `updated_at` bigint(20) DEFAULT NULL,
              PRIMARY KEY (`pid`),
              KEY `extras_post_id_key` (`extras_post_id`)
            ) ENGINE=InnoDB AUTO_INCREMENT=304 DEFAULT CHARSET=utf8;
            */


            $this->arrImageIds    = array();
            $this->arrImageIds    = $image_ids;
            $this->arrImageNames  = array();
            $this->arrImageNames  = $imageslist;
            $this->strGalleryPath = $gallerypath;
        }
        return;
    } // End Function

    function upload_images_widget() {
        global $wpdb;
        // Image Array
        $imageslist = array();
        // Get Gallery ID
        $galleryID = (int)$_POST['galleryselect'];
        if ( $galleryID == 0 ) {
            if( get_option( 'npu_default_gallery' ) ) {
                $galleryID = get_option( 'npu_default_gallery' );
            } else {
                self::show_error( __( 'No gallery selected.', 'nextgen-public-uploader' ) );
                return;
            }
        }
        // Get Gallery Path
        $gallerypath = $wpdb->get_var( "SELECT path FROM $wpdb->nggallery WHERE gid = '$galleryID' " );
        if ( ! $gallerypath ) {
            self::show_error( __( 'Failure in database, no gallery path set.', 'nextgen-public-uploader' ) );
            return;
        }
        // Read Image List
        $dirlist = $this->scandir( ABSPATH . $gallerypath );
        foreach( $_FILES as $key => $value ) {
            if ( $_FILES[$key]['error'] == 0 ) {
                $temp_file = $_FILES[$key]['tmp_name'];
                $filepart = pathinfo ( strtolower( $_FILES[$key]['name'] ) );
                // Required Until PHP 5.2.0
                $filepart['filename'] = substr( $filepart["basename"], 0, strlen( $filepart["basename"] ) - ( strlen( $filepart["extension"] ) + 1 ) );
                $filename = sanitize_title( $filepart['filename'] ) . '.' . $filepart['extension'];
                // Allowed Extensions
                $ext = array( 'jpeg', 'jpg', 'png', 'gif' );
                if ( !in_array( $filepart['extension'], $ext ) || !@getimagesize( $temp_file ) ){
                    self::show_error( '<strong>' . $_FILES[$key]['name'] . '</strong>' . __( 'is not a valid file.', 'nextgen-public-uploader' ) );
                    continue;
                }
                // Check If File Exists
                $i = 0;
                while ( in_array( $filename, $dirlist ) ) {
                    $filename = sanitize_title( $filepart['filename'] ) . '_' . $i++ . '.' . $filepart['extension'];
                }
                $dest_file = ABSPATH . $gallerypath . '/' . $filename;
                // Check Folder Permissions
                if ( !is_writeable( ABSPATH . $gallerypath ) ) {
                    $message = sprintf( __( 'Unable to write to directory %s. Is this directory writable by the server?', 'nextgen-public-uploader' ), ABSPATH . $gallerypath );
                    self::show_error( $message );
                    return;
                }
                // Save Temporary File
                if ( ! @move_uploaded_file( $_FILES[$key]['tmp_name'], $dest_file ) ) {
                    self::show_error( __( 'Error, the file could not moved to: ', 'nextgen-public-uploader' ) . $dest_file );
                    $this->check_safemode( ABSPATH . $gallerypath );
                    continue;
                }
                if ( ! $this->chmod( $dest_file ) ) {
                    self::show_error( __( 'Error, the file permissions could not set.', 'nextgen-public-uploader' ) );
                    continue;
                }
                // Add to Image and Dir List
                $imageslist[] = $filename;
                $dirlist[] = $filename;
            }
        }
        if ( count( $imageslist ) > 0 ) {
            if ( ! get_option( 'npu_exclude_select' ) ) {
                $npu_exclude_id = 0;
            } else {
                $npu_exclude_id = 1;
            }
            // Add Images to Database, uses add_Images from NextGen 2.x legacy classes
            $image_ids = $this->add_Images( $galleryID, $imageslist );
            $this->arrThumbReturn = array();

            foreach ( $image_ids as $pid ) { //TODO: prepare
                $wpdb->query( "UPDATE $wpdb->nggpictures SET exclude = '$npu_exclude_id' WHERE pid = '$pid'" );
                $this->arrThumbReturn[] = $this->create_thumbnail( $pid );
            }

            $this->arrImageIds    = array();
            $this->arrImageIds    = $image_ids;
            $this->arrImageNames  = array();
            $this->arrImageNames  = $imageslist;
            $this->strGalleryPath = $gallerypath;
        }
        return;
    } // End Function

    public static function show_error( $msg ) {
        if ( is_user_logged_in() && apply_filters( 'uploader_ngg_admin_show_error', true ) ) {
            nggGallery::show_error( $msg );
        }
    }
}
