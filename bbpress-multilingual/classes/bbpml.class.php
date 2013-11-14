<?php

class BBPML {
    
    function __construct( ) {
        add_action( 'plugins_loaded', array( $this,  'plugin_init'  ), 2 );
    }
    
    function plugin_init( ) {
        if ( !defined( 'ICL_SITEPRESS_VERSION' ) || ICL_PLUGIN_INACTIVE ) {
            if ( !function_exists( 'is_multisite' ) || !is_multisite() ) {
                add_action( 'admin_notices', array(  $this,  'notice_no_wpml' ) );
            }
			
            return false;
        } else if ( version_compare( ICL_SITEPRESS_VERSION, '2.0.5', '<' ) ) {
            add_action( 'admin_notices', array(  $this, 'notice_old_wpml_version'  ) );
            
           return false;
		} else if ( function_exists( 'bbp_get_version' ) ) {
			if ( version_compare( bbp_get_version(), '2.2.4', '<' ) ) {
            	add_action( 'admin_notices', array(  $this, 'notice_old_bbpress_version'  ) );
            
           		return false;
			}
        } else if ( !class_exists( 'bbPress' ) ) {
            add_action( 'admin_notices', array( $this, 'notice_no_bbpress' ) );
			
            return false;
        }
		
        add_action( 'save_post', array(  $this, 'update_post_language'  ), 100 );
      	add_action( 'init', array($this, '_dynamic_role_cap'), -10 );
		
		add_filter( 'posts_request', array( $this, '_bbp_has_replies_where' ), 9, 2 );
        add_filter( 'bbp_get_user_profile_url', array( $this, 'get_user_profile_url'  ), 10, 3 );
        add_filter( 'bbp_get_user_edit_profile_url', array( $this, 'get_user_edit_profile_url' ), 10, 3 );
        add_filter( 'bbp_get_favorites_permalink', array( $this, 'get_favorites_permalink' ), 10, 2 );
        add_filter( 'bbp_get_subscriptions_permalink', array( $this,  'get_subscriptions_permalink' ), 10, 2 );
        add_filter( 'bbp_get_user_topics_created_url', array( $this, 'get_user_topics_created_url' ), 10, 2 );
        add_filter( 'bbp_get_user_replies_created_url', array( $this, 'get_user_replies_created_url' ), 10, 2 );
        add_filter( 'post_type_archive_link', array( $this, 'post_type_archive_link' ), 10, 2 );
        
        if ( is_admin() ) {
        	global $pagenow;
        	
        	if ( $pagenow == 'post.php' && (int) isset( $_GET['post'] ) ) {
        		$post_id = (int) $_GET['post'];
        		$post_type = get_post_type( $post_id );
        		
        		if ( $post_type == 'topic' ) {
        			add_action( 'admin_init', array( $this, 'topic_duplicate_js' ) );
        		}
        	} elseif ( $pagenow == 'edit.php' && isset( $_GET['post_type'] ) == 'forum' ) {
        		add_filter( '_icl_posts_language_count_status', array( $this, 'language_filter_first_extra_cond' ) );
        	}

        	add_filter( 'bbp_get_dropdown', array( $this, 'get_dropdown' ), 10, 2 );
        }
    }
    
    /*
     * Fixes translated forums count on back-end
     */
    function language_filter_first_extra_cond(){ 
    	return "AND ( post_status = 'publish' OR post_type = 'private' OR post_type = 'hidden' )";
    }
    
    /*
     * Initiates roles again
     * Fixes an issue with insufficient permissions
     */
    function _dynamic_role_cap() {
		$current_user = wp_get_current_user();
		$current_user->get_role_caps();
	}
    
	/*
	 * Fixes replies count query
	 */
    function _bbp_has_replies_where( $where, $query ) {
        
        // Bail if no post_parent to replace
        if ( !is_numeric( $query->get( 'post_parent' ) ) )
            return $where;
        
        // Bail if not a topic and reply query
        if ( array(
             bbp_get_topic_post_type(),
            bbp_get_reply_post_type() 
        ) != $query->get( 'post_type' ) )
            return $where;
        
        // Bail if meta query
        if ( $query->get( 'meta_key' ) || $query->get( 'meta_query' ) )
            return $where;
        
        global $wpdb;
        
        // Table name for posts
        $table_name = $wpdb->prefix . 'posts';
        
        // Get the topic ID
        $topic_id = bbp_get_topic_id();
        
        // The text we're searching for
        $search = "WHERE 1=1  AND {$table_name}.post_parent = {$topic_id}";
        
        // The text to replace it with
        $replace = "WHERE 1=1 AND ({$table_name}.ID = {$topic_id} OR {$table_name}.post_parent = {$topic_id})";
        
        // Try to replace the search text with the replacement
        if ( $new_where = str_replace( $search, $replace, $where ) )
            $where = $new_where;
        
        return $where;
    }
    
   	/*
     * Adds language to topic or reply when it is posted
     */
    function update_post_language( $post_id ) {
        global $wpdb, $sitepress;
        
        $post_type = get_post_type( $post_id );
        
        if ( $post_type == 'reply' || $post_type == 'topic' ) {
            $element_type = 'post_' . $post_type;
            
            $translation = $wpdb->get_row( $wpdb->prepare( "SELECT translation_id, language_code FROM {$wpdb->prefix}icl_translations WHERE element_type = %s AND element_id = %d", $element_type, $post_id ) );
            
            if ( $translation->translation_id && $translation->language_code == $sitepress->get_default_language() ) {
                
                $update = $wpdb->update( $wpdb->prefix . 'icl_translations', array(
                     'language_code' => $sitepress->get_current_language() 
                ), array(
                     'translation_id' => $translation->translation_id 
                ), array(
                     '%s' 
                ), array(
                     '%d' 
                ) );
                
            }
        }
    }
    
    /*
     * Converts user profile url to the current language
     */
    function get_user_profile_url( $url, $user_id, $user_nicename ) {
        global $sitepress;
        
        return $sitepress->convert_url( $url, $sitepress->get_current_language() );
    }
    
    /*
     * Converts user edit profile url to the current language
     */
    function get_user_edit_profile_url( $url, $user_id, $user_nicename ) {
        global $sitepress;
        
        return $sitepress->convert_url( $url, $sitepress->get_current_language() );
    }
    
    /*
     * Converts favorites url to the current language
     */
    function get_favorites_permalink( $url, $user_id ) {
        global $sitepress;
        
        return $sitepress->convert_url( $url, $sitepress->get_current_language() );
    }
    
    /*
     * Converts subscriptions url to the current language
     */
    function get_subscriptions_permalink( $url, $user_id ) {
        global $sitepress;
        
        return $sitepress->convert_url( $url, $sitepress->get_current_language() );
    }
    
    /*
     * Converts user topics url to the current language
     */
    function get_user_topics_created_url( $url, $user_id ) {
        global $sitepress;
        
        return $sitepress->convert_url( $url, $sitepress->get_current_language() );
    }
    
    /*
     * Converts user replies url to the current language
     */
    function get_user_replies_created_url( $url, $user_id ) {
        global $sitepress;
        
        return $sitepress->convert_url( $url, $sitepress->get_current_language() );
    }
    
    /*
     * Fixes forums parent drop down
     */
    function get_dropdown( $retval, $args ) {
    	global $wpdb, $sitepress;
    	
    	$output = '<select name="parent_id" id="parent_id" tabindex="102">';
    	$output .= '<option value="" class="level-0">&mdash; '. __('No parent', 'bbpress-ml') .' &mdash;</option>';
    	
    	$forums = $wpdb->get_results("SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'forum' AND post_status = 'publish'");
    	
    	if ( $forums ) {
    		foreach( $forums as $forum ) {
    			$get_lang_data = wpml_get_language_information( $forum->ID );
    			
    			if ( $sitepress->get_current_language() != substr( $get_lang_data['locale'], 0, 2 ) ) {
    				continue;
    			}
    			
    			$output .= '<option value="'. $forum->ID .'" class="level-0">'. $forum->post_title .'</option>';
    		}
    	}
    	
    	$output .= '</select>';
    	
    	return $output;
    }
    
    /*
     * Fixes topic duplicate checkbox
     */
    function topic_duplicate_js() {
   		wp_enqueue_script( 'bbpress-ml', trailingslashit( BBPML_URL ) . 'assets/js/admin.js', array( 'jquery' ) );
    }
    
    /*
     * Fixes breadcrumb
     * 
     */
    function post_type_archive_link( $link, $post_type ) {
    	global $sitepress;
    	
    	if ( $post_type == 'forum' ) {
    		$link = $sitepress->convert_url( $link );
    	}
    	
    	return $link;
    }
    
    /*
     * Notice message when WPML plugin is not activated
     */
    function notice_no_wpml( ) {
?>
        <div class="message error"><p><?php
        printf( __( 'bbPress Multilingual is enabled but not effective. It requires <a href="%s">WPML</a> plugin in order to work.', 'bbpml' ), 'http://wpml.org/' );
?></p></div>
    <?php
    }
    
    /*
     * Notice message when WPML plugin version is prior to 2.0.5
     */
    function notice_old_wpml_version( ) {
?>
        <div class="message error"><p><?php
        printf( __( 'bbPress Multilingual is enabled but not effective. It is not compatible with <a href="%s">WPML</a> versions prior 2.0.5.', 'bbpml' ), 'http://wpml.org/' );
?></p></div>
    <?php
    }
    
    /*
     * Notice message when bbPress plugin version is prior to 2.2.4
     */
    function notice_old_bbpress_version( ) {
?>
        <div class="message error"><p><?php
        printf( __( 'bbPress Multilingual is enabled but not effective. It is not compatible with <a href="%s">bbPress</a> versions prior 2.2.4.', 'bbpml' ), 'http://bbpress.org/' );
?></p></div>
    <?php
    }
    
    /*
     * Notice message when bbPress plugin is not activated
     */
    function notice_no_bbpress( ) {
?>
        <div class="message error"><p><?php
        printf( __( 'bbPress Multilingual is enabled but not effective. It requires <a href="%s">bbPress</a> plugin in order to work.', 'bbpml' ), 'http://bbpress.org/' );
?></p></div>
    <?php
    }
    
}