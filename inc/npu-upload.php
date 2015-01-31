<?php

if ( ! class_exists( 'npuGalleryUpload' ) ) {

    // Public Variables
    class npuGalleryUpload {

        public $arrImageIds          = array();
        public $arrUploadedThumbUrls = array();
        public $arrUploadedImageUrls = array();
        public $arrErrorMsg          = array();
        public $arrImageMsg          = array();
        public $arrErrorMsg_widg     = array();
        public $arrImageMsg_widg     = array();
        public $arrImageNames        = array();
        public $arrImageMeta         = array();
        public $arrShortcodeArgs     = array();
        public $strTitle             = '';
        public $strDescription       = '';
        public $strKeywords          = '';
        public $strTimeStamp         = '';
        public $strGalleryPath       = '';
        public $blnRedirectPage      = false;

        // Function: Constructors
        public function __construct() {
            add_shortcode( 'ngg_uploader', array( $this, 'shortcode_show_uploader' ) ); // Shortcode Uploader
        }

        // Function: Add Scripts
        public function add_scripts() {
            wp_register_script( 'ngg-ajax', NGGALLERY_URLPATH . 'admin/js/ngg.ajax.js', array( 'jquery' ), '1.0.0' );
            // Setup Array
            wp_localize_script(
                'ngg-ajax',
                'nggAjaxSetup', array(
                    'url' => admin_url('admin-ajax.php'),
                    'action' => 'ngg_ajax_operation',
                    'operation' => '',
                    'nonce' => wp_create_nonce( 'ngg-ajax' ),
                    'ids' => '',
                    'permission' => __( 'You do not have the correct permission', 'nextgen-public-uploader' ),
                    'error' => __( 'Unexpected Error', 'nextgen-public-uploader' ),
                    'failure' => __( 'Upload Failed', 'nextgen-public-uploader' )
                )
            );
            wp_register_script( 'ngg-progressbar', NGGALLERY_URLPATH . 'admin/js/ngg.progressbar.js', array( 'jquery' ), '1.0.0' );
            wp_register_script( 'swfupload_f10', NGGALLERY_URLPATH . 'admin/js/swfupload.js', array( 'jquery' ), '2.2.0' );
            wp_enqueue_script( 'jquery-ui-tabs' );
            wp_enqueue_script( 'multifile', NGGALLERY_URLPATH . 'admin/js/jquery.MultiFile.js', array( 'jquery' ), '1.1.1' );
            wp_enqueue_script( 'ngg-swfupload-handler', NGGALLERY_URLPATH . 'admin/js/swfupload.handler.js', array( 'swfupload_f10' ), '1.0.0' );
            wp_enqueue_script( 'ngg-ajax' );
            wp_enqueue_script( 'ngg-progressbar' );
        }

        /**
         * Abstracts the image upload field.
         * --> TODO update for new upload layout
         * Used in the uploader, uploader widget, and Gravity Forms custom input field.
         *
         * @param  integer $gal_id     Gallery ID for NextGen Gallery.
         * @param  string  $context    Context.
         *
         * @return string  $strOutput  HTML output for image upload input.
         */
        public function display_image_upload_input( $gal_id = 0, $context = 'shortcode', $disable = false, $name = 'galleryselect') {
            $strOutput = wp_nonce_field( 'ngg_addgallery', '_wpnonce', true , false );
            $strOutput .= apply_filters( 'npu_gallery_upload_display_uploader_pre_input', '', $this, $context );

            $disabled   = $disable ? " disabled='disabled'" : '';

            $strOutput .= "\n<input type=\"hidden\" name=\"{$name}\" value=\"{$gal_id}\">";

            // get the number of upload fields to show, ADDED ESPAAN 20150118
            $option_max_uploads = get_option( 'npu_nr_of_uploads', 1);

            // Add small jQuery javascript for dynamic adding of pictures
            $strOutput .= "<script type=\"text/javascript\">";
            $strOutput .= 'jQuery(function($){$("document").ready(function(){$("#npu_upload_addBtn").click(function() { var ncnt = parseInt($("#npu_upload_lastCnt").val())+1;var ninp=$("<br/><input type=\"file\" name=\"imagefiles[]\" id=\"imagefiles"+ncnt+"\" />");$("#UploaderDiv' . $gal_id . '").append(ninp); });});});';
            $strOutput .= "</script>";

            $strOutput .= "\n\t<div id=\"UploaderDiv" . $gal_id . "\" class=\"uploader\"><input type=\"hidden\" id=\"npu_upload_lastCnt\" value=\"1\" />";
            $strOutput .= "\n\t<input type=\"file\" name=\"imagefiles[]\" id=\"imagefiles1\"{$disabled}/>";
            $strOutput .= "\n</div><input type=\"button\" value=\"" . __('+ Add another picture total ', 'nextgen-public-uploader') . "$option_max_uploads\" id=\"npu_upload_addBtn\">";

            return $strOutput;
        }

        // Function: Shortcode Form
        public function display_uploader( $gal_id, $strDetailsPage = false, $blnShowAltText = true, $echo = true ) {
            $strOutput = '';
            if ( count( $this->arrErrorMsg ) > 0 ) {
                $strOutput .= '<div class="upload_error">';
                foreach ( $this->arrErrorMsg as $msg ) {
                    $strOutput .= $msg;
                }
                $strOutput .= '</div>';
            }
            if ( count( $this->arrImageMsg ) > 0 ) {
                $strOutput .= '<div class="upload_error">';
                foreach ( $this->arrImageMsg as $msg ) {
                    $strOutput .= $msg;
                }
                $strOutput .= '</div>';
            }
            if ( !is_user_logged_in() && get_option( 'npu_user_role_select' ) != 99 ) {
                $strOutput .= '<div class="need_login">';
                $notlogged = get_option( 'npu_notlogged' );
                if( !empty( $notlogged ) ) {
                    $strOutput .= $notlogged;
                } else {
                    $strOutput .= __( 'You must be registered and logged in to upload images.', 'nextgen-public-uploader' );
                }
                $strOutput .= '</div>';
            } else {
                $npu_selected_user_role = get_option( 'npu_user_role_select' );

                if ( current_user_can( 'level_' . $npu_selected_user_role ) || get_option( 'npu_user_role_select' ) == 99 ) {

                    $strOutput .= apply_filters( 'npu_gallery_upload_display_uploader_before_form', '', $this, 'shortcode' );

                    $strOutput .= '<style type="text/css">';
                    $strOutput .= '   .npu_uploader input[type=file] {color:#F1F1F1;padding:5px;margin:15px 0px 5px;}';
                    $strOutput .= '   #file-list'. $gal_id .' {list-style: none;}';
                    $strOutput .= '   #file-list'. $gal_id .' li {border-bottom: 1px solid #F1F1F1;margin-bottom: 0.5em;padding-bottom: 0.5em;}';
                    $strOutput .= '   #file-list'. $gal_id .' li.no-items {border-bottom: none;}';
                    $strOutput .= '</style>';

                    $strOutput .= '<div id="npu_uploadimage'. $gal_id .'">';
                    $strOutput .= "\n\t<form name=\"npu_uploadimage\" id=\"npu_uploadimage_form'. $gal_id .'\" method=\"POST\" enctype=\"multipart/form-data\" accept-charset=\"utf-8\" >";

                    $strOutput .= wp_nonce_field( 'ngg_addgallery', '_wpnonce', true , false );
                    $strOutput .= apply_filters( 'npu_gallery_upload_display_uploader_pre_input', '', $this, 'shortcode' );

                    // the preselected gallery if present
                    $strOutput .= "\n<input type=\"hidden\" name=\"galleryselect\" value=\"{$gal_id}\">";

                    // get the max number of upload fields to show
                    $option_default_uploads = get_option( 'npu_default_uploads', 1);
                    $option_max_uploads = get_option( 'npu_max_uploads', -1);
                    $file_description = get_option( 'npu_description_text',  __( 'Description:', 'nextgen-public-uploader' ) );

                    // Add small jQuery javascript for dynamic adding of pictures
                    if ($option_max_uploads > $option_default_uploads && get_option( 'npu_use_html5_multiple' ) != 'Enabled' ) {
                        $strOutput .= "<script type=\"text/javascript\">";
                        $strOutput .= 'jQuery(document).ready(function($){$("#npu_upload_addBtn'. $gal_id .'").click(function() {';
                        $strOutput .= '    var ncnt = parseInt($("#npu_upload_lastCnt'. $gal_id .'").val())+1;';
                        $strOutput .= '    $("#npu_upload_lastCnt'. $gal_id .'").val(ncnt);';
                        $strOutput .= '    var file_description = "' . $file_description . '";';
                        $strOutput .= '    var ninp = $("<input type=\"file\" name=\"imagefiles[]\" id=\"imagefiles'. $gal_id .'"+ncnt+"\" />");';
                        $strOutput .= '    $("#UploaderDiv'. $gal_id .'").append(ninp);';
                        if ( get_option( 'npu_image_description_select' ) == 'Enabled' ) {
                            $strOutput .= '    var ndesc = $("<br/>"+file_description+" <input type=\"text\" size=\"50\" name=\"imagedescription[]\" id=\"imagedescription'. $gal_id .'"+ncnt+"\" /><br />");';
                            $strOutput .= '    $("#UploaderDiv'. $gal_id .'").append(ndesc);';
                        }
                        $strOutput .= '    if (ncnt >= ' . $option_max_uploads . ') {$(this).attr("disabled","disabled");$(this).hide();}';
                        $strOutput .= '});});';
                        $strOutput .= "</script>";
                    }

                    $strOutput .= "\n\t<div id=\"UploaderDiv". $gal_id ."\" class=\"npu_uploader\">";
                    $strOutput .= "\n\t<input type=\"hidden\" id=\"npu_upload_lastCnt". $gal_id ."\" value=\"" . $option_default_uploads . "\" />";
                    $inputs = 1;
                    if ( get_option( 'npu_use_html5_multiple' ) == 'Enabled' ) {
                        $strOutput .= "<input id=\"imagefiles". $gal_id ."\" name=\"imagefiles[]\" type=\"file\" accept=\"image/*\" multiple=\"multiple\" />";
				        $strOutput .= "<ul id=\"file-list". $gal_id ."\"><li class=\"no-items\">" . __( '(no uploaded files yet)', 'nextgen-public-uploader' ) . "</li></ul>";
                        $strOutput .= "<script type=\"text/javascript\">
(function () {
    var filesUpload = document.getElementById(\"imagefiles". $gal_id ."\"),
	fileList = document.getElementById(\"file-list". $gal_id ."\");
	
	function traverseFiles (files) {
        var li,
			file,
			fileInfo;
		fileList.innerHTML = \"\";

        // Not fail safe, but basic client side limitation
		if (files.length > " . $option_max_uploads . ") {
		    alert(\"" . __( 'Over the maximum number of files: ', 'nextgen-public-uploader' ) . ' ' . $option_max_uploads . "\");
		    filesUpload.value = '';
		    files = array();
		    return;
		}

		for (var i=0, il=files.length; i<il; i++) {
            li = document.createElement(\"li\");
            file = files[i];
			fileInfo = \"<div><strong>" . __( 'Name:', 'nextgen-public-uploader' ) . "</strong> \" + file.name + \"</div>\";
			fileInfo += \"<div><strong>" . __( 'Size:', 'nextgen-public-uploader' ) . "</strong> \" + Math.round(file.size/1024) + \" kB</div>\";
			//fileInfo += \"<div><strong>" . __( 'Type:', 'nextgen-public-uploader' ) . "</strong> \" + file.type + \"</div>\";
			li.innerHTML = fileInfo;
			fileList.appendChild(li);
		};
	};
	
	filesUpload.onchange = function () {
        traverseFiles(this.files);
    };
	})();</script>";
                    } else {
                        while($inputs <= $option_default_uploads) {
                            $strOutput .= "\n\t<input type=\"file\" name=\"imagefiles[]\" id=\"imagefiles". $gal_id . $inputs . "\" />";
                            if ( get_option( 'npu_image_description_select' ) == 'Enabled' ) {
                                $strOutput .= '<br />' . $file_description;
                                $strOutput .= "\n\t <input type=\"text\" size=\"50\" placeholder=\"" . get_option( 'npu_description_placeholder' ) . "\" name=\"imagedescription[]\" id=\"imagedescription". $gal_id . $inputs . "\" /><br />";
                            }
                            $inputs++;
                        }
                    }
                    $strOutput .= "\n\t</div>"; // UploaderDiv

                    if ( !$strDetailsPage ) {
                        $strOutput .= "\n\t<div class=\"image_details_textfield\">";
                        if ( $blnShowAltText ) {}
                        $strOutput .= "\n\t</div>";
                    }

                    $strOutput .= apply_filters( 'npu_gallery_upload_display_uploader_before_submit', '', $this, 'shortcode' );

                    $strOutput .= "\n\t<div class=\"submit\"><br />";
                    if ( get_option( 'npu_upload_button' ) ) {
                        if ($option_max_uploads > $option_default_uploads && get_option( 'npu_use_html5_multiple' ) != 'Enabled' ) {
                            $strOutput .= "\n\t\t <input type=\"button\" value=\"" . sprintf( __( '+ Add another picture (total %d)', 'nextgen-public-uploader' ), $option_max_uploads ) . "\" id=\"npu_upload_addBtn". $gal_id ."\" />";
                        }
                        $strOutput .= "\n\t\t<input class=\"button-primary\" type=\"submit\" name=\"uploadimage\" id=\"uploadimage_btn". $gal_id ."\" ";
                        $strOutput .= 'value="' . get_option( 'npu_upload_button' ) . ' (' . get_option( 'npu_max_uploads_msg' ) . ' ' . $option_max_uploads . ')' . '">';
                    } else {
                        if ($option_max_uploads > $option_default_uploads && get_option( 'npu_use_html5_multiple' ) != 'Enabled' ) {
                            $strOutput .= "\n\t\t <input type=\"button\" value=\"" . sprintf( __( '+ Add another picture (total %d)', 'nextgen-public-uploader' ), $option_max_uploads ) . "\" id=\"npu_upload_addBtn". $gal_id ."\" />";
                        }
                        $strOutput .= "\n\t\t <input class=\"button-primary\" type=\"submit\" name=\"uploadimage\" id=\"uploadimage_btn". $gal_id ."\" value=\"Upload\" />";
                    }
                    $strOutput .= "\n\t\t</div>";
                    $strOutput .= "\n</form>";
                    $strOutput .= "\n</div>";

                    $strOutput .= apply_filters( 'npu_gallery_upload_display_uploader_after_form', '', $this, 'shortcode' );
                }
            }

            $strOutput = apply_filters( 'npu_gallery_upload_display_uploader', $strOutput, $gal_id, $strDetailsPage, $blnShowAltText, $echo, 'shortcode', $this );

            if ( $echo ) {
                echo $strOutput;
            } else {
                return $strOutput;
            }
        }

        // Function: Handle Upload for regular shortcode
        public function handleUpload() {
            global $wpdb;
            require_once( dirname (__FILE__) . '/class.npu_uploader.php' );
            require_once( NGGALLERY_ABSPATH . '/lib/meta.php' );
            $ngg->options['swfupload'] = false;

            if ( isset( $_POST['uploadimage'] ) ) {
                check_admin_referer( 'ngg_addgallery' );
                if ( !isset( $_FILES['MF__F_0_0']['error'] ) || $_FILES['MF__F_0_0']['error'] == 0 ) {
                    $objUploaderNggAdmin = new UploaderNggAdmin();
                    $messagetext = $objUploaderNggAdmin->upload_images();
                    $this->arrImageIds = $objUploaderNggAdmin->arrImageIds;
                    $this->strGalleryPath = $objUploaderNggAdmin->strGalleryPath;
                    $this->arrImageNames = $objUploaderNggAdmin->arrImageNames;
                    if ( is_array( $objUploaderNggAdmin->arrThumbReturn ) && count( $objUploaderNggAdmin->arrThumbReturn ) > 0 ) {
                        foreach ( $objUploaderNggAdmin->arrThumbReturn as $strReturnMsg ) {
                            if ( $strReturnMsg != '1' ) {
                                $this->arrErrorMsg[] = $strReturnMsg;
                            }
                        }

                        // multiple file image upload mail message
                        if(get_option('npu_upload_success')) {
                            if ($this->arrImageMsg[0] != get_option('npu_upload_success')){
                                $this->arrImageMsg[] = get_option('npu_upload_success');
                                $this->sendEmail();
                            }
                        } else {
                            $this->arrImageMsg[] = __( 'Thank you! Your image has been submitted and is pending review.', 'nextgen-public-uploader' );
                            $this->sendEmail();
                        }
                    }
                    if ( is_array( $this->arrImageIds ) && count( $this->arrImageIds ) > 0 ) {
                        foreach ( $this->arrImageIds as $imageId ) {
                            $pic = nggdb::find_image( $imageId );
                            $objEXIF = new nggMeta( $pic->imagePath );
                            $this->strTitle = $objEXIF->get_META( 'title' );
                            $this->strDescription = $objEXIF->get_META( 'caption' );
                            $this->strKeywords = $objEXIF->get_META( 'keywords' );
                            $this->strTimeStamp = $objEXIF->get_date_time();
                            //What are we doing with this stuff? It's just reassigning, unless there's only ever 1 index in the array.
                        }
                    } else {
                        if (!(get_option( 'npu_max_uploads', -1) > get_option( 'npu_default_uploads', 1) || get_option( 'npu_default_uploads', 1) > 1)) {
                            if ( get_option( 'npu_no_file' ) ) {
                                $this->arrErrorMsg[] = get_option( 'npu_no_file' );
                            } else {
                                $this->arrErrorMsg[] = __( 'You must select a file to upload', 'nextgen-public-uploader' );
                            }
                        }
                    }
                    $this->update_details();
                } else {
                    if ( get_option( 'npu_upload_failed' ) ) {
                        $this->arrErrorMsg[] = get_option( 'npu_upload_failed' );
                    } else {
                        $this->arrErrorMsg[] = __( 'Upload failed!', 'nextgen-public-uploader' );
                    }
                }
                if ( count( $this->arrErrorMsg ) > 0 && ( is_array( $this->arrImageIds ) && count( $this->arrImageIds ) > 0 ) ) {
                    $gal_id = ( !empty( $_POST['galleryselect'] ) ) ? absint( $_POST['galleryselect'] ) : 1;
                    foreach ( $this->arrImageIds as $intImageId ) {
                        $filename = $wpdb->get_var( "SELECT filename FROM $wpdb->nggpictures WHERE pid = '$intImageId' "); //Prepare me
                        if ( $filename ) {
                            $gallerypath = $wpdb->get_var( $wpdb->prepare( "SELECT path FROM $wpdb->nggallery WHERE gid = %d", $gal_id ) );
                            if ( $gallerypath ){
                                @unlink( ABSPATH . $gallerypath . '/thumbs/thumbs_' . $filename );
                                @unlink( ABSPATH . $gallerypath . '/' . $filename );
                            }
                            $delete_pic = $wpdb->delete( $wpdb->nggpictures, array( 'pid' => $intImageId ), array( '%d' ) );
                        }
                    }
                }
            }
        }

        // Function: Update Details
        public function update_details() {
            global $wpdb;
            $arrUpdateFields = array();
            if ( isset( $_POST['imagedescription'] ) && !empty( $_POST['imagedescription'] ) ) {
                $this->strDescription = esc_sql( $_POST['imagedescription'] );
                $arrUpdateFields[] = "description = '$this->strDescription'";
            } else {
                return;
            }
            if ( isset( $_POST['alttext'] ) && !empty( $_POST['alttext'] ) ) {
                $this->strTitle = esc_sql( $_POST['alttext'] );
                $arrUpdateFields[] = "alttext = '$this->strTitle'";
            }
            if ( isset( $_POST['tags'] ) && !empty( $_POST['tags'] ) ) {
                $this->strKeywords = $_POST['tags']; //sanitize!
            }
            if ( count( $arrUpdateFields) > 0 ) {
                if ( ! get_option( 'npu_exclude_select' )  ) {
                    $npu_exclude_id = 0;
                } else {
                    $npu_exclude_id = 1;
                }
                $strUpdateFields = implode( ', ', $arrUpdateFields );
                $pictures = $this->arrImageIds;
                if ( count( $pictures ) > 0 ) {
                    foreach ( (array)$pictures as $pid ) {
                        $strQuery = "UPDATE $wpdb->nggpictures SET ";
                        $strQuery .= $strUpdateFields . ", exclude = $npu_exclude_id WHERE pid = $pid";
                        $wpdb->query( $strQuery );
                        $arrTags = explode( ',', $this->strKeywords );
                        wp_set_object_terms( $pid, $arrTags, 'ngg_tag' );
                    }
                }
            }

            do_action( 'npu_gallery_upload_update_details', $this );
        }

        // Function: Shortcode
        public function shortcode_show_uploader( $atts ) {

            $default_args = apply_filters( 'npu_gallery_upload_shortcode_atts', array(
                'id'       => get_option( 'npu_default_gallery' ),
                'template' => ''
            ), $this );

            $this->arrShortcodeArgs = version_compare( $GLOBALS['wp_version'], '3.6', '>=' ) ? shortcode_atts( $default_args, $atts, 'ngg_uploader' ) : shortcode_atts( $default_args, $atts );

            extract( $this->arrShortcodeArgs );

            // process multiple files via handleUpload
            $upload_count = count((array)$_FILES['imagefiles']['tmp_name']);
            $uploaded_files = $_FILES['imagefiles'];
            $uploaded_desc = $_POST['imagedescription'];
            for($i=0; $i<$upload_count; $i++){
                $_FILES['imagefiles']['name']= $uploaded_files['name'][$i];
                $_FILES['imagefiles']['type']= $uploaded_files['type'][$i];
                $_FILES['imagefiles']['tmp_name']= $uploaded_files['tmp_name'][$i];
                $_FILES['imagefiles']['error']= $uploaded_files['error'][$i];
                $_FILES['imagefiles']['size']= $uploaded_files['size'][$i];
                $_POST['imagedescription'] = $uploaded_desc[$i];
                $this->handleUpload();
            }
            //$this->handleUpload();

            return $this->display_uploader( $id, false, true, false );
        }

        // Function: Send Email Notice
        // TODO UPDATE METHOD better feedback based on npu_exclude_select and $this->arrImageMsg[]
        public function sendEmail() {

            if ( get_option( 'npu_notification_email' ) ) {

                $to      = apply_filters( 'npu_gallery_upload_send_email_to'     , get_option( 'npu_notification_email' ), $this );
                $subject = apply_filters( 'npu_gallery_upload_send_email_subject', __( 'New Image Pending Review - NextGEN Public Uploader', 'nextgen-public-uploader' ), $this );
                $message = apply_filters( 'npu_gallery_upload_send_email_message', __( 'A new image has been submitted and is waiting to be reviewed.', 'nextgen-public-uploader' ), $this );

                wp_mail( $to, $subject, $message );
            }
        }


        /**
         * Display the form on the frontend widget
         */
        public function display_uploader_widget( $gal_id, $strDetailsPage = false, $blnShowAltText = true, $echo = true ) {
            $output = '';

            //check if we have any error messages
            if ( count( $this->arrErrorMsg_widg ) > 0 ) {
                $output .= '<div class="upload_error">';
                foreach ( $this->arrErrorMsg_widg as $msg )  {
                    $output .= $msg;
                }
                $output .= '</div>';
            }
            //check if we have any image messages
            if ( count( $this->arrImageMsg_widg ) > 0 ) {
                $output .= '<div class="upload_error">';
                foreach ( $this->arrImageMsg_widg as $msg ) {
                    $output .= $msg;
                }
                $output .= '</div>';
            }

            if ( !is_user_logged_in() && get_option( 'npu_user_role_select' ) != 99 ) {
                $output .= '<div class="need_login">';
                if( get_option( 'npu_notlogged' ) ) {
                    $output .= get_option( 'npu_notlogged' );
                } else {
                    $output .= __( 'You must be registered and logged in to upload images.', 'nextgen-public-uploader' );
                }
                $output .= '</div>';
            } else {
                $npu_selected_user_role = get_option( 'npu_user_role_select' );

                if ( current_user_can( 'level_'. $npu_selected_user_role ) || get_option( 'npu_user_role_select' ) == 99 ) {

                    $output .= apply_filters( 'npu_gallery_upload_display_uploader_before_form', '', $this, 'widget' );

                    $output .= '<style type="text/css">';
                    $output .= '   .npu_uploader input[type=file] {color:#F1F1F1;padding:5px;margin:15px 0px 5px;}';
                    $output .= '   #file-list'. $gal_id .' {list-style: none;}';
                    $output .= '   #file-list'. $gal_id .' li {font-size:0.9em;border-bottom:1px solid #F1F1F1;margin-bottom:0.5em;padding-bottom:0.5em;}';
                    $output .= '   #file-list'. $gal_id .' li.no-items {border-bottom: none;}';
                    $output .= '</style>';

                    $output .= '<div id="npu_uploadimage">';
                    $output .= "\n\t<form name=\"npu_uploadimage\" id=\"npu_uploadimage_form" . $gal_id ."\" method=\"POST\" enctype=\"multipart/form-data\" accept-charset=\"utf-8\" >";

                    $output .= wp_nonce_field( 'ngg_addgallery', '_wpnonce', true , false );
                    $output .= apply_filters( 'npu_gallery_upload_display_uploader_pre_input', '', $this, 'widget' );

                    // the preselected gallery if present
                    $output .= "\n<input type=\"hidden\" name=\"galleryselect\" value=\"{$gal_id}\">";

                    // get the max number of upload fields to show
                    //$option_default_uploads = get_option( 'npu_default_uploads', 1);
                    // In widget mode only show by default 1 input
                    $option_default_uploads = 1;
                    $option_max_uploads = get_option( 'npu_max_uploads', -1);
                    $file_description = get_option( 'npu_description_text',  __( 'Description:', 'nextgen-public-uploader' ) );

                    // Add small jQuery javascript for dynamic adding of pictures
                    if ($option_max_uploads > $option_default_uploads && get_option( 'npu_use_html5_multiple' ) != 'Enabled' ) {
                        $output .= "<script type=\"text/javascript\">";
                        $output .= 'jQuery(document).ready(function($){$("#npu_upload_addBtn' . $gal_id . '").click(function() {';
                        $output .= '    var ncnt = parseInt($("#npu_upload_lastCnt' . $gal_id . '").val())+1;';
                        $output .= '    $("#npu_upload_lastCnt' . $gal_id . '").val(ncnt);';
                        $output .= '    var file_description = "' . $file_description . '";';
                        $output .= '    var ninp = $("<input type=\"file\" name=\"imagefiles[]\" id=\"imagefiles' . $gal_id . '"+ncnt+"\" />");';
                        $output .= '    $("#UploaderDiv' . $gal_id . '").append(ninp);';
                        if ( get_option( 'npu_image_description_select' ) == 'Enabled' ) {
                            $output .= '    var ndesc = $("<br/>"+file_description+" <input type=\"text\" size=\"50\" name=\"imagedescription[]\" id=\"imagedescription' . $gal_id . '"+ncnt+"\" /><br />");';
                            $output .= '    $("#UploaderDiv' . $gal_id . '").append(ndesc);';
                        }
                        $output .= '    if (ncnt >= ' . $option_max_uploads . ') {$(this).attr("disabled","disabled");$(this).hide();}';
                        $output .= '});});';
                        $output .= "</script>";
                    }

                    $output .= "\n\t<div id=\"UploaderDiv" . $gal_id ."\" class=\"npu_uploader\">";
                    $output .= "\n\t<input type=\"hidden\" id=\"npu_upload_lastCnt" . $gal_id . "\" value=\"" . $option_default_uploads . "\" />";
                    $inputs = 1;
                    if ( get_option( 'npu_use_html5_multiple' ) == 'Enabled' ) {
                        $output .= "<input id=\"imagefiles" . $gal_id . "\" name=\"imagefiles[]\" type=\"file\" accept=\"image/*\" multiple=\"multiple\" />";
                        $output .= "<ul id=\"file-list". $gal_id ."\"><li class=\"no-items\">" . __( '(no uploaded files yet)', 'nextgen-public-uploader' ) . "</li></ul>";
                        $output .= "<script type=\"text/javascript\">
(function () {
    var filesUpload = document.getElementById(\"imagefiles" . $gal_id . "\"),
	fileList = document.getElementById(\"file-list" . $gal_id . "\");

	function traverseFiles (files) {
        var li,
			file,
			fileInfo;
		fileList.innerHTML = \"\";

        // Not fail safe, but basic client side limitation
		if (files.length > " . $option_max_uploads . ") {
		    alert(\"" . __( 'Over the maximum number of files: ', 'nextgen-public-uploader' ) . ' ' . $option_max_uploads . "\");
		    filesUpload.value = '';
		    files = array();
		    return;
		}

		for (var i=0, il=files.length; i<il; i++) {
            li = document.createElement(\"li\");
            file = files[i];
			fileInfo = \"<div><strong>" . __( 'Name:', 'nextgen-public-uploader' ) . "</strong> \" + file.name + \"</div>\";
			fileInfo += \"<div><strong>" . __( 'Size:', 'nextgen-public-uploader' ) . "</strong> \" + Math.round(file.size/1024) + \" kB</div>\";
			//fileInfo += \"<div><strong>" . __( 'Type:', 'nextgen-public-uploader' ) . "</strong> \" + file.type + \"</div>\";
			li.innerHTML = fileInfo;
			fileList.appendChild(li);
		};
	};

	filesUpload.onchange = function () {
        traverseFiles(this.files);
    };
	})();</script>";
                    } else {
                        while($inputs <= $option_default_uploads) {
                            $output .= "\n\t<input type=\"file\" name=\"imagefiles[]\" id=\"imagefiles" . $gal_id . $inputs . "\" />";
                            if ( get_option( 'npu_image_description_select' ) == 'Enabled' ) {
                                $output .= '<br />' . $file_description;
                                $output .= "\n\t <input type=\"text\" size=\"50\" placeholder=\"" . get_option( 'npu_description_placeholder' ) . "\" name=\"imagedescription[]\" id=\"imagedescription" . $gal_id . $inputs . "\" /><br />";
                            }
                            $inputs++;
                        }
                    }
                    $output .= "\n\t</div>"; // UploaderDiv

                    if ( !$strDetailsPage ) {
                        $output .= "\n\t<div class=\"image_details_textfield\">";
                        if ( $blnShowAltText ) {}
                        $output .= "\n\t</div>";
                    }

                    $output .= apply_filters( 'npu_gallery_upload_display_uploader_before_submit', '', $this, 'widget' );

                    $output .= "\n\t<div class=\"submit\"><br />";
                    if ( get_option( 'npu_upload_button' ) ) {
                        if ($option_max_uploads > $option_default_uploads && get_option( 'npu_use_html5_multiple' ) != 'Enabled' ) {
                            $output .= "\n\t\t <input type=\"button\" value=\"" . sprintf( __( '+ Add another picture (total %d)', 'nextgen-public-uploader' ), $option_max_uploads ) . "\" id=\"npu_upload_addBtn" . $gal_id . "\" />";
                        }
                        $output .= "\n\t\t<input class=\"button-primary\" type=\"submit\" name=\"uploadimage\" id=\"uploadimage_btn" . $gal_id . "\" ";
                        $output .= 'value="' . get_option( 'npu_upload_button' ) . ' (' . get_option( 'npu_max_uploads_msg' ) . ' ' . $option_max_uploads . ')' . '">';
                    } else {
                        if ($option_max_uploads > $option_default_uploads && get_option( 'npu_use_html5_multiple' ) != 'Enabled' ) {
                            $output .= "\n\t\t <input type=\"button\" value=\"" . sprintf( __( '+ Add another picture (total %d)', 'nextgen-public-uploader' ), $option_max_uploads ) . "\" id=\"npu_upload_addBtn" . $gal_id . "\" />";
                        }
                        $output .= "\n\t\t <input class=\"button-primary\" type=\"submit\" name=\"uploadimage\" id=\"uploadimage_btn" . $gal_id . "\" value=\"Upload\" />";
                    }
                    $output .= "\n\t\t</div></form></div>";
                    $output .= apply_filters( 'npu_gallery_upload_display_uploader_after_form', '', $this, 'widget' );
                }
            }

            $output = apply_filters( 'npu_gallery_upload_display_uploader', $output, $gal_id, $strDetailsPage, $blnShowAltText, $echo, 'widget', $this );

            if ( $echo ) {
                echo $output;
            } else {
                return $output;
            }
        }

        // Function: Handle Upload for Widget
        public function handleUpload_widget() {

            global $wpdb;

            require_once( dirname (__FILE__). '/class.npu_uploader.php' );
            require_once( NGGALLERY_ABSPATH . '/lib/meta.php' );

            $ngg->options['swfupload'] = false; //Where is this being instantiated?

            if ( isset( $_POST['uploadimage_widget'] ) ) {

                check_admin_referer( 'ngg_addgallery' );

                if ( ! isset( $_FILES['MF__F_0_0']['error'] ) || $_FILES['MF__F_0_0']['error'] == 0 ) {

                    $objUploaderNggAdmin    = new UploaderNggAdmin();
                    $messagetext            = $objUploaderNggAdmin->upload_images_widget();
                    $this->arrImageIds      = $objUploaderNggAdmin->arrImageIds;
                    $this->strGalleryPath   = $objUploaderNggAdmin->strGalleryPath;
                    $this->arrImageNames    = $objUploaderNggAdmin->arrImageNames;

                    if ( is_array( $objUploaderNggAdmin->arrThumbReturn ) && count( $objUploaderNggAdmin->arrThumbReturn ) > 0 ) {
                        foreach ( $objUploaderNggAdmin->arrThumbReturn as $strReturnMsg ) {
                            if ( $strReturnMsg != '1' ) {
                                $this->arrErrorMsg_widg[] = $strReturnMsg;
                            }
                        }
                        $this->arrImageMsg_widg[] = ( get_option( 'npu_upload_success' ) )
                            ? get_option( 'npu_upload_success' )
                            : __( 'Thank you! Your image has been submitted and is pending review.', 'nextgen-public-uploader' );

                        $this->sendEmail();
                    }

                    //Used in update_details method.
                    if ( is_array( $this->arrImageIds ) && count( $this->arrImageIds ) > 0 ) {
                        foreach( $this->arrImageIds as $imageId ) {
                            $pic                    = nggdb::find_image( $imageId );
                            $objEXIF                = new nggMeta( $pic->imagePath );
                            $this->strTitle         = $objEXIF->get_META( 'title' );
                            $this->strDescription   = $objEXIF->get_META( 'caption' );
                            $this->strKeywords      = $objEXIF->get_META( 'keywords' );
                            $this->strTimeStamp     = $objEXIF->get_date_time();
                        }
                    } else {
                        $this->arrErrorMsg_widg[] = ( get_option( 'npu_no_file' ) )
                            ? get_option( 'npu_no_file' )
                            : __( 'You must select a file to upload', 'nextgen-public-uploader' );
                    }
                    $this->update_details();
                } else {
                    $this->arrErrorMsg_widg[] = ( get_option( 'npu_upload_failed' ) )
                        ? get_option( 'npu_upload_failed' )
                        : __( 'Upload failed!', 'nextgen-public-uploader' );
                }

                //If we've encountered any errors, delete?
                if ( count( $this->arrErrorMsg_widg ) > 0 && ( is_array( $this->arrImageIds ) && count( $this->arrImageIds ) > 0 ) ) {
                    $gal_id = ( !empty( $_POST['galleryselect'] ) ) ? absint( $_POST['galleryselect'] ) : 1;

                    foreach ( $this->arrImageIds as $intImageId ) {
                        $filename = $wpdb->get_var( "SELECT filename FROM $wpdb->nggpictures WHERE pid = '$intImageId' " );
                        if ( $filename ) {
                            $gallerypath = $wpdb->get_var( $wpdb->prepare( "SELECT path FROM $wpdb->nggallery WHERE gid = %d", $gal_id ) );
                            if ( $gallerypath ){
                                @unlink( ABSPATH . $gallerypath . '/thumbs/thumbs_' . $filename );
                                @unlink( ABSPATH . $gallerypath . '/' . $filename );
                            }
                            $delete_pic = $wpdb->delete( $wpdb->nggpictures, array( 'pid' => $intImageId ), array( '%d' ) );
                        }
                    }
                }
            }
        }

    } // end class
} // end class if exists

// Create Uploader
$npuUpload = new npuGalleryUpload();

/*
Register our widget
 */
function ngg_public_uploader() {
    register_widget( "NextGenPublicUploader" );
}
add_action( 'widgets_init', 'ngg_public_uploader' );

class NextGenPublicUploader extends WP_Widget {

    function __construct() {
        $widget_ops = array(
            'description'   => __( 'Upload images to a NextGEN Gallery', 'nextgen-public-uploader' ),
            'classname'     => 'npu_gallery_upload',
        );
        parent::__construct( false, _x( 'NextGEN Uploader', 'widget name', 'nextgen-public-uploader' ), $widget_ops );
    }

    function widget( $args, $instance ) {
        $npu_uploader = new npuGalleryUpload();

        extract( $args );

        $title = esc_html( $instance['title'] );
        $gal_id   = esc_attr( $instance['gal_id'] );

        echo $before_widget;

        if ( !empty( $title ) ) {
            echo $before_title . $title . $after_title;
        }
        // process multiple files via handleUpload
        $upload_count = count((array)$_FILES['imagefiles']['tmp_name']);
        $uploaded_files = $_FILES['imagefiles'];
        $uploaded_desc = $_POST['imagedescription'];
        for($i=0; $i<$upload_count; $i++){
            $_FILES['imagefiles']['name']= $uploaded_files['name'][$i];
            $_FILES['imagefiles']['type']= $uploaded_files['type'][$i];
            $_FILES['imagefiles']['tmp_name']= $uploaded_files['tmp_name'][$i];
            $_FILES['imagefiles']['error']= $uploaded_files['error'][$i];
            $_FILES['imagefiles']['size']= $uploaded_files['size'][$i];
            $_POST['imagedescription'] = $uploaded_desc[$i];
            $npu_uploader->handleUpload_widget();
        }

        $npu_uploader->display_uploader_widget( $gal_id, false ); //leave as method in separate class for now.

        echo $after_widget;
    }

    function form( $instance ) {

        // Set Defaults
        $instance = wp_parse_args( (array) $instance, array( 'gal_id' => '0' ) );

        include_once ( NGGALLERY_ABSPATH . "lib/ngg-db.php" );

        $nggdb = new nggdb();
        $gallerylist = $nggdb->find_all_galleries( 'gid', 'DESC' ); ?>

        <p><label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title:', 'nextgen-public-uploader' ); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" /></p>

        <p>
            <label for="<?php echo $this->get_field_id( 'gal_id' ); ?>"><?php _e( 'Upload to :', 'nextgen-public-uploader' ); ?></label>
            <select id="<?php echo $this->get_field_id( 'gal_id' ) ?>" name="<?php echo $this->get_field_name( 'gal_id' ); ?>">
                <option value="0" ><?php _e( 'Choose gallery', 'nextgen-public-uploader' ); ?></option>
                <?php
                foreach( $gallerylist as $gallery ) {
                    $name = ( empty( $gallery->title ) ) ? $gallery->name : $gallery->title;
                    echo '<option ' . selected( $instance['gal_id'], $gallery->gid, false ) . ' value="' . $gallery->gid . '">ID: ' . $gallery->gid . ' &ndash; ' . $name . '</option>';
                }
                ?>
            </select>
        </p>
    <?php
    }

    function update( $new_instance, $old_instance ) {

        $instance['title'] = sanitize_text_field( $new_instance['title'] );
        $instance['gal_id'] = absint( $new_instance['gal_id'] );

        return $instance;
    }
}
